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
            $columns = Schema::getColumnListing($this->table);
            $query = DB::table($this->table)
                ->when($this->tenantId && Schema::hasColumn($this->table, 'pesantren_id'), fn ($builder) => $builder->where('pesantren_id', $this->tenantId))
                ->when(Schema::hasColumn($this->table, 'deleted_at'), fn ($builder) => $builder->whereNull('deleted_at'));
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
}
