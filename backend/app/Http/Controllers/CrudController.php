<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CrudAuthorizationConcern;
use App\Http\Controllers\Concerns\CrudExportConcern;
use App\Http\Controllers\Concerns\CrudImportConcern;
use App\Http\Controllers\Concerns\CrudPayloadConcern;
use App\Http\Controllers\Concerns\CrudPermissionConcern;
use App\Jobs\BuildResourceExportJob;
use App\Jobs\ProcessResourceImportJob;
use App\Services\EntitlementService;
use App\Services\OperationStorage;
use App\Services\ResourceAuthorization;
use App\Services\StepUpMfaService;
use App\Services\TenantContext;
use App\Services\UserAccessRevoker;
use App\Support\QueueNames;
use App\Support\ResourceCatalog;
use App\Support\SafeSpreadsheet;
use App\Support\TimelineRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrudController extends Controller
{
    use CrudAuthorizationConcern, CrudExportConcern, CrudImportConcern, CrudPayloadConcern, CrudPermissionConcern;

    private const USER_SENSITIVE_COLUMNS = ['password', 'remember_token', 'mfa_secret'];

    private const PROTECTED_SETTING_PREFIXES = ['permissions.', 'security.', 'auth.', 'backup-security.'];

    private array $schemaColumns = [];

    private array $schemaTables = [];

    private array $schemaDefinitions = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntitlementService $entitlements,
        private readonly OperationStorage $operationStorage,
        private readonly ResourceAuthorization $resourceAuthorization,
        private readonly UserAccessRevoker $accessRevoker,
        private readonly StepUpMfaService $stepUpMfa,
    ) {}

    public function index(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $query = $this->scopedQuery($request, $table);
        $this->applyRequestFilters($request, $query, $table);
        $this->applySearchFilter($request, $query, $table);

        $paginator = $query->latest("{$table}.id")->paginate(min(max((int) $request->query('per_page', 10), 5), 100));
        $paginator->getCollection()->transform(fn ($row) => $this->enrichRow($table, $row));

        return response()->json($paginator);
    }

    public function store(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'store');
        $table = $this->table($resource);
        $this->assertAccessAdministrationStepUp($request, $table);
        $this->assertPlatformMutationAllowed($request, $table, 'store');
        $data = $this->tenantContext->withTenantPayload($request, $table, $this->payload($request, $table));
        $this->assertUserTransitionAllowed($request, $table, $data);
        $this->assertMembershipTransitionAllowed($request, $table, $data);
        $this->assertSystemSettingPayloadAllowed($request, $table, $data);
        $this->assertPersonalPayload($request, $table, $data);
        $this->assertOperationalPayload($request, $table, $data);

        [$row, $status, $before] = DB::transaction(function () use ($request, $resource, $table, $data) {
            $this->entitlements->assertWriteQuota($request, $resource);
            $this->assertMembershipTransitionAllowed($request, $table, $data);
            if ($table === 'system_settings') {
                $existing = $this->scopedQuery($request, $table)
                    ->where("{$table}.key", $data['key'])
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    DB::table($table)->where('id', $existing->id)->update($data);
                    $after = DB::table($table)->find($existing->id);
                    $this->audit($request, 'update', $table, $existing->id, $existing, $after);

                    return [$after, 200, $existing];
                }
            }

            $id = DB::table($table)->insertGetId($data);
            $row = DB::table($table)->find($id);
            $this->audit($request, 'create', $table, $id, null, $row);
            if ($table === 'pesantren_user') {
                $this->accessRevoker->revoke((int) $row->user_id);
            }

            return [$row, 201, null];
        }, 3);

        $this->invalidateContextCache($table, $before, $row);

        return response()->json($this->enrichRow($table, $row), $status);
    }

    public function show(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);

        $row = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first() ?? abort(404);

        return response()->json($this->enrichRow($table, $row));
    }

    public function timeline(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $row = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
        abort_unless($row, 404);

        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->where('audit_logs.module', $table)
            ->where('audit_logs.row_id', $id)
            ->when(isset($row->pesantren_id), fn ($query) => $query->where('audit_logs.pesantren_id', $row->pesantren_id))
            ->select('audit_logs.*', 'users.name as actor_name')
            ->latest('audit_logs.id')
            ->paginate(min(100, max(10, (int) $request->integer('per_page', 50))))
            ->through(function ($log) use ($table, $row) {
                $metadata = json_decode($log->metadata ?: '{}', true) ?: [];
                $metadata = TimelineRedactor::redactMetadata($metadata, $row);

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor_name ?: 'Sistem',
                    'module' => $log->module,
                    'note' => $metadata['note'] ?? null,
                    'from_status' => $metadata['from_status'] ?? data_get($metadata, 'before.status'),
                    'to_status' => $metadata['to_status'] ?? data_get($metadata, 'after.status'),
                    'changes' => $this->changedFields((array) ($metadata['before'] ?? []), (array) ($metadata['after'] ?? []), $table),
                    'created_at' => $log->created_at,
                    'row_id' => (int) ($metadata['row_id'] ?? 0),
                ];
            });

        return response()->json($logs);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'update');
        $table = $this->table($resource);
        $this->assertAccessAdministrationStepUp($request, $table);
        $this->assertMembershipMutationAllowed($request, $table);
        $snapshot = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
        abort_unless($snapshot, 404);
        $this->assertPlatformMutationAllowed($request, $table, 'update', $snapshot);
        $this->assertWorkflowRecordEditable($resource, $snapshot);

        $data = $this->tenantContext->withTenantPayload($request, $table, $this->payload($request, $table, false));
        $this->assertUserTransitionAllowed($request, $table, $data, $snapshot);
        $this->assertSystemSettingPayloadAllowed($request, $table, $data, $snapshot);
        $this->assertPersonalPayload($request, $table, $data);
        $this->assertOperationalPayload($request, $table, $data, $snapshot);

        [$row, $after] = DB::transaction(function () use ($request, $resource, $table, $id, $data) {
            $row = $this->scopedQuery($request, $table)
                ->where("{$table}.id", $id)
                ->lockForUpdate()
                ->first();
            abort_unless($row, 404);
            $this->assertMembershipTransitionAllowed($request, $table, $data, $row);
            $this->assertWorkflowRecordEditable($resource, $row);
            DB::table($table)->where('id', $id)->update($data);
            $after = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
            $this->audit($request, 'update', $table, $id, $row, $after);
            if ($table === 'users') {
                $this->revokeUserAccessWhenSecurityChanges($table, $id, $data);
            }
            if ($table === 'pesantren_user') {
                $this->accessRevoker->revokeMany([(int) $row->user_id, (int) $after->user_id]);
            }

            return [$row, $after];
        }, 3);

        $this->invalidateContextCache($table, $row, $after);

        return response()->json($this->enrichRow($table, $after));
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'delete');
        $table = $this->table($resource);
        $this->assertAccessAdministrationStepUp($request, $table);
        $this->assertMembershipMutationAllowed($request, $table);

        $row = DB::transaction(function () use ($request, $table, $id) {
            $row = $this->scopedQuery($request, $table)
                ->where("{$table}.id", $id)
                ->lockForUpdate()
                ->first();
            abort_unless($row, 404);
            $this->assertPlatformMutationAllowed($request, $table, 'delete', $row);
            $this->assertMembershipTransitionAllowed($request, $table, [], $row, true);
            $this->assertSystemSettingPayloadAllowed($request, $table, [], $row);

            $this->hasColumn($table, 'deleted_at')
                ? DB::table($table)->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()])
                : DB::table($table)->where('id', $id)->delete();
            $this->audit($request, 'delete', $table, $id, $row, null);
            if ($table === 'users') {
                $this->accessRevoker->revoke($id);
            }
            if ($table === 'pesantren_user') {
                $this->accessRevoker->revoke((int) $row->user_id);
            }

            return $row;
        }, 3);

        $this->invalidateContextCache($table, $row, null);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    public function export(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $query = $this->scopedQuery($request, $table);
        $this->applyRequestFilters($request, $query, $table);
        $this->applySearchFilter($request, $query, $table);
        $rows = $query->limit((int) config('pesantrenku.export.sync_limit', 1000))->get();
        $columns = $this->visibleColumns($table);
        $formatted = $this->formatRowsForExport($rows, $columns);
        $format = strtolower((string) $request->query('format', 'csv'));

        if (in_array($format, ['xls', 'xlsx', 'excel'], true)) {
            $xml = SafeSpreadsheet::xmlHeader(Str::limit(Str::headline($table), 31, ''));
            $xml .= SafeSpreadsheet::xmlRow($columns);
            foreach ($formatted as $row) {
                $xml .= SafeSpreadsheet::xmlRow(collect($columns)->map(fn ($column) => $row[$column] ?? '')->all());
            }
            $xml .= SafeSpreadsheet::xmlFooter();

            return response($xml, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$table}.xls",
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($format === 'pdf') {
            $template = $this->pdfTemplate($request, $table);
            $lines = [
                $template['header'] ?? "Laporan {$table}",
                'Dicetak: '.now()->toDateTimeString(),
                '',
            ];
            foreach ($formatted as $row) {
                $lines[] = collect($columns)
                    ->filter(fn ($column) => ! in_array($column, ['deleted_at', 'metadata'], true))
                    ->map(fn ($column) => "{$column}: ".SafeSpreadsheet::plainText($row[$column] ?? '-'))
                    ->implode(' | ');
            }
            if (! empty($template['footer'])) {
                $lines[] = '';
                $lines[] = $template['footer'];
            }

            return $this->pdfResponse($template['title'] ?? "Laporan {$table}", $lines, "{$table}.pdf");
        }

        $csv = "\xEF\xBB\xBF".SafeSpreadsheet::csvLine($columns)."\n";
        foreach ($formatted as $row) {
            $csv .= SafeSpreadsheet::csvLine(collect($columns)->map(fn ($column) => $row[$column] ?? '')->all())."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$table}.csv",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function queueExport(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $format = strtolower((string) $request->input('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xls', 'xlsx', 'excel'], true), 422, 'Export job mendukung CSV atau Excel.');

        $user = $request->user();
        $tenantId = $this->tenantContext->tenantIdForTable($request, $table);
        $uuid = (string) Str::uuid();
        $jobId = DB::table('operation_jobs')->insertGetId([
            'uuid' => $uuid,
            'pesantren_id' => $tenantId,
            'user_id' => $user?->id,
            'type' => 'export',
            'resource' => $resource,
            'status' => 'queued',
            'progress' => 0,
            'queue_name' => QueueNames::EXPORTS,
            'payload' => json_encode([
                'format' => $format,
                'table' => $table,
                'filters' => $request->except(['_token']),
                'actor_context' => $this->queuedActorContext($request, $tenantId),
            ]),
            'expires_at' => now()->addDays((int) config('pesantrenku.operations.file_retention_days', 7)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BuildResourceExportJob::dispatch($jobId, $table, $tenantId, $format)->onQueue(QueueNames::EXPORTS);
        $this->audit($request, 'queue export', 'operation_jobs', $jobId, null, DB::table('operation_jobs')->find($jobId));

        return response()->json([
            'message' => 'Export besar masuk antrian.',
            'job' => [
                'uuid' => $uuid,
                'status' => 'queued',
                'resource' => $resource,
                'type' => 'export',
            ],
        ], 202);
    }

    public function analytics(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $query = $this->scopedQuery($request, $table);
        $this->applyRequestFilters($request, $query, $table);
        $this->applySearchFilter($request, $query, $table);
        $rows = $query->limit(1000)->get();
        $studentIds = $rows->pluck('student_id')->filter()->unique()->values();
        $studentMap = $studentIds->isNotEmpty()
            ? DB::table('students')->whereIn('id', $studentIds)->get()->keyBy('id')
            : collect();
        $classIds = $rows->pluck('class_id')->merge($studentMap->pluck('class_id'))->filter()->unique()->values();
        $classNames = $classIds->isNotEmpty()
            ? DB::table('classes')->whereIn('id', $classIds)->pluck('name', 'id')
            : collect();
        $dateColumn = $this->dateColumn($table);

        $byStatus = $this->hasColumn($table, 'status')
            ? $rows->groupBy(fn ($row) => (string) ($row->status ?? 'tanpa_status'))->map(fn ($items, $status) => ['name' => $status, 'value' => $items->count()])->values()
            : collect();

        $byDate = $dateColumn
            ? $rows->groupBy(fn ($row) => substr((string) ($row->{$dateColumn} ?? $row->created_at ?? ''), 0, 10) ?: 'tanpa_tanggal')
                ->map(fn ($items, $date) => ['name' => $date, 'value' => $items->count()])
                ->sortBy('name')
                ->values()
            : collect();

        $byClass = $rows->map(function ($row) use ($studentMap, $classNames) {
            $classId = $row->class_id ?? ($studentMap->get($row->student_id ?? 0)->class_id ?? null);

            return $classNames->get($classId, $classId ? "Kelas {$classId}" : 'Tanpa Kelas');
        })->countBy()->map(fn ($count, $name) => ['name' => $name, 'value' => $count])->values();

        $byStudent = $rows->filter(fn ($row) => isset($row->student_id))
            ->map(fn ($row) => $studentMap->get($row->student_id)?->name ?? "Santri {$row->student_id}")
            ->countBy()
            ->map(fn ($count, $name) => ['name' => $name, 'value' => $count])
            ->values();

        $amountTotal = $this->hasColumn($table, 'amount') ? (float) $rows->sum('amount') : null;

        return response()->json([
            'resource' => $resource,
            'total' => $rows->count(),
            'amount_total' => $amountTotal,
            'pending' => $this->hasColumn($table, 'status') ? $rows->whereIn('status', ['pending', 'waiting', 'unpaid', 'in_progress'])->count() : 0,
            'completed' => $this->hasColumn($table, 'status') ? $rows->whereIn('status', ['success', 'paid', 'approved', 'done', 'active'])->count() : 0,
            'rejected' => $this->hasColumn($table, 'status') ? $rows->whereIn('status', ['rejected', 'cancelled', 'inactive'])->count() : 0,
            'by_status' => $byStatus,
            'by_date' => $byDate,
            'by_class' => $byClass,
            'by_student' => $byStudent,
        ]);
    }

    public function importPreview(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'import');
        $table = $this->table($resource);
        $this->assertPlatformMutationAllowed($request, $table, 'import');
        $this->tenantContext->tenantIdForTable($request, $table);
        $request->validate(['file' => ['required', 'file', 'max:'.config('pesantrenku.import.max_kilobytes', 10240)]]);
        [$extension, $path] = $this->validatedImportFile($request);
        $rows = $this->rowsFromImportFile($path, $extension);
        $schema = $this->columns($table);
        $headers = collect($rows)->flatMap(fn ($row) => array_keys($row))->unique()->values()->all();
        $mapping = $this->importMapping($request, $request->input('mapping'), $schema, $headers, $table);
        $this->assertImportRowsAllowed($request, $table, $rows, $schema, $mapping);
        $analysis = $this->analyzeImportRows($request, $table, $rows, $schema, $mapping);

        return response()->json([
            'resource' => $resource,
            'headers' => $headers,
            'columns' => $this->importableColumns($request, $table),
            'mapping' => $mapping,
            'row_count' => count($rows),
            'preview' => collect($rows)->take((int) config('pesantrenku.import.preview_rows', 5))->values(),
            'summary' => [
                'valid' => $analysis['valid'],
                'invalid' => $analysis['invalid'],
                'blank' => $analysis['blank'],
            ],
            'errors' => $analysis['errors'],
        ]);
    }

    public function import(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'import');
        $table = $this->table($resource);
        $this->assertPlatformMutationAllowed($request, $table, 'import');
        $tenantId = $this->tenantContext->tenantIdForTable($request, $table);
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('pesantrenku.import.max_kilobytes', 10240)],
            'mapping' => ['nullable'],
            'queue' => ['nullable', 'boolean'],
        ]);
        [$extension, $path] = $this->validatedImportFile($request);
        $rows = $this->rowsFromImportFile($path, $extension);
        $schema = $this->columns($table);
        $headers = collect($rows)->flatMap(fn ($row) => array_keys($row))->unique()->values()->all();
        $mapping = $this->importMapping($request, $request->input('mapping'), $schema, $headers, $table);
        $this->assertImportRowsAllowed($request, $table, $rows, $schema, $mapping);
        $inserted = 0;
        $skipped = 0;
        $errors = [];

        $queueThreshold = max(1, (int) config('pesantrenku.import.queue_threshold', 500));
        if ($request->boolean('queue') || count($rows) >= $queueThreshold) {
            $user = $request->user();
            $preparedRows = [];
            $queueErrors = [];
            foreach ($rows as $rowNumber => $row) {
                $raw = $this->mappedImportRow($row, $schema, $mapping);
                if (! $raw) {
                    continue;
                }

                $validator = Validator::make($raw, $this->validationRules($request, $table, true), [], $this->validationAttributes());
                if ($validator->fails()) {
                    $queueErrors[] = ['row' => $rowNumber, 'messages' => $validator->errors()->all()];

                    continue;
                }

                $data = $this->tenantContext->withTenantPayload(
                    $request,
                    $table,
                    $this->preparePayload($request, $table, $validator->validated()),
                );

                try {
                    $this->assertPersonalPayload($request, $table, $data);
                    $this->assertOperationalPayload($request, $table, $data);
                } catch (\Throwable $exception) {
                    $queueErrors[] = ['row' => $rowNumber, 'messages' => [$exception->getMessage() ?: 'Data di luar scope akses role.']];

                    continue;
                }

                $preparedRows[] = $data;
            }

            if ($queueErrors !== []) {
                return response()->json([
                    'message' => 'Import antrian dibatalkan karena terdapat data tidak valid.',
                    'errors' => array_slice($queueErrors, 0, 20),
                ], 422);
            }

            $this->entitlements->assertWriteQuota($request, $resource, count($preparedRows));

            $uuid = (string) Str::uuid();
            $sourcePath = $this->storeImportRows($uuid, $preparedRows);
            $queuedMapping = collect($schema)->mapWithKeys(fn (string $column) => [$column => $column])->all();
            $jobId = DB::table('operation_jobs')->insertGetId([
                'uuid' => $uuid,
                'pesantren_id' => $tenantId,
                'user_id' => $user?->id,
                'type' => 'import',
                'resource' => $resource,
                'status' => 'queued',
                'progress' => 0,
                'total_rows' => count($preparedRows),
                'processed_rows' => 0,
                'queue_name' => QueueNames::IMPORTS,
                'source_path' => $sourcePath,
                'payload' => json_encode([
                    'mapping' => $queuedMapping,
                    'table' => $table,
                    'chunk_size' => (int) config('pesantrenku.import.chunk_size', 250),
                    'row_count' => count($preparedRows),
                    'file' => $request->file('file')->getClientOriginalName(),
                    'actor_context' => $this->queuedActorContext($request, $tenantId),
                ], JSON_UNESCAPED_UNICODE),
                'expires_at' => now()->addDays((int) config('pesantrenku.operations.file_retention_days', 7)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ProcessResourceImportJob::dispatch(
                $jobId,
                $table,
                $tenantId,
                $sourcePath,
                $queuedMapping,
                0,
                (int) config('pesantrenku.import.chunk_size', 250),
            )->onQueue(QueueNames::IMPORTS);
            $this->audit($request, 'queue import', 'operation_jobs', $jobId, null, DB::table('operation_jobs')->find($jobId));

            return response()->json([
                'message' => 'Import besar masuk antrian.',
                'queued' => true,
                'job' => [
                    'uuid' => $uuid,
                    'status' => 'queued',
                    'resource' => $resource,
                    'type' => 'import',
                ],
            ], 202);
        }

        DB::transaction(function () use ($rows, $schema, $table, $resource, $request, $mapping, &$inserted, &$skipped, &$errors) {
            foreach ($rows as $rowNumber => $row) {
                $raw = $this->mappedImportRow($row, $schema, $mapping);

                if (! $raw) {
                    $skipped++;

                    continue;
                }

                if (in_array('name', $schema, true) && empty($raw['name']) && ! isset($raw['title'])) {
                    $skipped++;

                    continue;
                }

                $validator = Validator::make($raw, $this->validationRules($request, $table, true), [], $this->validationAttributes());
                if ($validator->fails()) {
                    $skipped++;
                    if (count($errors) < 10) {
                        $errors[] = ['row' => $rowNumber, 'messages' => $validator->errors()->all()];
                    }

                    continue;
                }

                $data = $this->tenantContext->withTenantPayload(
                    $request,
                    $table,
                    $this->preparePayload($request, $table, $validator->validated()),
                );

                try {
                    $this->assertPersonalPayload($request, $table, $data);
                    $this->assertOperationalPayload($request, $table, $data);
                } catch (\Throwable $exception) {
                    $skipped++;
                    if (count($errors) < 10) {
                        $errors[] = ['row' => $rowNumber, 'messages' => [$exception->getMessage() ?: 'Data di luar scope akses role.']];
                    }

                    continue;
                }

                $this->entitlements->assertWriteQuota($request, $resource);
                DB::table($table)->insert($data);
                $inserted++;
            }
        });

        return response()->json([
            'message' => "{$inserted} baris berhasil diimport.",
            'resource' => $resource,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function bulk(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $this->assertGenericMutationAllowed($resource, 'bulk');
        $table = $this->table($resource);
        $this->assertPlatformMutationAllowed($request, $table, 'bulk');
        $this->tenantContext->tenantIdForTable($request, $table);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $this->assertMembershipMutationAllowed($request, $table);
        $ids = collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $rows = $this->scopedQuery($request, $table)->whereIn("{$table}.id", $ids->all())->get()->keyBy('id');
        abort_if($rows->isEmpty(), 404, 'Tidak ada data yang sesuai scope akses.');

        $updated = 0;
        DB::transaction(function () use ($request, $table, $rows, &$updated) {
            foreach ($rows as $row) {
                $this->hasColumn($table, 'deleted_at')
                    ? DB::table($table)->where('id', $row->id)->update(['deleted_at' => now(), 'updated_at' => now()])
                    : DB::table($table)->where('id', $row->id)->delete();
                $this->audit($request, 'bulk delete', $table, $row->id, $row, null);
                $this->invalidateContextCache($table, $row, null);
                $updated++;
            }
        });

        return response()->json([
            'message' => "{$updated} data berhasil dihapus.",
            'updated' => $updated,
            'action' => 'delete',
        ]);
    }

    public function permissionMatrix(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        $isSuperAdmin = $this->tenantContext->isSuperAdmin($request);
        if (! $isSuperAdmin) {
            $this->tenantContext->authorizeActiveRole($request, 'owner_pesantren', false, 'Hanya super admin dan owner tenant aktif yang dapat membuka matriks permission.');
        }

        $roles = DB::table('roles')->orderBy('id')->get(['name', 'label']);
        $matrix = $roles->mapWithKeys(function ($role) use ($request) {
            $access = $this->effectiveRoleAccess($request, $role->name);

            return [$role->name => [
                'label' => $role->label,
                'read' => array_values(array_unique(array_merge($access['read'], $access['write']))),
                'write' => array_values($access['write']),
                'actions' => array_values($access['actions'] ?? []),
            ]];
        });

        $endpoints = collect(ResourceCatalog::ALL)->flatMap(fn ($resource) => [
            ['method' => 'GET', 'path' => "/web-api/{$resource}", 'ability' => 'read', 'resource' => $resource],
            ['method' => 'POST', 'path' => "/web-api/{$resource}", 'ability' => 'write', 'resource' => $resource],
            ['method' => 'PATCH', 'path' => "/web-api/{$resource}/{id}", 'ability' => 'write', 'resource' => $resource],
            ['method' => 'DELETE', 'path' => "/web-api/{$resource}/{id}", 'ability' => 'write', 'resource' => $resource],
            ['method' => 'POST', 'path' => "/web-api/{$resource}/bulk", 'ability' => 'write', 'resource' => $resource],
            ['method' => 'POST', 'path' => "/web-api/{$resource}/import", 'ability' => 'write', 'resource' => $resource],
            ['method' => 'GET', 'path' => "/web-api/{$resource}/export", 'ability' => 'read', 'resource' => $resource],
        ])->values();

        return response()->json([
            'roles' => $roles,
            'resources' => $this->delegableResources(),
            'matrix' => $matrix,
            'endpoints' => $endpoints,
            'tenant_id' => $isSuperAdmin ? $this->tenantContext->activePesantrenId($request) : $this->tenantContext->activePesantrenId($request, 'owner_pesantren'),
            'source' => $isSuperAdmin && ! $this->tenantContext->activePesantrenId($request) ? 'global' : 'tenant',
            'editable' => ! $isSuperAdmin || (bool) $this->tenantContext->activePesantrenId($request),
            'workflow' => ResourceCatalog::workflowMatrix(),
            'workflow_abilities' => ResourceCatalog::workflowAbilities(),
        ]);
    }

    public function updatePermissionMatrix(Request $request)
    {
        $this->stepUpMfa->assert($request, 'permission-matrix');
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        $isSuperAdmin = $this->tenantContext->isSuperAdmin($request);
        if (! $isSuperAdmin) {
            $this->tenantContext->authorizeActiveRole($request, 'owner_pesantren', false, 'Hanya super admin dan owner tenant aktif yang dapat mengubah matriks permission.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'read' => ['nullable', 'array'],
            'read.*' => ['string', Rule::in($this->delegableResources())],
            'write' => ['nullable', 'array'],
            'write.*' => ['string', Rule::in($this->delegableResources())],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', Rule::in(ResourceCatalog::workflowAbilities())],
        ]);

        abort_if($validated['role'] === 'super_admin', 422, 'Permission super admin tidak dapat diubah.');
        if (! $isSuperAdmin) {
            abort_if($validated['role'] === 'owner_pesantren', 422, 'Owner tidak dapat mengubah permission owner.');
        }

        $read = collect($validated['read'] ?? [])->merge($validated['write'] ?? [])->unique()->values()->all();
        $write = collect($validated['write'] ?? [])->unique()->values()->all();
        $actions = collect($validated['actions'] ?? [])->unique()->values()->all();
        $invalidActionResources = collect($actions)
            ->map(fn (string $ability) => Str::beforeLast($ability, '.'))
            ->diff($write)
            ->values();
        abort_if($invalidActionResources->isNotEmpty(), 422, 'Aksi workflow memerlukan permission kelola untuk resource terkait: '.$invalidActionResources->implode(', '));
        $tenantId = $isSuperAdmin ? $this->tenantContext->activePesantrenId($request) : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        abort_unless($tenantId, 422, 'Pilih tenant sebelum mengubah matriks permission. Permission global bersifat read-only.');
        if ($tenantId) {
            $unsafe = collect([...$read, ...$write])->diff($this->delegableResources())->values();
            abort_if($unsafe->isNotEmpty(), 422, 'Permission tenant tidak boleh mencakup resource platform/kontrol: '.$unsafe->implode(', '));
        }

        DB::transaction(function () use ($request, $tenantId, $validated, $read, $write, $actions) {
            DB::table('pesantrens')->where('id', $tenantId)->lockForUpdate()->first();
            $overrides = $this->tenantPermissionOverrides($request, $tenantId);
            $overrides[$validated['role']] = ['read' => $read, 'write' => $write, 'actions' => $actions];
            $this->saveTenantPermissionOverrides($tenantId, $overrides);
            $this->audit($request, 'update permission matrix', 'permissions', null, null, (object) [
                'role' => $validated['role'],
                'read' => $read,
                'write' => $write,
                'actions' => $actions,
            ]);
            $affectedUsers = DB::table('pesantren_user')
                ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
                ->where('pesantren_user.pesantren_id', $tenantId)
                ->where('roles.name', $validated['role'])
                ->pluck('pesantren_user.user_id');
            $this->accessRevoker->revokeMany($affectedUsers);
        }, 3);

        return response()->json([
            'message' => 'Permission matrix berhasil diperbarui.',
            'role' => $validated['role'],
            'access' => ['read' => $read, 'write' => $write, 'actions' => $actions],
            'source' => 'tenant',
        ]);
    }

    public function customRoles(Request $request)
    {
        $tenantId = $this->permissionTenantId($request);
        $roles = DB::table('tenant_roles')
            ->where('pesantren_id', $tenantId)
            ->orderBy('label')
            ->get()
            ->map(function ($role) {
                $permissions = json_decode((string) $role->permissions, true) ?: ['read' => [], 'write' => [], 'actions' => []];

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => $role->label,
                    'description' => $role->description,
                    'status' => $role->status,
                    'read' => array_values(array_intersect($permissions['read'] ?? [], $this->delegableResources())),
                    'write' => array_values(array_intersect($permissions['write'] ?? [], $this->delegableResources())),
                    'actions' => array_values(array_intersect($permissions['actions'] ?? [], ResourceCatalog::workflowAbilities())),
                ];
            });
        $memberships = DB::table('pesantren_user')
            ->join('users', 'pesantren_user.user_id', '=', 'users.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->leftJoin('tenant_roles', 'pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
            ->where('pesantren_user.pesantren_id', $tenantId)
            ->select('pesantren_user.id', 'users.name as user_name', 'roles.label as base_role', 'tenant_roles.id as tenant_role_id', 'tenant_roles.label as tenant_role_label')
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'tenant_id' => $tenantId,
            'resources' => $this->delegableResources(),
            'workflow_abilities' => ResourceCatalog::workflowAbilities(),
            'roles' => $roles,
            'memberships' => $memberships,
        ]);
    }

    public function storeCustomRole(Request $request)
    {
        $this->stepUpMfa->assert($request, 'custom-role-create');
        $tenantId = $this->permissionTenantId($request);
        $data = $this->validateCustomRole($request, $tenantId);
        $id = DB::transaction(function () use ($request, $tenantId, $data) {
            DB::table('pesantrens')->where('id', $tenantId)->lockForUpdate()->first();
            $id = DB::table('tenant_roles')->insertGetId([
                'pesantren_id' => $tenantId,
                'name' => $data['name'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'permissions' => json_encode(['read' => $data['read'], 'write' => $data['write'], 'actions' => $data['actions']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => $data['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $after = DB::table('tenant_roles')->find($id);
            $this->audit($request, 'create custom role', 'tenant_roles', $id, null, $after);

            return $id;
        }, 3);

        return response()->json(['message' => 'Custom role berhasil dibuat.', 'id' => $id], 201);
    }

    public function updateCustomRole(Request $request, int $id)
    {
        $this->stepUpMfa->assert($request, 'custom-role-update');
        $tenantId = $this->permissionTenantId($request);
        $data = $this->validateCustomRole($request, $tenantId, $id);
        DB::transaction(function () use ($request, $tenantId, $id, $data) {
            DB::table('pesantrens')->where('id', $tenantId)->lockForUpdate()->first();
            $before = DB::table('tenant_roles')->where('pesantren_id', $tenantId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($before, 404);
            $users = DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('tenant_role_id', $id)->pluck('user_id');
            if (($data['status'] ?? 'active') === 'active' && (! empty($data['write']) || ! empty($data['actions']))) {
                $this->assertUsersMfaReady($users);
            }
            DB::table('tenant_roles')->where('id', $id)->update([
                'name' => $data['name'],
                'label' => $data['label'],
                'description' => $data['description'] ?? null,
                'permissions' => json_encode(['read' => $data['read'], 'write' => $data['write'], 'actions' => $data['actions']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => $data['status'],
                'updated_at' => now(),
            ]);
            $after = DB::table('tenant_roles')->find($id);
            $this->audit($request, 'update custom role', 'tenant_roles', $id, $before, $after);
            $this->accessRevoker->revokeMany($users);
        }, 3);

        return response()->json(['message' => 'Custom role berhasil diperbarui.']);
    }

    public function deleteCustomRole(Request $request, int $id)
    {
        $this->stepUpMfa->assert($request, 'custom-role-delete');
        $tenantId = $this->permissionTenantId($request);
        DB::transaction(function () use ($tenantId, $id, $request) {
            DB::table('pesantrens')->where('id', $tenantId)->lockForUpdate()->first();
            $role = DB::table('tenant_roles')->where('pesantren_id', $tenantId)->where('id', $id)->lockForUpdate()->first();
            abort_unless($role, 404);
            $users = DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('tenant_role_id', $id)->pluck('user_id');
            DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('tenant_role_id', $id)->update(['tenant_role_id' => null, 'updated_at' => now()]);
            DB::table('tenant_roles')->where('id', $id)->delete();
            $this->audit($request, 'delete custom role', 'tenant_roles', $id, $role, null);
            $this->accessRevoker->revokeMany($users);
        }, 3);

        return response()->json(['message' => 'Custom role berhasil dihapus.']);
    }

    public function assignCustomRole(Request $request, int $id)
    {
        $this->stepUpMfa->assert($request, 'custom-role-assignment');
        $tenantId = $this->permissionTenantId($request);
        $validated = $request->validate([
            'membership_ids' => ['required', 'array'],
            'membership_ids.*' => ['integer', 'distinct'],
            'mfa_code' => ['nullable', 'string', 'max:64'],
        ]);
        $membershipIds = collect($validated['membership_ids'])->map(fn ($value) => (int) $value)->unique()->values();

        DB::transaction(function () use ($tenantId, $id, $membershipIds, $request) {
            DB::table('pesantrens')->where('id', $tenantId)->lockForUpdate()->first();
            $role = DB::table('tenant_roles')
                ->where('pesantren_id', $tenantId)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();
            abort_unless($role, 404);

            $memberships = DB::table('pesantren_user')
                ->where('pesantren_id', $tenantId)
                ->whereIn('id', $membershipIds)
                ->lockForUpdate()
                ->get(['id', 'user_id']);
            abort_unless($memberships->count() === $membershipIds->count(), 422, 'Semua membership harus berasal dari tenant aktif.');

            $permissions = json_decode((string) $role->permissions, true) ?: [];
            if ($role->status === 'active' && (! empty($permissions['write']) || ! empty($permissions['actions']))) {
                $this->assertUsersMfaReady($memberships->pluck('user_id'));
            }

            $beforeUsers = DB::table('pesantren_user')
                ->where('pesantren_id', $tenantId)
                ->where('tenant_role_id', $id)
                ->lockForUpdate()
                ->pluck('user_id');
            DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('tenant_role_id', $id)->update(['tenant_role_id' => null, 'updated_at' => now()]);
            if ($membershipIds->isNotEmpty()) {
                DB::table('pesantren_user')->where('pesantren_id', $tenantId)->whereIn('id', $membershipIds)->update(['tenant_role_id' => $id, 'updated_at' => now()]);
            }
            $afterUsers = $memberships->pluck('user_id');
            $this->audit($request, 'assign custom role', 'tenant_roles', $id, null, (object) ['membership_ids' => $membershipIds->all(), 'pesantren_id' => $tenantId]);
            $this->accessRevoker->revokeMany($beforeUsers->merge($afterUsers)->unique()->values());
        }, 3);

        return response()->json(['message' => 'Assignment custom role berhasil diperbarui.', 'assigned' => $membershipIds->count()]);
    }
}
