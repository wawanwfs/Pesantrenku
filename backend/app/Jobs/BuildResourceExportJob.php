<?php

namespace App\Jobs;

use App\Services\OperationStorage;
use App\Services\ResourceAuthorization;
use App\Support\ResourceCatalog;
use App\Support\SafeSpreadsheet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BuildResourceExportJob implements ShouldQueue
{
    use Queueable;

    private const USER_SENSITIVE_COLUMNS = ['password', 'remember_token', 'mfa_secret'];

    private const GLOBAL_TABLES = ['roles', 'plans'];

    private const PROTECTED_SETTING_PREFIXES = ['permissions.', 'security.', 'auth.', 'backup-security.'];

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $jobId,
        private readonly string $table,
        private readonly ?int $tenantId,
        private readonly string $format = 'csv',
    ) {}

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(OperationStorage $storage, ResourceAuthorization $authorization): void
    {
        $lock = Cache::lock("operation-job:export:{$this->jobId}", $this->timeout + 120);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->process($storage, $authorization);
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('operation_jobs')
            ->where('id', $this->jobId)
            ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
            ->update([
                'status' => 'failed',
                'error' => Str::limit($exception?->getMessage() ?: 'Worker export berhenti tanpa menyelesaikan proses.', 4000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function process(OperationStorage $storage, ResourceAuthorization $authorization): void
    {
        $job = DB::table('operation_jobs')->where('id', $this->jobId)->first();
        if (! $job || in_array($job->status, ['completed', 'cancelled', 'expired'], true)) {
            return;
        }
        $payload = json_decode((string) $job->payload, true) ?: [];
        $jobTenantId = $job->pesantren_id ? (int) $job->pesantren_id : null;
        if (
            $job->type !== 'export'
            || ($payload['table'] ?? null) !== $this->table
            || ResourceCatalog::resourceForTable($this->table) !== $job->resource
            || $jobTenantId !== $this->tenantId
            || strtolower((string) ($payload['format'] ?? 'csv')) !== strtolower($this->format)
        ) {
            throw new \RuntimeException('Identitas queued export tidak cocok dengan operation job.');
        }
        $authorization->assertQueuedActor(
            (int) $job->user_id,
            $this->tenantId,
            (string) $job->resource,
            'read',
            is_array($payload['actor_context'] ?? null) ? $payload['actor_context'] : [],
        );

        DB::table('operation_jobs')->where('id', $this->jobId)->update([
            'status' => 'processing',
            'started_at' => $job->started_at ?: now(),
            'finished_at' => null,
            'error' => null,
            'updated_at' => now(),
        ]);

        $handle = null;
        try {
            $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
            $columns = collect(Schema::getColumnListing($this->table))
                ->reject(fn (string $column) => $this->table === 'users' && in_array($column, self::USER_SENSITIVE_COLUMNS, true))
                ->values()
                ->all();
            if (! in_array('id', $columns, true)) {
                throw new \RuntimeException("Tabel export {$this->table} tidak memiliki primary key id.");
            }

            // The row-level scope is resolved once and frozen for the whole
            // export so every page describes the same result set. It is then
            // re-resolved per page; a shrinking or shifting assignment must stop
            // the export instead of streaming rows from a stale scope.
            $actorScope = $this->resolveActorScope($job, $payload);
            $scopeFingerprint = $this->scopeFingerprint($actorScope);

            $query = DB::table($this->table);
            $this->applyTenantScope($query);
            $this->applyResolvedScope($query, $actorScope);
            $this->applyFilters($query, $filters);
            if (Schema::hasColumn($this->table, 'deleted_at')) {
                $query->whereNull("{$this->table}.deleted_at");
            }

            $limit = max(1, (int) config('pesantrenku.export.queued_limit', 10000));
            $total = min((int) (clone $query)->count(), $limit);
            $isExcel = in_array($this->format, ['xls', 'xlsx', 'excel'], true);
            $extension = $isExcel ? 'xls' : 'csv';
            $outputPath = 'exports/'.now()->format('Ymd')."/{$this->table}-{$job->uuid}.{$extension}";
            $handle = tmpfile();
            if (! $handle) {
                throw new \RuntimeException('Buffer export tidak dapat dibuat.');
            }

            if ($isExcel) {
                fwrite($handle, SafeSpreadsheet::xmlHeader(substr(ucwords(str_replace('_', ' ', $this->table)), 0, 31)));
                fwrite($handle, SafeSpreadsheet::xmlRow($columns));
            } else {
                fwrite($handle, "\xEF\xBB\xBF".SafeSpreadsheet::csvLine($columns)."\n");
            }

            $processed = 0;
            $lastId = 0;
            while ($processed < $total) {
                $authorization->assertQueuedActor(
                    (int) $job->user_id,
                    $this->tenantId,
                    (string) $job->resource,
                    'read',
                    is_array($payload['actor_context'] ?? null) ? $payload['actor_context'] : [],
                );
                if ($this->scopeFingerprint($this->resolveActorScope($job, $payload)) !== $scopeFingerprint) {
                    throw new \RuntimeException('Cakupan baris actor queued export berubah saat proses berjalan. Export dihentikan agar tidak mengeluarkan baris di luar cakupan terbaru.');
                }
                $pageSize = min(500, $total - $processed);
                $rows = (clone $query)
                    ->where("{$this->table}.id", '>', $lastId)
                    ->orderBy("{$this->table}.id")
                    ->limit($pageSize)
                    ->get();
                if ($rows->isEmpty()) {
                    break;
                }

                foreach ($rows as $row) {
                    $values = collect($columns)->map(fn ($column) => $row->{$column} ?? '')->all();
                    fwrite($handle, $isExcel ? SafeSpreadsheet::xmlRow($values) : SafeSpreadsheet::csvLine($values)."\n");
                    $processed++;
                    $lastId = max($lastId, (int) $row->id);
                }

                DB::table('operation_jobs')
                    ->where('id', $this->jobId)
                    ->whereNotIn('status', ['cancelled', 'expired'])
                    ->update([
                        'processed_rows' => $processed,
                        'progress' => $total > 0 ? min(99, (int) floor(($processed / $total) * 100)) : 99,
                        'updated_at' => now(),
                    ]);

                $currentStatus = DB::table('operation_jobs')->where('id', $this->jobId)->value('status');
                if (in_array($currentStatus, ['cancelled', 'expired'], true)) {
                    return;
                }
            }

            if ($isExcel) {
                fwrite($handle, SafeSpreadsheet::xmlFooter());
            }
            rewind($handle);
            if (! $storage->put($outputPath, $handle)) {
                throw new \RuntimeException('File export tidak dapat disimpan ke object storage.');
            }
            fclose($handle);
            $handle = null;

            DB::table('operation_jobs')
                ->where('id', $this->jobId)
                ->whereNotIn('status', ['cancelled', 'expired'])
                ->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'total_rows' => $total,
                    'processed_rows' => $processed,
                    'file_path' => $outputPath,
                    'output_path' => $outputPath,
                    'result' => json_encode([
                        'rows' => $processed,
                        'columns' => count($columns),
                        'format' => $extension,
                        'truncated' => (int) (clone $query)->count() > $limit,
                        'limit' => $limit,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'error' => null,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $exception) {
            DB::table('operation_jobs')
                ->where('id', $this->jobId)
                ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
                ->update([
                    'status' => 'processing',
                    'error' => Str::limit($exception->getMessage(), 4000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    private function applyTenantScope($query): void
    {
        if (! $this->tenantId) {
            return;
        }

        if (Schema::hasColumn($this->table, 'pesantren_id')) {
            $query->where("{$this->table}.pesantren_id", $this->tenantId);
            if ($this->table === 'system_settings') {
                foreach (self::PROTECTED_SETTING_PREFIXES as $prefix) {
                    $query->where('system_settings.key', 'not like', $prefix.'%');
                }
            }

            return;
        }

        if ($this->table === 'pesantrens') {
            $query->where('pesantrens.id', $this->tenantId);

            return;
        }

        if ($this->table === 'users') {
            $query->whereExists(function ($membership) {
                $membership->selectRaw('1')
                    ->from('pesantren_user')
                    ->whereColumn('pesantren_user.user_id', 'users.id')
                    ->where('pesantren_user.pesantren_id', $this->tenantId)
                    ->where('pesantren_user.status', 'active');
            });

            return;
        }

        if (! in_array($this->table, self::GLOBAL_TABLES, true)) {
            throw new \RuntimeException("Queued export {$this->table} tidak memiliki aturan tenant yang aman.");
        }
    }

    /**
     * Read the actor's current row-level scope from the database.
     *
     * The returned snapshot is deterministic (all id lists sorted) so it can be
     * fingerprinted and compared between pages.
     *
     * @return array<string, mixed>
     */
    private function resolveActorScope(object $job, array $payload): array
    {
        $empty = [
            'role' => null,
            'user_id' => 0,
            'teacher_ids' => [],
            'teacher_names' => [],
            'class_ids' => [],
            'subject_ids' => [],
            'student_ids' => [],
            'room_ids' => [],
        ];

        if (! $job->user_id) {
            if ($this->tenantId || ! in_array($this->table, self::GLOBAL_TABLES, true)) {
                throw new \RuntimeException('Queued export tenant tidak memiliki actor yang dapat diverifikasi.');
            }

            return $empty;
        }

        $actorContext = $payload['actor_context'] ?? null;
        if (
            ! is_array($actorContext)
            || (int) ($actorContext['version'] ?? 0) !== 1
            || (int) ($actorContext['user_id'] ?? 0) !== (int) $job->user_id
        ) {
            throw new \RuntimeException('Binding actor queued export tidak valid.');
        }

        $userIsActive = DB::table('users')
            ->where('id', $job->user_id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
        if (! $userIsActive) {
            throw new \RuntimeException('Actor queued export sudah tidak aktif.');
        }

        $userId = (int) $job->user_id;

        if (($actorContext['role'] ?? null) === 'super_admin') {
            $isSuperAdmin = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $userId)
                ->where('roles.name', 'super_admin')
                ->exists();
            if (! $isSuperAdmin) {
                throw new \RuntimeException('Role platform actor queued export sudah dicabut.');
            }

            return array_merge($empty, ['role' => 'super_admin', 'user_id' => $userId]);
        }

        if (! $this->tenantId) {
            throw new \RuntimeException('Queued export non-platform tidak memiliki tenant aktif.');
        }

        $membership = DB::table('pesantren_user')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->where('pesantren_user.id', (int) ($actorContext['membership_id'] ?? 0))
            ->where('pesantren_user.user_id', $userId)
            ->where('pesantren_user.pesantren_id', $this->tenantId)
            ->where('pesantren_user.status', 'active')
            ->where('pesantrens.status', 'active')
            ->whereNull('pesantrens.deleted_at')
            ->select('roles.name as role', 'pesantren_user.tenant_role_id')
            ->first();
        $contextTenantRoleId = $actorContext['tenant_role_id'] ?? null;
        if (
            ! $membership
            || (string) $membership->role !== (string) ($actorContext['role'] ?? '')
            || ($membership->tenant_role_id ? (int) $membership->tenant_role_id : null) !== ($contextTenantRoleId ? (int) $contextTenantRoleId : null)
        ) {
            throw new \RuntimeException('Membership actor queued export sudah tidak aktif.');
        }

        $role = (string) $membership->role;
        $scope = array_merge($empty, ['role' => $role, 'user_id' => $userId]);

        if (in_array($role, ['santri', 'wali_murid'], true)) {
            $scope['student_ids'] = $this->familyStudentIds($role, $userId);
            $scope['class_ids'] = DB::table('students')
                ->whereIn('id', $scope['student_ids'] ?: [0])
                ->pluck('class_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        } elseif ($role === 'guru') {
            $scope = array_merge($scope, $this->teacherScope($userId));
        } elseif ($role === 'musyrif') {
            $scope = array_merge($scope, $this->dormScope($userId));
        }

        foreach (['teacher_ids', 'teacher_names', 'class_ids', 'subject_ids', 'student_ids', 'room_ids'] as $key) {
            $values = array_values(array_unique($scope[$key]));
            sort($values);
            $scope[$key] = $values;
        }

        return $scope;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function scopeFingerprint(array $scope): string
    {
        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function applyResolvedScope($query, array $scope): void
    {
        $role = $scope['role'] ?? null;
        if ($role === null || $role === 'super_admin') {
            return;
        }

        if ($this->table === 'notification_logs' && $role !== 'owner_pesantren') {
            $userId = (int) $scope['user_id'];
            $query->where(function ($notifications) use ($userId) {
                $notifications->whereNull('notification_logs.user_id')
                    ->orWhere('notification_logs.user_id', $userId);
            });
        }

        if (in_array($role, ['santri', 'wali_murid'], true)) {
            $this->applyStudentFamilyScope($query, $scope['student_ids'], $scope['class_ids']);

            return;
        }

        if ($role === 'guru') {
            $this->applyTeacherScope($query, $scope);

            return;
        }

        if ($role === 'musyrif') {
            $this->applyDormScope($query, $scope);
        }
    }

    /**
     * @return array<int, int>
     */
    private function familyStudentIds(string $role, int $userId): array
    {
        return $role === 'santri'
            ? DB::table('students')
                ->where('pesantren_id', $this->tenantId)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all()
            : DB::table('students')
                ->join('guardians', 'students.guardian_id', '=', 'guardians.id')
                ->where('students.pesantren_id', $this->tenantId)
                ->where('guardians.pesantren_id', $this->tenantId)
                ->where('guardians.user_id', $userId)
                ->whereNull('students.deleted_at')
                ->whereNull('guardians.deleted_at')
                ->pluck('students.id')
                ->all();
    }

    /**
     * @return array{teacher_ids: array<int, int>, teacher_names: array<int, string>, class_ids: array<int, int>, subject_ids: array<int, int>, student_ids: array<int, int>}
     */
    private function teacherScope(int $userId): array
    {
        $teachers = DB::table('teachers')
            ->where('pesantren_id', $this->tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->get();
        $teacherIds = $teachers->pluck('id')->all();
        $teacherNames = $teachers->pluck('name')->filter()->values()->all();

        $assignments = DB::table('teacher_assignments')
            ->where('pesantren_id', $this->tenantId)
            ->where('status', 'active')
            ->whereIn('teacher_id', $teacherIds ?: [0])
            ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
            ->get();
        $classIds = $assignments->pluck('class_id')->filter()->unique()->values()->all();
        $subjectIds = $assignments->pluck('subject_id')->filter()->unique()->values()->all();

        $studentIds = DB::table('students')
            ->where('pesantren_id', $this->tenantId)
            ->whereIn('class_id', $classIds ?: [0])
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        return [
            'teacher_ids' => $teacherIds,
            'teacher_names' => $teacherNames,
            'class_ids' => $classIds,
            'subject_ids' => $subjectIds,
            'student_ids' => $studentIds,
        ];
    }

    /**
     * @return array{room_ids: array<int, int>, student_ids: array<int, int>}
     */
    private function dormScope(int $userId): array
    {
        $roomIds = DB::table('musyrif_room_assignments')
            ->where('pesantren_id', $this->tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $studentIds = DB::table('room_assignments')
            ->where('pesantren_id', $this->tenantId)
            ->where('status', 'active')
            ->whereIn('room_id', $roomIds ?: [0])
            ->where(fn ($query) => $query->whereNull('assigned_at')->orWhere('assigned_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', today()))
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'room_ids' => $roomIds,
            'student_ids' => $studentIds,
        ];
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<int, int>  $classIds
     */
    private function applyStudentFamilyScope($query, array $studentIds, array $classIds): void
    {
        if ($this->table === 'students') {
            $query->whereIn('students.id', $studentIds ?: [0]);

            return;
        }
        if (Schema::hasColumn($this->table, 'student_id')) {
            $query->whereIn("{$this->table}.student_id", $studentIds ?: [0]);

            return;
        }
        if ($this->table === 'schedules') {
            $query->whereIn('schedules.class_id', $classIds ?: [0]);

            return;
        }
        if ($this->table === 'invoice_items') {
            $query->whereExists(function ($invoices) use ($studentIds) {
                $invoices->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', 'invoice_items.invoice_id')
                    ->where('invoices.pesantren_id', $this->tenantId)
                    ->whereIn('invoices.student_id', $studentIds ?: [0]);
            });

            return;
        }
        if (in_array($this->table, ['medical_examinations', 'prescriptions'], true)) {
            $query->whereExists(function ($records) use ($studentIds) {
                $records->selectRaw('1')
                    ->from('medical_records')
                    ->whereColumn('medical_records.id', "{$this->table}.medical_record_id")
                    ->where('medical_records.pesantren_id', $this->tenantId)
                    ->whereIn('medical_records.student_id', $studentIds ?: [0]);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function applyTeacherScope($query, array $scope): void
    {
        $teacherIds = $scope['teacher_ids'];
        $teacherNames = $scope['teacher_names'];
        $classIds = $scope['class_ids'];
        $subjectIds = $scope['subject_ids'];
        $studentIds = $scope['student_ids'];

        if ($this->table === 'teachers') {
            $query->whereIn('teachers.id', $teacherIds ?: [0]);
        } elseif ($this->table === 'students') {
            $query->whereIn('students.id', $studentIds ?: [0]);
        } elseif ($this->table === 'classes') {
            $query->whereIn('classes.id', $classIds ?: [0]);
        } elseif ($this->table === 'subjects') {
            $query->whereIn('subjects.id', $subjectIds ?: [0]);
        } elseif (Schema::hasColumn($this->table, 'student_id')) {
            $query->whereIn("{$this->table}.student_id", $studentIds ?: [0]);
        } else {
            if (Schema::hasColumn($this->table, 'class_id')) {
                $query->whereIn("{$this->table}.class_id", $classIds ?: [0]);
            }
            if (Schema::hasColumn($this->table, 'subject_id')) {
                $query->whereIn("{$this->table}.subject_id", $subjectIds ?: [0]);
            }
            if ($this->table === 'schedules') {
                $query->whereIn('schedules.teacher_name', $teacherNames ?: ['__none__']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function applyDormScope($query, array $scope): void
    {
        $roomIds = $scope['room_ids'];
        $studentIds = $scope['student_ids'];

        if ($this->table === 'rooms') {
            $query->whereIn('rooms.id', $roomIds ?: [0]);
        } elseif ($this->table === 'students') {
            $query->whereIn('students.id', $studentIds ?: [0]);
        } else {
            if (Schema::hasColumn($this->table, 'room_id')) {
                $query->whereIn("{$this->table}.room_id", $roomIds ?: [0]);
            }
            if (Schema::hasColumn($this->table, 'student_id')) {
                $query->whereIn("{$this->table}.student_id", $studentIds ?: [0]);
            }
        }
    }

    private function applyFilters($query, array $filters): void
    {
        foreach (['status', 'student_id', 'class_id'] as $column) {
            if (($filters[$column] ?? null) !== null && $filters[$column] !== '' && Schema::hasColumn($this->table, $column)) {
                $query->where("{$this->table}.{$column}", $filters[$column]);
            }
        }

        $dateColumn = collect(['date', 'paid_at', 'due_date', 'start_at', 'noted_at', 'published_at', 'completed_at', 'created_at'])
            ->first(fn (string $column) => Schema::hasColumn($this->table, $column));
        if ($dateColumn && ! empty($filters['date_from'])) {
            $query->whereDate("{$this->table}.{$dateColumn}", '>=', $filters['date_from']);
        }
        if ($dateColumn && ! empty($filters['date_to'])) {
            $query->whereDate("{$this->table}.{$dateColumn}", '<=', $filters['date_to']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search === '') {
            return;
        }

        $searchColumns = collect(['name', 'title', 'nis', 'email', 'invoice_number', 'number', 'status', 'reference'])
            ->filter(fn (string $column) => Schema::hasColumn($this->table, $column));
        if ($searchColumns->isEmpty()) {
            return;
        }

        $query->where(function ($nested) use ($searchColumns, $search) {
            foreach ($searchColumns as $column) {
                $nested->orWhere("{$this->table}.{$column}", 'like', "%{$search}%");
            }
        });
    }
}
