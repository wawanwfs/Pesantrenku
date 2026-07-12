<?php

namespace App\Jobs;

use App\Services\OperationStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildResourceExportJob implements ShouldQueue
{
    use Queueable;

    private const USER_SENSITIVE_COLUMNS = ['password', 'remember_token', 'mfa_secret'];

    private const GLOBAL_TABLES = ['roles', 'plans'];

    private const PROTECTED_SETTING_PREFIXES = ['permissions.', 'security.', 'auth.', 'backup-security.'];

    public int $tries = 3;

    public function __construct(
        private readonly int $jobId,
        private readonly string $table,
        private readonly ?int $tenantId,
        private readonly string $format = 'csv',
    ) {}

    public function handle(OperationStorage $storage): void
    {
        $job = DB::table('operation_jobs')->where('id', $this->jobId)->first();
        if (! $job || $job->status === 'cancelled') {
            return;
        }

        DB::table('operation_jobs')->where('id', $this->jobId)->update([
            'status' => 'processing',
            'started_at' => $job->started_at ?: now(),
            'updated_at' => now(),
        ]);

        try {
            $payload = json_decode((string) $job->payload, true) ?: [];
            $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
            $columns = collect(Schema::getColumnListing($this->table))
                ->reject(fn (string $column) => $this->table === 'users' && in_array($column, self::USER_SENSITIVE_COLUMNS, true))
                ->values()
                ->all();
            $query = DB::table($this->table);
            $this->applyTenantScope($query);
            $this->applyFilters($query, $filters);
            if (Schema::hasColumn($this->table, 'deleted_at')) {
                $query->whereNull("{$this->table}.deleted_at");
            }
            $total = min((int) $query->count(), (int) config('pesantrenku.export.queued_limit', 10000));
            $separator = in_array($this->format, ['xls', 'xlsx', 'excel'], true) ? "\t" : ',';
            $extension = $separator === "\t" ? 'xls' : 'csv';
            $outputPath = 'exports/'.now()->format('Ymd')."/{$this->table}-{$job->uuid}.{$extension}";
            $handle = tmpfile();
            if (! $handle) {
                throw new \RuntimeException('Buffer export tidak dapat dibuat.');
            }

            fwrite($handle, $this->line($columns, $separator)."\n");
            $processed = 0;
            $query->orderBy('id')->limit($total)->chunkById(500, function ($rows) use ($handle, $columns, $separator, $total, &$processed) {
                foreach ($rows as $row) {
                    fwrite($handle, $this->line(collect($columns)->map(fn ($column) => $row->{$column} ?? '')->all(), $separator)."\n");
                    $processed++;
                }
                DB::table('operation_jobs')->where('id', $this->jobId)->update([
                    'processed_rows' => $processed,
                    'progress' => $total > 0 ? min(99, (int) floor(($processed / $total) * 100)) : 99,
                    'updated_at' => now(),
                ]);
            });
            rewind($handle);
            if (! $storage->put($outputPath, $handle)) {
                fclose($handle);
                throw new \RuntimeException('File export tidak dapat disimpan ke object storage.');
            }
            fclose($handle);

            DB::table('operation_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'progress' => 100,
                'total_rows' => $total,
                'processed_rows' => $processed,
                'file_path' => $outputPath,
                'output_path' => $outputPath,
                'result' => json_encode(['rows' => $processed, 'columns' => count($columns), 'format' => $extension]),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('operation_jobs')->where('id', $this->jobId)->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function line(array $values, string $separator): string
    {
        return collect($values)
            ->map(fn ($value) => str_replace(["\t", "\r", "\n"], ' ', (string) $value))
            ->map(fn ($value) => $separator === ',' ? '"'.str_replace('"', '""', $value).'"' : $value)
            ->implode($separator);
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
