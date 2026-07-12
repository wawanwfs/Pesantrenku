<?php

namespace App\Http\Controllers;

use App\Jobs\BuildResourceExportJob;
use App\Jobs\ProcessResourceImportJob;
use App\Services\OperationStorage;
use App\Services\TenantContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrudController extends Controller
{
    private array $schemaColumns = [];

    private array $schemaTables = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OperationStorage $operationStorage,
    ) {}

    private array $allowed = [
        'users', 'roles', 'pesantrens', 'plans', 'subscriptions', 'tenant-modules', 'memberships',
        'academic-years', 'classes', 'programs', 'subjects', 'teacher-assignments', 'students', 'guardians',
        'teachers', 'staff', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'attendances', 'dorm-attendances', 'grades',
        'report-cards', 'memorization-records', 'schedules', 'assignments', 'materials', 'announcements',
        'violations', 'permits', 'daily-notes', 'invoices', 'invoice-items', 'payments', 'payment-methods',
        'bank-accounts', 'expense-categories', 'expenses', 'cashflow-entries', 'discounts', 'letters',
        'room-mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine-stocks', 'medical-queues',
        'medical-records', 'medical-examinations', 'prescriptions', 'sick-letters', 'referrals',
        'notifications', 'audit-logs', 'system-settings', 'backups',
    ];

    private array $roleResources = [
        'owner_pesantren' => [
            'read' => ['pesantrens', 'memberships', 'tenant-modules', 'subscriptions', 'plans', 'audit-logs', 'system-settings', 'academic-years', 'classes', 'programs', 'subjects', 'teacher-assignments', 'students', 'guardians', 'teachers', 'staff', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'attendances', 'dorm-attendances', 'grades', 'report-cards', 'memorization-records', 'schedules', 'assignments', 'materials', 'announcements', 'violations', 'permits', 'daily-notes', 'invoices', 'invoice-items', 'payments', 'payment-methods', 'bank-accounts', 'expense-categories', 'expenses', 'cashflow-entries', 'discounts', 'letters', 'room-mutations', 'alumni', 'clinics', 'doctors', 'medicines', 'medicine-stocks', 'medical-queues', 'medical-records', 'medical-examinations', 'prescriptions', 'sick-letters', 'referrals', 'notifications', 'backups'],
            'write' => ['pesantrens', 'memberships', 'tenant-modules', 'teacher-assignments', 'musyrif-room-assignments', 'system-settings'],
        ],
        'admin_keuangan' => [
            'read' => ['students', 'guardians', 'programs', 'invoice-items', 'bank-accounts', 'academic-years', 'invoices', 'payments', 'payment-methods', 'discounts', 'cashflow-entries', 'expenses', 'system-settings', 'memberships'],
            'write' => ['students', 'guardians', 'programs', 'invoice-items', 'bank-accounts', 'academic-years', 'invoices', 'payments', 'payment-methods', 'discounts', 'cashflow-entries', 'expenses', 'system-settings', 'memberships'],
        ],
        'admin_akademik' => [
            'read' => ['academic-years', 'programs', 'subjects', 'classes', 'teachers', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'report-cards', 'students', 'announcements', 'system-settings'],
            'write' => ['academic-years', 'programs', 'subjects', 'classes', 'teachers', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'report-cards', 'students', 'announcements', 'system-settings'],
        ],
        'admin_tu' => [
            'read' => ['students', 'guardians', 'teachers', 'staff', 'classes', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'programs', 'letters', 'room-mutations', 'alumni', 'announcements', 'system-settings'],
            'write' => ['students', 'guardians', 'teachers', 'staff', 'classes', 'rooms', 'room-assignments', 'musyrif-room-assignments', 'programs', 'letters', 'room-mutations', 'alumni', 'announcements', 'system-settings'],
        ],
        'santri' => [
            'read' => ['students', 'grades', 'report-cards', 'memorization-records', 'attendances', 'permits', 'violations', 'medical-records', 'invoices', 'payments', 'announcements', 'notifications', 'schedules'],
            'write' => ['permits', 'payments'],
        ],
        'wali_murid' => [
            'read' => ['students', 'grades', 'report-cards', 'attendances', 'permits', 'violations', 'medical-records', 'invoices', 'payments', 'announcements', 'notifications', 'schedules'],
            'write' => ['permits', 'payments'],
        ],
        'guru' => [
            'read' => ['students', 'classes', 'subjects', 'teacher-assignments', 'schedules', 'attendances', 'grades', 'assignments', 'materials', 'report-cards', 'daily-notes', 'announcements'],
            'write' => ['attendances', 'grades', 'assignments', 'materials', 'daily-notes'],
        ],
        'musyrif' => [
            'read' => ['students', 'rooms', 'musyrif-room-assignments', 'dorm-attendances', 'permits', 'violations', 'daily-notes', 'room-mutations', 'schedules', 'announcements'],
            'write' => ['dorm-attendances', 'permits', 'violations', 'daily-notes', 'room-mutations'],
        ],
        'klinik' => [
            'read' => ['students', 'medical-queues', 'medical-records', 'medical-examinations', 'medicines', 'medicine-stocks', 'prescriptions', 'sick-letters', 'referrals', 'doctors', 'announcements'],
            'write' => ['medical-queues', 'medical-records', 'medical-examinations', 'medicines', 'medicine-stocks', 'prescriptions', 'sick-letters', 'referrals', 'doctors'],
        ],
    ];

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
        $table = $this->table($resource);
        $data = $this->tenantContext->withTenantPayload($request, $table, $this->payload($request, $table));
        $this->assertPersonalPayload($request, $table, $data);
        $this->assertOperationalPayload($request, $table, $data);

        if ($table === 'system_settings') {
            $existing = $this->scopedQuery($request, $table)
                ->where("{$table}.key", $data['key'])
                ->first();
            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update($data);
                $after = DB::table($table)->find($existing->id);
                $this->audit($request, 'update', $table, $existing->id, $existing, $after);
                $this->invalidateContextCache($table, $existing, $after);

                return response()->json($this->enrichRow($table, $after));
            }
        }

        $id = DB::table($table)->insertGetId($data);
        $row = DB::table($table)->find($id);
        $this->audit($request, 'create', $table, $id, null, $row);
        $this->invalidateContextCache($table, null, $row);

        return response()->json($this->enrichRow($table, $row), 201);
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
            ->when(isset($row->pesantren_id), fn ($query) => $query->where('audit_logs.pesantren_id', $row->pesantren_id))
            ->select('audit_logs.*', 'users.name as actor_name')
            ->latest('audit_logs.id')
            ->limit(100)
            ->get()
            ->map(function ($log) use ($table) {
                $metadata = json_decode($log->metadata ?: '{}', true) ?: [];

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
            })
            ->filter(fn ($log) => $log['row_id'] === $id)
            ->values();

        return response()->json(['data' => $logs]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $table = $this->table($resource);
        $row = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
        abort_unless($row, 404);

        $data = $this->tenantContext->withTenantPayload($request, $table, $this->payload($request, $table, false));
        $this->assertPersonalPayload($request, $table, $data);
        $this->assertOperationalPayload($request, $table, $data);
        DB::table($table)->where('id', $id)->update($data);
        $after = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
        $this->audit($request, 'update', $table, $id, $row, $after);
        $this->invalidateContextCache($table, $row, $after);

        return response()->json($this->enrichRow($table, $after));
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $this->authorizeResourceAccess($request, $resource, 'write');
        $table = $this->table($resource);
        $row = $this->scopedQuery($request, $table)->where("{$table}.id", $id)->first();
        abort_unless($row, 404);

        $this->hasColumn($table, 'deleted_at')
            ? DB::table($table)->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()])
            : DB::table($table)->where('id', $id)->delete();
        $this->audit($request, 'delete', $table, $id, $row, null);
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
        $columns = $this->columns($table);
        $formatted = $this->formatRowsForExport($rows, $columns);
        $format = strtolower((string) $request->query('format', 'csv'));

        if (in_array($format, ['xls', 'xlsx', 'excel'], true)) {
            $tsv = implode("\t", $columns)."\n";
            foreach ($formatted as $row) {
                $tsv .= collect($columns)->map(fn ($column) => str_replace(["\t", "\r", "\n"], ' ', (string) ($row[$column] ?? '')))->implode("\t")."\n";
            }

            return response($tsv, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$table}.xls",
            ]);
        }

        if ($format === 'pdf') {
            $template = $this->pdfTemplate($request, $table);
            $lines = [
                $template['header'] ?? "Laporan {$table}",
                'Dicetak: '.now()->toDateTimeString(),
                '',
            ];
            foreach (array_slice($formatted, 0, 40) as $row) {
                $lines[] = collect($columns)
                    ->filter(fn ($column) => ! in_array($column, ['deleted_at', 'metadata'], true))
                    ->map(fn ($column) => "{$column}: ".($row[$column] ?? '-'))
                    ->implode(' | ');
            }
            if (! empty($template['footer'])) {
                $lines[] = '';
                $lines[] = $template['footer'];
            }

            return $this->pdfResponse($template['title'] ?? "Laporan {$table}", $lines, "{$table}.pdf");
        }

        $csv = implode(',', $columns)."\n";
        foreach ($formatted as $row) {
            $csv .= collect($columns)->map(fn ($column) => '"'.str_replace('"', '""', (string) ($row[$column] ?? '')).'"')->implode(',')."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$table}.csv",
        ]);
    }

    public function queueExport(Request $request, string $resource)
    {
        $this->authorizeResourceAccess($request, $resource, 'read');
        $table = $this->table($resource);
        $format = strtolower((string) $request->input('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xls', 'xlsx', 'excel'], true), 422, 'Export job mendukung CSV atau Excel.');

        $user = $request->user();
        $tenantId = $user?->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $uuid = (string) Str::uuid();
        $jobId = DB::table('operation_jobs')->insertGetId([
            'uuid' => $uuid,
            'pesantren_id' => $tenantId,
            'user_id' => $user?->id,
            'type' => 'export',
            'resource' => $resource,
            'status' => 'queued',
            'progress' => 0,
            'queue_name' => 'exports',
            'payload' => json_encode([
                'format' => $format,
                'filters' => $request->except(['_token']),
            ]),
            'expires_at' => now()->addDays((int) config('pesantrenku.operations.file_retention_days', 7)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BuildResourceExportJob::dispatch($jobId, $table, $tenantId, $format)->onQueue('exports');
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
        $request->validate(['file' => ['required', 'file']]);
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true), 422, 'Format import harus CSV, TXT, XLS, atau XLSX.');
        $table = $this->table($resource);
        $rows = $this->rowsFromImportFile($request->file('file')->getRealPath(), $extension);
        $schema = $this->columns($table);
        $headers = collect($rows)->flatMap(fn ($row) => array_keys($row))->unique()->values()->all();
        $mapping = $this->importMapping($request->input('mapping'), $schema, $headers);
        $analysis = $this->analyzeImportRows($request, $table, $rows, $schema, $mapping);

        return response()->json([
            'resource' => $resource,
            'headers' => $headers,
            'columns' => $this->importableColumns($table),
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
        $request->validate([
            'file' => ['required', 'file'],
            'mapping' => ['nullable'],
            'queue' => ['nullable'],
        ]);
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true), 422, 'Format import harus CSV, TXT, XLS, atau XLSX.');
        $table = $this->table($resource);
        $rows = $this->rowsFromImportFile($request->file('file')->getRealPath(), $extension);
        $schema = $this->columns($table);
        $headers = collect($rows)->flatMap(fn ($row) => array_keys($row))->unique()->values()->all();
        $mapping = $this->importMapping($request->input('mapping'), $schema, $headers);
        $inserted = 0;
        $skipped = 0;
        $errors = [];

        if ($request->boolean('queue')) {
            $user = $request->user();
            $tenantId = $user?->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
            $uuid = (string) Str::uuid();
            $sourcePath = $this->storeImportRows($uuid, $rows);
            $jobId = DB::table('operation_jobs')->insertGetId([
                'uuid' => $uuid,
                'pesantren_id' => $tenantId,
                'user_id' => $user?->id,
                'type' => 'import',
                'resource' => $resource,
                'status' => 'queued',
                'progress' => 0,
                'total_rows' => count($rows),
                'processed_rows' => 0,
                'queue_name' => 'imports',
                'source_path' => $sourcePath,
                'payload' => json_encode([
                    'mapping' => $mapping,
                    'row_count' => count($rows),
                    'file' => $request->file('file')->getClientOriginalName(),
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
                $mapping,
                0,
                (int) config('pesantrenku.import.chunk_size', 250),
            )->onQueue('imports');
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

        DB::transaction(function () use ($rows, $schema, $table, $request, $mapping, &$inserted, &$skipped, &$errors) {
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

                $data = $this->tenantContext->withTenantPayload($request, $table, $this->preparePayload($request, $table, $raw));
                $validator = Validator::make($data, $this->validationRules($request, $table, true), [], $this->validationAttributes());
                if ($validator->fails()) {
                    $skipped++;
                    if (count($errors) < 10) {
                        $errors[] = ['row' => $rowNumber, 'messages' => $validator->errors()->all()];
                    }
                    continue;
                }

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
        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete', 'status'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'status' => ['required_if:action,status', 'nullable', 'string', 'max:60'],
        ]);

        $table = $this->table($resource);
        $ids = collect($validated['ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $rows = $this->scopedQuery($request, $table)->whereIn("{$table}.id", $ids->all())->get()->keyBy('id');
        abort_if($rows->isEmpty(), 404, 'Tidak ada data yang sesuai scope akses.');

        $updated = 0;
        DB::transaction(function () use ($request, $table, $validated, $rows, &$updated) {
            foreach ($rows as $row) {
                if ($validated['action'] === 'delete') {
                    $this->hasColumn($table, 'deleted_at')
                        ? DB::table($table)->where('id', $row->id)->update(['deleted_at' => now(), 'updated_at' => now()])
                        : DB::table($table)->where('id', $row->id)->delete();
                    $this->audit($request, 'bulk delete', $table, $row->id, $row, null);
                    $updated++;
                    continue;
                }

                abort_unless($this->hasColumn($table, 'status'), 422, 'Resource ini tidak memiliki kolom status.');
                DB::table($table)->where('id', $row->id)->update(['status' => $validated['status'], 'updated_at' => now()]);
                $after = DB::table($table)->where('id', $row->id)->first();
                $this->audit($request, 'bulk status', $table, $row->id, $row, $after);
                $updated++;
            }
        });

        return response()->json([
            'message' => "{$updated} data berhasil diproses.",
            'updated' => $updated,
            'action' => $validated['action'],
        ]);
    }

    public function permissionMatrix(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        abort_unless($user->hasRole(['super_admin', 'owner_pesantren']), 403, 'Hanya super admin dan owner yang dapat membuka matriks permission.');

        $roles = DB::table('roles')->orderBy('id')->get(['name', 'label']);
        $matrix = $roles->mapWithKeys(function ($role) use ($request) {
            $access = $this->effectiveRoleAccess($request, $role->name);

            return [$role->name => [
                'label' => $role->label,
                'read' => array_values(array_unique(array_merge($access['read'], $access['write']))),
                'write' => array_values($access['write']),
            ]];
        });

        $endpoints = collect($this->allowed)->flatMap(fn ($resource) => [
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
            'resources' => $this->allowed,
            'matrix' => $matrix,
            'endpoints' => $endpoints,
            'tenant_id' => $user->hasRole('super_admin') ? $this->tenantContext->activePesantrenId($request) : $this->tenantContext->activePesantrenId($request, 'owner_pesantren'),
            'source' => $user->hasRole('super_admin') && ! $this->tenantContext->activePesantrenId($request) ? 'global' : 'tenant',
            'editable' => true,
            'workflow' => [
                'permits' => ['approve' => ['musyrif', 'admin_tu', 'owner_pesantren'], 'reject' => ['musyrif', 'admin_tu', 'owner_pesantren'], 'cancel' => ['santri', 'wali_murid', 'musyrif', 'admin_tu', 'owner_pesantren']],
                'payments' => ['verify' => ['admin_keuangan', 'owner_pesantren'], 'reject' => ['admin_keuangan', 'owner_pesantren']],
                'medical-queues' => ['start' => ['klinik'], 'finish' => ['klinik'], 'cancel' => ['klinik']],
            ],
        ]);
    }

    public function updatePermissionMatrix(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);
        abort_unless($user->hasRole(['super_admin', 'owner_pesantren']), 403, 'Hanya super admin dan owner yang dapat mengubah matriks permission.');

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'read' => ['nullable', 'array'],
            'read.*' => ['string', Rule::in($this->allowed)],
            'write' => ['nullable', 'array'],
            'write.*' => ['string', Rule::in($this->allowed)],
        ]);

        abort_if($validated['role'] === 'super_admin', 422, 'Permission super admin tidak dapat diubah.');
        if ($user->hasRole('owner_pesantren')) {
            abort_if($validated['role'] === 'owner_pesantren', 422, 'Owner tidak dapat mengubah permission owner.');
        }

        $read = collect($validated['read'] ?? [])->merge($validated['write'] ?? [])->unique()->values()->all();
        $write = collect($validated['write'] ?? [])->unique()->values()->all();
        $tenantId = $user->hasRole('super_admin') ? $this->tenantContext->activePesantrenId($request) : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');

        if ($tenantId) {
            $overrides = $this->tenantPermissionOverrides($request, $tenantId);
            $overrides[$validated['role']] = ['read' => $read, 'write' => $write];
            $this->saveTenantPermissionOverrides($tenantId, $overrides);
        } else {
            $this->saveGlobalRolePermissions($validated['role'], $read, $write);
        }

        $this->audit($request, 'update permission matrix', 'permissions', null, null, (object) [
            'role' => $validated['role'],
            'read' => $read,
            'write' => $write,
        ]);

        return response()->json([
            'message' => 'Permission matrix berhasil diperbarui.',
            'role' => $validated['role'],
            'access' => ['read' => $read, 'write' => $write],
            'source' => $tenantId ? 'tenant' : 'global',
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
                $permissions = json_decode((string) $role->permissions, true) ?: ['read' => [], 'write' => []];
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => $role->label,
                    'description' => $role->description,
                    'status' => $role->status,
                    'read' => $permissions['read'] ?? [],
                    'write' => $permissions['write'] ?? [],
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

        return response()->json(['tenant_id' => $tenantId, 'resources' => $this->allowed, 'roles' => $roles, 'memberships' => $memberships]);
    }

    public function storeCustomRole(Request $request)
    {
        $tenantId = $this->permissionTenantId($request);
        $data = $this->validateCustomRole($request, $tenantId);
        $id = DB::table('tenant_roles')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'permissions' => json_encode(['read' => $data['read'], 'write' => $data['write']], JSON_UNESCAPED_UNICODE),
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $after = DB::table('tenant_roles')->find($id);
        $this->audit($request, 'create custom role', 'tenant_roles', $id, null, $after);

        return response()->json(['message' => 'Custom role berhasil dibuat.', 'id' => $id], 201);
    }

    public function updateCustomRole(Request $request, int $id)
    {
        $tenantId = $this->permissionTenantId($request);
        $before = DB::table('tenant_roles')->where('pesantren_id', $tenantId)->where('id', $id)->first();
        abort_unless($before, 404);
        $data = $this->validateCustomRole($request, $tenantId, $id);
        DB::table('tenant_roles')->where('id', $id)->update([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'permissions' => json_encode(['read' => $data['read'], 'write' => $data['write']], JSON_UNESCAPED_UNICODE),
            'status' => $data['status'],
            'updated_at' => now(),
        ]);
        $after = DB::table('tenant_roles')->find($id);
        $this->audit($request, 'update custom role', 'tenant_roles', $id, $before, $after);

        return response()->json(['message' => 'Custom role berhasil diperbarui.']);
    }

    public function deleteCustomRole(Request $request, int $id)
    {
        $tenantId = $this->permissionTenantId($request);
        $role = DB::table('tenant_roles')->where('pesantren_id', $tenantId)->where('id', $id)->first();
        abort_unless($role, 404);

        DB::transaction(function () use ($id, $role, $request) {
            DB::table('pesantren_user')->where('tenant_role_id', $id)->update(['tenant_role_id' => null, 'updated_at' => now()]);
            DB::table('tenant_roles')->where('id', $id)->delete();
            $this->audit($request, 'delete custom role', 'tenant_roles', $id, $role, null);
        });

        return response()->json(['message' => 'Custom role berhasil dihapus.']);
    }

    public function assignCustomRole(Request $request, int $id)
    {
        $tenantId = $this->permissionTenantId($request);
        abort_unless(DB::table('tenant_roles')->where('pesantren_id', $tenantId)->where('id', $id)->exists(), 404);
        $validated = $request->validate(['membership_ids' => ['required', 'array'], 'membership_ids.*' => ['integer']]);
        $membershipIds = collect($validated['membership_ids'])->map(fn ($value) => (int) $value)->unique()->values();

        DB::transaction(function () use ($tenantId, $id, $membershipIds, $request) {
            DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('tenant_role_id', $id)->update(['tenant_role_id' => null, 'updated_at' => now()]);
            if ($membershipIds->isNotEmpty()) {
                DB::table('pesantren_user')->where('pesantren_id', $tenantId)->whereIn('id', $membershipIds)->update(['tenant_role_id' => $id, 'updated_at' => now()]);
            }
            $this->audit($request, 'assign custom role', 'tenant_roles', $id, null, (object) ['membership_ids' => $membershipIds->all(), 'pesantren_id' => $tenantId]);
        });

        return response()->json(['message' => 'Assignment custom role berhasil diperbarui.', 'assigned' => $membershipIds->count()]);
    }

    private function applyRequestFilters(Request $request, $query, string $table): void
    {
        if ($request->filled('status') && $this->hasColumn($table, 'status')) {
            $query->where("{$table}.status", $request->query('status'));
        }

        if ($request->filled('student_id') && $this->hasColumn($table, 'student_id')) {
            $query->where("{$table}.student_id", (int) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            if ($this->hasColumn($table, 'class_id')) {
                $query->where("{$table}.class_id", (int) $request->query('class_id'));
            } elseif ($this->hasColumn($table, 'student_id')) {
                $query->whereExists(function ($subQuery) use ($table, $request) {
                    $subQuery->selectRaw('1')
                        ->from('students')
                        ->whereColumn('students.id', "{$table}.student_id")
                        ->where('students.class_id', (int) $request->query('class_id'));
                });
            }
        }

        $dateColumn = collect(['date', 'paid_at', 'due_date', 'start_at', 'noted_at', 'published_at', 'completed_at', 'created_at'])
            ->first(fn ($column) => $this->hasColumn($table, $column));

        if ($dateColumn && $request->filled('date_from')) {
            $query->whereDate("{$table}.{$dateColumn}", '>=', $request->query('date_from'));
        }

        if ($dateColumn && $request->filled('date_to')) {
            $query->whereDate("{$table}.{$dateColumn}", '<=', $request->query('date_to'));
        }
    }

    private function applySearchFilter(Request $request, $query, string $table): void
    {
        if (! ($search = $request->query('search'))) {
            return;
        }

        $columns = collect($this->columns($table))->filter(fn ($column) => in_array($column, ['name', 'title', 'nis', 'email', 'invoice_number', 'number', 'status', 'reference']));
        $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function importableColumns(string $table): array
    {
        return collect($this->columns($table))
            ->reject(fn ($column) => in_array($column, ['id', 'pesantren_id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->values()
            ->all();
    }

    private function storeImportRows(string $uuid, array $rows): string
    {
        $path = "imports/tmp/{$uuid}.jsonl";
        $handle = tmpfile();
        abort_unless($handle, 500, 'File temporary import tidak dapat dibuat.');

        foreach ($rows as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
        }
        rewind($handle);
        $stored = $this->operationStorage->put($path, $handle);
        fclose($handle);
        abort_unless($stored, 500, 'File temporary import tidak dapat disimpan.');

        return $path;
    }

    private function importMapping(mixed $mappingInput, array $schema, array $headers): array
    {
        if (is_string($mappingInput) && trim($mappingInput) !== '') {
            $decoded = json_decode($mappingInput, true);
            $mappingInput = is_array($decoded) ? $decoded : [];
        }

        $mappingInput = is_array($mappingInput) ? $mappingInput : [];
        $allowedColumns = $this->importableColumnsFromSchema($schema);
        $mapping = [];

        foreach ($headers as $header) {
            $requested = $mappingInput[$header] ?? $mappingInput[$this->normalizeImportHeader($header)] ?? null;
            if ($requested && in_array($requested, $allowedColumns, true)) {
                $mapping[$header] = $requested;
                continue;
            }

            $normalized = $this->normalizeImportHeader($header);
            $mapping[$header] = in_array($normalized, $allowedColumns, true) ? $normalized : '';
        }

        return $mapping;
    }

    private function importableColumnsFromSchema(array $schema): array
    {
        return collect($schema)
            ->reject(fn ($column) => in_array($column, ['id', 'pesantren_id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->values()
            ->all();
    }

    private function mappedImportRow(array $row, array $schema, array $mapping): array
    {
        $raw = [];
        foreach ($row as $header => $value) {
            $target = $mapping[$header] ?? null;
            if (! $target || ! in_array($target, $schema, true)) {
                continue;
            }

            $raw[$target] = $value === '' ? null : $value;
        }

        return $raw;
    }

    private function analyzeImportRows(Request $request, string $table, array $rows, array $schema, array $mapping): array
    {
        $valid = 0;
        $invalid = 0;
        $blank = 0;
        $errors = [];

        foreach ($rows as $rowNumber => $row) {
            $raw = $this->mappedImportRow($row, $schema, $mapping);
            if (! $raw) {
                $blank++;
                continue;
            }

            $data = $this->tenantContext->withTenantPayload($request, $table, $this->preparePayload($request, $table, $raw));
            $validator = Validator::make($data, $this->validationRules($request, $table, true), [], $this->validationAttributes());
            $scopeMessages = [];

            try {
                $this->assertPersonalPayload($request, $table, $data);
                $this->assertOperationalPayload($request, $table, $data);
            } catch (\Throwable $exception) {
                $scopeMessages[] = $exception->getMessage() ?: 'Data di luar scope akses role.';
            }

            if ($validator->fails() || $scopeMessages) {
                $invalid++;
                if (count($errors) < 10) {
                    $errors[] = ['row' => $rowNumber, 'messages' => array_values(array_merge($validator->errors()->all(), $scopeMessages))];
                }
                continue;
            }

            $valid++;
        }

        return compact('valid', 'invalid', 'blank', 'errors');
    }

    private function rowsFromImportFile(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            return $this->rowsFromXlsx($path);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        abort_unless($lines !== false && count($lines) > 0, 422, 'File import tidak dapat dibaca.');

        $firstLine = (string) ($lines[0] ?? '');
        $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ';') ? ';' : ',');
        $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), str_getcsv($firstLine, $delimiter));
        abort_unless(collect($headers)->filter()->isNotEmpty(), 422, 'Header import wajib tersedia.');

        $rows = [];
        foreach (array_slice($lines, 1) as $offset => $line) {
            if (trim((string) $line) === '') {
                continue;
            }

            $values = str_getcsv((string) $line, $delimiter);
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $values[$index] ?? null;
            }
            $rows[$offset + 2] = $row;
        }

        return $rows;
    }

    private function rowsFromXlsx(string $path): array
    {
        abort_unless(class_exists(\ZipArchive::class), 422, 'Import XLSX membutuhkan ekstensi PHP zip.');

        $zip = new \ZipArchive();
        abort_unless($zip->open($path) === true, 422, 'File XLSX tidak dapat dibuka.');

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            foreach ($shared?->si ?? [] as $item) {
                $text = '';
                if (isset($item->t)) {
                    $text = (string) $item->t;
                } elseif (isset($item->r)) {
                    foreach ($item->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        abort_unless($sheetXml, 422, 'Sheet pertama XLSX tidak ditemukan.');

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($sheet?->sheetData?->row ?? [] as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnLetters = preg_replace('/[^A-Z]/', '', $reference);
                $index = $this->excelColumnIndex($columnLetters);
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $values[$index] = $value;
            }
            ksort($values);
            $rows[] = $values;
        }

        $headers = array_map(fn ($header) => $this->normalizeImportHeader($header), array_values($rows[0] ?? []));
        abort_unless(collect($headers)->filter()->isNotEmpty(), 422, 'Header XLSX wajib tersedia.');

        $mapped = [];
        foreach (array_slice($rows, 1) as $offset => $values) {
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $values[$index] ?? null;
            }
            $mapped[$offset + 2] = $row;
        }

        return $mapped;
    }

    private function normalizeImportHeader(string $header): string
    {
        return trim(strtolower(str_replace([' ', '-'], '_', $header)));
    }

    private function excelColumnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function dateColumn(string $table): ?string
    {
        return collect(['date', 'paid_at', 'due_date', 'start_at', 'noted_at', 'published_at', 'completed_at', 'assigned_at', 'created_at'])
            ->first(fn ($column) => $this->hasColumn($table, $column));
    }

    private function changedFields(array $before, array $after, string $table): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (in_array($key, ['updated_at'], true)) {
                continue;
            }

            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => $key,
                    'before' => $old,
                    'after' => $new,
                    'before_label' => $this->humanDiffValue($key, $old),
                    'after_label' => $this->humanDiffValue($key, $new),
                    'label' => Str::headline(str_replace('_', ' ', str_ends_with($key, '_id') ? Str::beforeLast($key, '_id') : $key)),
                    'table' => $table,
                ];
            }
        }

        return $changes;
    }

    private function humanDiffValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $relation = match ($field) {
            'student_id' => ['students', 'name'],
            'class_id' => ['classes', 'name'],
            'subject_id' => ['subjects', 'name'],
            'teacher_id' => ['teachers', 'name'],
            'guardian_id' => ['guardians', 'name'],
            'room_id' => ['rooms', 'name'],
            'user_id' => ['users', 'name'],
            'role_id' => ['roles', 'label'],
            'pesantren_id' => ['pesantrens', 'name'],
            'plan_id' => ['plans', 'name'],
            'invoice_id' => ['invoices', 'invoice_number'],
            'payment_method_id' => ['payment_methods', 'name'],
            'medicine_id' => ['medicines', 'name'],
            'medical_record_id' => ['medical_records', 'complaint'],
            default => null,
        };

        if (! $relation || ! is_numeric($value)) {
            return $value;
        }

        return DB::table($relation[0])->where('id', (int) $value)->value($relation[1]) ?? "#{$value}";
    }

    private function table(string $resource): string
    {
        abort_unless(in_array($resource, $this->allowed, true), 404);

        return match ($resource) {
            'academic-years' => 'academic_years',
            'tenant-modules' => 'tenant_modules',
            'memberships' => 'pesantren_user',
            'room-assignments' => 'room_assignments',
            'dorm-attendances' => 'dorm_attendances',
            'report-cards' => 'report_cards',
            'memorization-records' => 'memorization_records',
            'daily-notes' => 'daily_notes',
            'invoice-items' => 'invoice_items',
            'payment-methods' => 'payment_methods',
            'bank-accounts' => 'bank_accounts',
            'expense-categories' => 'expense_categories',
            'cashflow-entries' => 'cashflow_entries',
            'room-mutations' => 'room_mutations',
            'medicine-stocks' => 'medicine_stocks',
            'medical-queues' => 'medical_queues',
            'medical-records' => 'medical_records',
            'medical-examinations' => 'medical_examinations',
            'sick-letters' => 'sick_letters',
            'audit-logs' => 'audit_logs',
            'system-settings' => 'system_settings',
            'notifications' => 'notification_logs',
            default => str_replace('-', '_', $resource),
        };
    }

    private function scopedQuery(Request $request, string $table)
    {
        $query = DB::table($table)->when($this->hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull("{$table}.deleted_at"));

        $query = $this->tenantContext->applyScope($request, $query, $table);

        return $this->applyPersonalScope($request, $query, $table);
    }

    private function applyPersonalScope(Request $request, $query, string $table)
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            return $query;
        }

        if ($user->hasRole(['santri', 'wali_murid'])) {
            return $this->applyStudentFamilyScope($request, $query, $table);
        }

        if ($user->hasRole('guru')) {
            return $this->applyTeacherScope($request, $query, $table);
        }

        if ($user->hasRole('musyrif')) {
            return $this->applyDormScope($request, $query, $table);
        }

        return $query;
    }

    private function applyStudentFamilyScope(Request $request, $query, string $table)
    {
        $studentIds = $this->accessibleStudentIds($request);
        $classIds = $this->requestMemo($request, 'personal-class-ids', fn () => DB::table('students')
            ->whereIn('id', $studentIds ?: [0])
            ->pluck('class_id')->filter()->values()->all());

        if ($table === 'students') {
            return $query->whereIn('students.id', $studentIds ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            return $query->whereIn("{$table}.student_id", $studentIds ?: [0]);
        }

        if ($table === 'schedules') {
            return $query->whereIn('schedules.class_id', $classIds ?: [0]);
        }

        if ($table === 'invoice_items') {
            return $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', "{$table}.invoice_id")
                    ->whereIn('invoices.student_id', $studentIds ?: [0]);
            });
        }

        if (in_array($table, ['medical_examinations', 'prescriptions'], true)) {
            return $query->whereExists(function ($subQuery) use ($studentIds, $table) {
                $subQuery->selectRaw('1')
                    ->from('medical_records')
                    ->whereColumn('medical_records.id', "{$table}.medical_record_id")
                    ->whereIn('medical_records.student_id', $studentIds ?: [0]);
            });
        }

        return $query;
    }

    private function applyTeacherScope(Request $request, $query, string $table)
    {
        $scope = $this->teacherScope($request);

        if ($table === 'teachers') {
            return $query->whereIn('teachers.id', $scope['teacher_ids'] ?: [0]);
        }

        if ($table === 'students') {
            return $query->whereIn('students.class_id', $scope['class_ids'] ?: [0]);
        }

        if ($table === 'classes') {
            return $query->whereIn('classes.id', $scope['class_ids'] ?: [0]);
        }

        if ($table === 'subjects') {
            return $query->whereIn('subjects.id', $scope['subject_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            return $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'class_id')) {
            $query->whereIn("{$table}.class_id", $scope['class_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'subject_id')) {
            $query->whereIn("{$table}.subject_id", $scope['subject_ids'] ?: [0]);
        }

        if ($table === 'schedules') {
            return $query->whereIn('schedules.teacher_name', $scope['teacher_names'] ?: ['__none__']);
        }

        return $query;
    }

    private function applyDormScope(Request $request, $query, string $table)
    {
        $scope = $this->dormScope($request);

        if ($table === 'rooms') {
            return $query->whereIn('rooms.id', $scope['room_ids'] ?: [0]);
        }

        if ($table === 'students') {
            return $query->whereIn('students.id', $scope['student_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'room_id')) {
            $query->whereIn("{$table}.room_id", $scope['room_ids'] ?: [0]);
        }

        if ($this->hasColumn($table, 'student_id')) {
            $query->whereIn("{$table}.student_id", $scope['student_ids'] ?: [0]);
        }

        return $query;
    }

    private function accessibleStudentIds(Request $request): array
    {
        return $this->requestMemo($request, 'accessible-student-ids', function () use ($request) {
            $user = $request->user()?->loadMissing('roles');
            if (! $user) {
                return [];
            }

            $tenantId = $this->tenantContext->activePesantrenId($request);

            if ($user->hasRole('santri')) {
                return DB::table('students')
                    ->where('pesantren_id', $tenantId)
                    ->where('user_id', $user->id)
                    ->pluck('id')
                    ->all();
            }

            if ($user->hasRole('wali_murid')) {
                return DB::table('students')
                    ->join('guardians', 'students.guardian_id', '=', 'guardians.id')
                    ->where('students.pesantren_id', $tenantId)
                    ->where('guardians.user_id', $user->id)
                    ->pluck('students.id')
                    ->all();
            }

            return [];
        });
    }

    private function assertPersonalPayload(Request $request, string $table, array $data): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user || ! $user->hasRole(['santri', 'wali_murid'])) {
            return;
        }

        $studentIds = $this->accessibleStudentIds($request);

        if (isset($data['student_id'])) {
            abort_unless(in_array((int) $data['student_id'], $studentIds, true), 403, 'Data santri tidak sesuai akses akun.');
        }

        if (isset($data['invoice_id'])) {
            abort_unless(
                DB::table('invoices')->where('id', (int) $data['invoice_id'])->whereIn('student_id', $studentIds ?: [0])->exists(),
                403,
                'Tagihan tidak sesuai akses akun.'
            );
        }

        if (isset($data['medical_record_id'])) {
            abort_unless(
                DB::table('medical_records')->where('id', (int) $data['medical_record_id'])->whereIn('student_id', $studentIds ?: [0])->exists(),
                403,
                'Rekam medis tidak sesuai akses akun.'
            );
        }
    }

    private function assertOperationalPayload(Request $request, string $table, array $data): void
    {
        $user = $request->user()?->loadMissing('roles');
        if (! $user) {
            return;
        }

        if ($user->hasRole('guru')) {
            $scope = $this->teacherScope($request);
            if (isset($data['student_id'])) {
                abort_unless(in_array((int) $data['student_id'], $scope['student_ids'], true), 403, 'Santri tidak berada dalam kelas guru.');
            }
            if (isset($data['class_id'])) {
                abort_unless(in_array((int) $data['class_id'], $scope['class_ids'], true), 403, 'Kelas tidak sesuai akses guru.');
            }
            if (isset($data['subject_id'])) {
                abort_unless(in_array((int) $data['subject_id'], $scope['subject_ids'], true), 403, 'Mata pelajaran tidak sesuai akses guru.');
            }
        }

        if ($user->hasRole('musyrif')) {
            $scope = $this->dormScope($request);
            if (isset($data['student_id'])) {
                abort_unless(in_array((int) $data['student_id'], $scope['student_ids'], true), 403, 'Santri tidak berada dalam binaan musyrif.');
            }
            if (isset($data['room_id'])) {
                abort_unless(in_array((int) $data['room_id'], $scope['room_ids'], true), 403, 'Kamar tidak berada dalam binaan musyrif.');
            }
        }
    }

    private function teacherScope(Request $request): array
    {
        return $this->requestMemo($request, 'teacher-scope', function () use ($request) {
        $tenantId = $this->tenantContext->activePesantrenId($request);
        $userId = $request->user()?->id;
        $teachers = DB::table('teachers')->where('pesantren_id', $tenantId)->where('user_id', $userId)->get();
        $teacherIds = $teachers->pluck('id')->all();
        $teacherNames = $teachers->pluck('name')->filter()->values()->all();
        $teacherSubjects = $teachers->pluck('subject')->filter()->values()->all();

        $assignments = $this->hasTable('teacher_assignments')
            ? DB::table('teacher_assignments')
                ->where('pesantren_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('teacher_id', $teacherIds ?: [0])
                ->get()
            : collect();

        if ($assignments->isNotEmpty()) {
            $subjectIds = $assignments->pluck('subject_id')->filter()->unique()->values()->all();
            $classIds = $assignments->pluck('class_id')->filter()->unique()->values()->all();
        } else {
            $subjectIds = DB::table('subjects')
                ->where('pesantren_id', $tenantId)
                ->where(function ($query) use ($teacherNames, $teacherSubjects) {
                    $query->whereIn('teacher_name', $teacherNames ?: ['__none__'])
                        ->orWhereIn('name', $teacherSubjects ?: ['__none__']);
                })
                ->pluck('id')
                ->all();

            $classIds = DB::table('schedules')
                ->where('pesantren_id', $tenantId)
                ->where(function ($query) use ($teacherNames, $subjectIds) {
                    $query->whereIn('teacher_name', $teacherNames ?: ['__none__'])
                        ->orWhereIn('subject_id', $subjectIds ?: [0]);
                })
                ->pluck('class_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $studentIds = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('class_id', $classIds ?: [0])
            ->pluck('id')
            ->all();

        return [
            'teacher_ids' => $teacherIds,
            'teacher_names' => $teacherNames,
            'subject_ids' => $subjectIds,
            'class_ids' => $classIds,
            'student_ids' => $studentIds,
        ];
        });
    }

    private function dormScope(Request $request): array
    {
        return $this->requestMemo($request, 'dorm-scope', function () use ($request) {
        $tenantId = $this->tenantContext->activePesantrenId($request);
        $userId = $request->user()?->id;
        $assignedRoomIds = $this->hasTable('musyrif_room_assignments')
            ? DB::table('musyrif_room_assignments')
                ->where('pesantren_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->pluck('room_id')
                ->filter()
                ->values()
                ->all()
            : [];

        $rooms = DB::table('rooms')
            ->where('pesantren_id', $tenantId)
            ->when($assignedRoomIds, fn ($query) => $query->whereIn('id', $assignedRoomIds), function ($query) {
                $query->where(function ($query) {
                    $query->where('block', 'like', '%Putri%')
                        ->orWhere('name', 'like', 'C-%');
                });
            })
            ->get();

        $roomNames = $rooms->pluck('name')->all();
        $studentIds = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->whereIn('room', $roomNames ?: ['__none__'])
            ->pluck('id')
            ->all();

        return [
            'room_ids' => $rooms->pluck('id')->all(),
            'room_names' => $roomNames,
            'student_ids' => $studentIds,
        ];
        });
    }

    private function formatRowsForExport($rows, array $columns): array
    {
        $maps = $this->relationMaps($rows, $columns);

        return $rows->map(function ($row) use ($columns, $maps) {
            $item = [];
            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;
                $item[$column] = isset($maps[$column]) && $value
                    ? ($maps[$column]->get($value) ?? "#{$value}")
                    : $value;
            }

            return $item;
        })->all();
    }

    private function enrichRow(string $table, ?object $row): ?object
    {
        if (! $row) {
            return $row;
        }

        $columns = array_keys((array) $row);
        $maps = $this->relationMaps(collect([$row]), $columns);
        $data = (array) $row;

        foreach ($maps as $column => $map) {
            $value = $row->{$column} ?? null;
            if (! $value) {
                continue;
            }

            $label = $map->get($value);
            if ($label) {
                $data[preg_replace('/_id$/', '', $column).'_name'] = $label;
            }
        }

        return (object) $data;
    }

    private function relationMaps($rows, array $columns): array
    {
        $maps = [];
        foreach ($this->relationTables() as $column => [$table, $labelColumn]) {
            if (! in_array($column, $columns, true) || ! $this->hasTable($table)) {
                continue;
            }

            $ids = $rows->pluck($column)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                continue;
            }

            $maps[$column] = DB::table($table)->whereIn('id', $ids)->pluck($labelColumn, 'id');
        }

        return $maps;
    }

    private function relationTables(): array
    {
        return [
            'academic_year_id' => ['academic_years', 'name'],
            'class_id' => ['classes', 'name'],
            'clinic_id' => ['clinics', 'name'],
            'doctor_id' => ['doctors', 'name'],
            'expense_category_id' => ['expense_categories', 'name'],
            'guardian_id' => ['guardians', 'name'],
            'invoice_id' => ['invoices', 'invoice_number'],
            'medical_record_id' => ['medical_records', 'complaint'],
            'medicine_id' => ['medicines', 'name'],
            'payment_method_id' => ['payment_methods', 'name'],
            'pesantren_id' => ['pesantrens', 'name'],
            'plan_id' => ['plans', 'name'],
            'program_id' => ['programs', 'name'],
            'role_id' => ['roles', 'label'],
            'room_id' => ['rooms', 'name'],
            'student_id' => ['students', 'name'],
            'subject_id' => ['subjects', 'name'],
            'teacher_id' => ['teachers', 'name'],
            'user_id' => ['users', 'name'],
        ];
    }

    private function pdfTemplate(Request $request, string $table): array
    {
        $key = match ($table) {
            'payments' => 'pdf.receipt_template',
            'letters', 'sick_letters' => 'pdf.letter_template',
            default => 'pdf.report_template',
        };

        $tenantId = $request->user()?->hasRole('super_admin') ? null : $this->tenantContext->activePesantrenId($request);
        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->when($tenantId, fn ($query) => $query->where('pesantren_id', $tenantId), fn ($query) => $query->whereNull('pesantren_id'))
            ->value('value');

        $decoded = json_decode((string) $setting, true);
        if (is_array($decoded)) {
            return $decoded + ['title' => 'Pesantenku', 'header' => 'Laporan Pesantenku', 'footer' => 'Dokumen dibuat otomatis oleh Pesantenku.'];
        }

        return [
            'title' => 'Pesantenku',
            'header' => $table === 'payments' ? 'Kwitansi Pembayaran Pesantenku' : 'Laporan Pesantenku',
            'footer' => 'Dokumen dibuat otomatis oleh Pesantenku.',
        ];
    }

    private function pdfResponse(string $title, array $lines, string $filename)
    {
        $escapedLines = collect($lines)->map(fn ($line) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr((string) $line, 0, 120)))->values();
        $content = "BT\n/F1 12 Tf\n50 800 Td\n({$this->pdfEscape($title)}) Tj\n0 -22 Td\n/F1 9 Tf\n";
        foreach ($escapedLines as $line) {
            $content .= "({$line}) Tj\n0 -14 Td\n";
        }
        $content .= "ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function audit(Request $request, string $action, string $table, ?int $rowId = null, ?object $before = null, ?object $after = null): void
    {
        $user = $request->user();
        if (! $user || $table === 'audit_logs') {
            return;
        }

        DB::table('audit_logs')->insert([
            'pesantren_id' => $this->hasColumn($table, 'pesantren_id')
                ? (int) ($after?->pesantren_id ?? $before?->pesantren_id ?? $this->tenantContext->activePesantrenId($request))
                : null,
            'user_id' => $user->id,
            'action' => strtoupper($action).' '.$table,
            'module' => $table,
            'ip_address' => $request->ip(),
            'metadata' => json_encode([
                'row_id' => $rowId,
                'before' => $before,
                'after' => $after,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function effectiveRoleAccess(Request $request, string $role): array
    {
        if ($role === 'super_admin') {
            return ['read' => $this->allowed, 'write' => $this->allowed];
        }

        $tenantId = $request->user()?->hasRole('super_admin')
            ? $this->tenantContext->activePesantrenId($request)
            : $this->tenantContext->activePesantrenId($request);
        if ($tenantId) {
            $overrides = $this->tenantPermissionOverrides($request, $tenantId);
            if (isset($overrides[$role])) {
                return [
                    'read' => array_values(array_intersect($overrides[$role]['read'] ?? [], $this->allowed)),
                    'write' => array_values(array_intersect($overrides[$role]['write'] ?? [], $this->allowed)),
                ];
            }
        }

        $dbAccess = $this->globalRolePermissions($role);
        if ($dbAccess !== null) {
            return $dbAccess;
        }

        return $this->roleResources[$role] ?? ['read' => [], 'write' => []];
    }

    private function customRoleAccessForUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || $user->hasRole(['super_admin', 'owner_pesantren'])) {
            return null;
        }

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $value = DB::table('pesantren_user')
            ->join('tenant_roles', 'pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
            ->where('pesantren_user.pesantren_id', $tenantId)
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('tenant_roles.status', 'active')
            ->value('tenant_roles.permissions');

        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'read' => array_values(array_intersect($decoded['read'] ?? [], $this->allowed)),
            'write' => array_values(array_intersect($decoded['write'] ?? [], $this->allowed)),
        ];
    }

    private function permissionTenantId(Request $request): int
    {
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $user->hasRole(['super_admin', 'owner_pesantren']), 403, 'Hanya super admin dan owner yang dapat mengelola custom role.');

        if ($user->hasRole('owner_pesantren')) {
            return (int) $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        }

        $tenantId = $this->tenantContext->activePesantrenId($request)
            ?? (int) ($request->input('pesantren_id') ?: $request->query('pesantren_id') ?: DB::table('pesantrens')->whereNull('deleted_at')->value('id'));
        abort_unless($tenantId && DB::table('pesantrens')->where('id', $tenantId)->whereNull('deleted_at')->exists(), 422, 'Pilih tenant untuk mengelola custom role.');

        return (int) $tenantId;
    }

    private function validateCustomRole(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('tenant_roles', 'name')->where('pesantren_id', $tenantId)->ignore($ignoreId)],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'read' => ['nullable', 'array'],
            'read.*' => ['string', Rule::in($this->allowed)],
            'write' => ['nullable', 'array'],
            'write.*' => ['string', Rule::in($this->allowed)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validated['write'] = collect($validated['write'] ?? [])->unique()->values()->all();
        $validated['read'] = collect($validated['read'] ?? [])->merge($validated['write'])->unique()->values()->all();
        $validated['status'] = $validated['status'] ?? 'active';

        return $validated;
    }

    private function globalRolePermissions(string $role): ?array
    {
        $permissions = DB::table('roles')
            ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('roles.name', $role)
            ->pluck('permissions.name')
            ->all();

        if (! $permissions) {
            return null;
        }

        return [
            'read' => collect($permissions)->filter(fn ($name) => str_ends_with($name, '.read'))->map(fn ($name) => Str::beforeLast($name, '.read'))->intersect($this->allowed)->values()->all(),
            'write' => collect($permissions)->filter(fn ($name) => str_ends_with($name, '.write'))->map(fn ($name) => Str::beforeLast($name, '.write'))->intersect($this->allowed)->values()->all(),
        ];
    }

    private function saveGlobalRolePermissions(string $role, array $read, array $write): void
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');
        abort_unless($roleId, 404);

        $permissionIds = collect($read)->map(fn ($resource) => "{$resource}.read")
            ->merge(collect($write)->map(fn ($resource) => "{$resource}.write"))
            ->unique()
            ->map(function ($permissionName) {
                return DB::table('permissions')->where('name', $permissionName)->value('id')
                    ?: DB::table('permissions')->insertGetId([
                        'name' => $permissionName,
                        'label' => Str::headline(str_replace(['.', '-'], ' ', $permissionName)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            })
            ->values()
            ->all();

        DB::table('permission_role')->where('role_id', $roleId)->delete();
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function tenantPermissionOverrides(Request $request, ?int $tenantId = null): array
    {
        $tenantId ??= $this->tenantContext->activePesantrenId($request);
        if (! $tenantId) {
            return [];
        }

        $value = DB::table('system_settings')
            ->where('pesantren_id', $tenantId)
            ->where('key', 'permissions.matrix_override')
            ->value('value');

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveTenantPermissionOverrides(int $tenantId, array $overrides): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['pesantren_id' => $tenantId, 'key' => 'permissions.matrix_override'],
            [
                'value' => json_encode($overrides, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function authorizeResourceAccess(Request $request, string $resource, string $ability): void
    {
        abort_unless(in_array($resource, $this->allowed, true), 404);

        $user = $request->user()?->loadMissing('roles');
        abort_unless($user, 401);

        if ($user->hasRole('super_admin')) {
            return;
        }

        $customAccess = $this->customRoleAccessForUser($request);
        if ($customAccess !== null) {
            $resources = $ability === 'read'
                ? array_unique(array_merge($customAccess['read'], $customAccess['write']))
                : $customAccess['write'];
            abort_unless(in_array($resource, $resources, true), 403, 'Custom role tenant tidak memiliki akses ke resource ini.');
            return;
        }

        $roles = $user->role_names;
        foreach ($roles as $role) {
            $access = $this->effectiveRoleAccess($request, $role);

            $resources = $ability === 'read'
                ? array_unique(array_merge($access['read'], $access['write']))
                : $access['write'];

            if (in_array($resource, $resources, true)) {
                return;
            }
        }

        abort(403, 'Role tidak memiliki akses ke resource ini.');
    }

    private function payload(Request $request, string $table, bool $includeCreatedAt = true): array
    {
        $validator = Validator::make(
            $request->all(),
            $this->validationRules($request, $table, $request->isMethod('post')),
            [],
            $this->validationAttributes()
        );

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Data tidak valid. Periksa kembali field yang ditandai.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $this->preparePayload($request, $table, $request->except(['id', 'created_at', 'updated_at', 'deleted_at']), $includeCreatedAt);
    }

    private function preparePayload(Request $request, string $table, array $raw, bool $includeCreatedAt = true): array
    {
        $data = collect($raw)
            ->only($this->columns($table))
            ->merge(['updated_at' => now()])
            ->when($includeCreatedAt && $this->hasColumn($table, 'created_at'), fn ($data) => $data->merge(['created_at' => $data->get('created_at', now())]));

        if ($table === 'users') {
            if ($data->has('password') && filled($data->get('password'))) {
                $data->put('password', Hash::make((string) $data->get('password')));
            } else {
                $data->forget('password');
            }
        }

        if ($table === 'system_settings' && $data->has('value') && filled($data->get('value')) && json_decode((string) $data->get('value'), true) === null) {
            $data->put('value', json_encode(['body' => (string) $data->get('value')], JSON_UNESCAPED_UNICODE));
        }

        return $data->all();
    }

    private function validationRules(Request $request, string $table, bool $creating): array
    {
        $required = fn (string $rule = 'string') => [$creating ? 'required' : 'sometimes', $rule];
        $nullableString = ['nullable', 'string', 'max:255'];
        $nullableText = ['nullable', 'string', 'max:5000'];
        $nullableDate = ['nullable', 'date'];
        $nullableInteger = ['nullable', 'integer'];
        $nullableNumber = ['nullable', 'numeric'];
        $status = ['nullable', 'string', 'max:60'];

        $rules = match ($table) {
            'users' => [
                'name' => $required('string'),
                'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255'],
                'username' => $nullableString,
                'phone' => $nullableString,
                'password' => [$creating ? 'required' : 'nullable', 'string', 'min:6'],
                'status' => $status,
            ],
            'students' => [
                'nis' => $required('string'),
                'name' => $required('string'),
                'class_id' => $nullableInteger,
                'guardian_id' => $nullableInteger,
                'gender' => $nullableString,
                'birth_date' => $nullableDate,
                'room' => $nullableString,
                'status' => $status,
            ],
            'teachers', 'guardians', 'staff', 'rooms', 'payment_methods', 'expense_categories', 'doctors', 'clinics', 'medicines', 'plans' => [
                'name' => $required('string'),
                'status' => $status,
            ],
            'programs', 'subjects' => [
                'name' => $required('string'),
                'code' => $required('string'),
                'status' => $status,
            ],
            'classes' => [
                'name' => $required('string'),
                'program_id' => $nullableInteger,
                'academic_year_id' => $nullableInteger,
                'student_count' => $nullableInteger,
                'status' => $status,
            ],
            'teacher_assignments' => [
                'teacher_id' => $required('integer'),
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'assigned_at' => $nullableDate,
                'ended_at' => $nullableDate,
                'status' => $status,
                'note' => $nullableText,
            ],
            'musyrif_room_assignments' => [
                'user_id' => $required('integer'),
                'room_id' => $required('integer'),
                'assigned_at' => $nullableDate,
                'ended_at' => $nullableDate,
                'status' => $status,
                'note' => $nullableText,
            ],
            'permits' => [
                'student_id' => $required('integer'),
                'type' => $required('string'),
                'start_at' => $nullableDate,
                'end_at' => $nullableDate,
                'reason' => $nullableText,
                'status' => $status,
            ],
            'payments' => [
                'invoice_id' => $nullableInteger,
                'student_id' => $required('integer'),
                'payment_method_id' => $nullableInteger,
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
                'paid_at' => $nullableDate,
                'status' => $status,
                'reference' => $nullableString,
            ],
            'invoices' => [
                'student_id' => $required('integer'),
                'invoice_number' => $required('string'),
                'period' => $required('string'),
                'amount' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
                'due_date' => $nullableDate,
                'status' => $status,
            ],
            'attendances', 'dorm_attendances' => [
                'student_id' => $required('integer'),
                'class_id' => $nullableInteger,
                'room_id' => $nullableInteger,
                'date' => [$creating ? 'required' : 'sometimes', 'date'],
                'session' => $nullableString,
                'activity' => $nullableString,
                'status' => $required('string'),
                'note' => $nullableText,
            ],
            'grades' => [
                'student_id' => $required('integer'),
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'semester' => $required('string'),
                'assignment_score' => ['nullable', 'numeric', 'between:0,100'],
                'midterm_score' => ['nullable', 'numeric', 'between:0,100'],
                'final_score' => ['nullable', 'numeric', 'between:0,100'],
                'attitude_score' => ['nullable', 'numeric', 'between:0,100'],
                'average_score' => ['nullable', 'numeric', 'between:0,100'],
                'grade' => $nullableString,
            ],
            'assignments', 'materials' => [
                'class_id' => $required('integer'),
                'subject_id' => $required('integer'),
                'title' => $required('string'),
                'due_date' => $nullableDate,
                'status' => $status,
            ],
            'daily_notes' => [
                'student_id' => $required('integer'),
                'noted_at' => $nullableDate,
                'note' => [$creating ? 'required' : 'sometimes', 'string', 'max:5000'],
            ],
            'medical_queues' => [
                'student_id' => $required('integer'),
                'queue_number' => $required('string'),
                'status' => $status,
                'estimated_time' => ['nullable'],
            ],
            'medical_records' => [
                'student_id' => $required('integer'),
                'complaint' => $required('string'),
                'diagnosis' => $nullableString,
                'date' => $nullableDate,
                'doctor_name' => $nullableString,
            ],
            'medical_examinations' => [
                'medical_record_id' => $required('integer'),
                'temperature' => $nullableNumber,
                'blood_pressure' => $nullableString,
                'pulse' => $nullableInteger,
                'doctor_note' => $nullableText,
            ],
            'medicine_stocks' => [
                'medicine_id' => $required('integer'),
                'quantity' => [$creating ? 'required' : 'sometimes', 'integer'],
                'type' => $required('string'),
                'date' => $nullableDate,
            ],
            'letters' => [
                'number' => $required('string'),
                'subject' => $required('string'),
                'type' => $required('string'),
                'destination' => $nullableString,
                'date' => $nullableDate,
                'status' => $status,
            ],
            'system_settings' => [
                'key' => $required('string'),
                'value' => ['nullable'],
            ],
            default => [],
        };

        if (! $rules && $creating) {
            $candidate = collect(['name', 'title', 'code', 'number', 'key', 'module', 'student_id', 'user_id'])
                ->first(fn (string $column) => $this->hasColumn($table, $column));
            if ($candidate !== null) {
                $rules[$candidate] = str_ends_with($candidate, '_id')
                    ? ['required', 'integer']
                    : ['required', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    private function validationAttributes(): array
    {
        return [
            'student_id' => 'santri',
            'class_id' => 'kelas',
            'subject_id' => 'mata pelajaran',
            'teacher_id' => 'guru',
            'room_id' => 'kamar',
            'invoice_id' => 'tagihan',
            'payment_method_id' => 'metode pembayaran',
            'medical_record_id' => 'rekam medis',
            'queue_number' => 'nomor antrian',
            'invoice_number' => 'nomor tagihan',
        ];
    }

    private function invalidateContextCache(string $table, ?object $before, ?object $after): void
    {
        if ($table === 'pesantren_user') {
            $this->tenantContext->invalidateMemberships(
                (int) ($after?->user_id ?? $before?->user_id ?? 0)
            );
        }

        if ($table === 'pesantrens') {
            $this->tenantContext->invalidatePesantren(
                (int) ($after?->id ?? $before?->id ?? 0)
            );
        }
    }

    private function columns(string $table): array
    {
        return $this->schemaColumns[$table] ??= Schema::getColumnListing($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaTables[$table] ??= Schema::hasTable($table);
    }

    private function requestMemo(Request $request, string $key, callable $resolver): mixed
    {
        $attribute = "pesantenku.crud.{$key}";
        if (! $request->attributes->has($attribute)) {
            $request->attributes->set($attribute, $resolver());
        }

        return $request->attributes->get($attribute);
    }
}
