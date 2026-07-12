<?php

namespace App\Jobs;

use App\Services\OperationStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcessResourceImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $jobId,
        private readonly string $table,
        private readonly ?int $tenantId,
        private readonly string $sourcePath,
        private readonly array $mapping,
        private readonly int $offset = 0,
        private readonly int $chunkSize = 250,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 90];
    }

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
            $schema = Schema::getColumnListing($this->table);
            $handle = $storage->readStream($this->sourcePath);
            if (! $handle) {
                throw new \RuntimeException('File temporary import tidak dapat dibuka.');
            }

            $lineNumber = 0;
            $read = 0;
            $inserted = 0;
            $skipped = 0;
            $errors = [];

            while (($line = fgets($handle)) !== false) {
                if ($lineNumber++ < $this->offset) {
                    continue;
                }
                if ($read >= $this->chunkSize) {
                    break;
                }

                $read++;
                $row = json_decode(trim($line), true);
                if (! is_array($row)) {
                    $skipped++;
                    continue;
                }

                $data = [];
                foreach ($this->mapping as $source => $target) {
                    if (! $target || ! in_array($target, $schema, true)) {
                        continue;
                    }
                    $value = $row[$source] ?? null;
                    $data[$target] = $value === '' ? null : $value;
                }

                if (! $data || (in_array('name', $schema, true) && empty($data['name']) && empty($data['title']))) {
                    $skipped++;
                    continue;
                }

                if (Schema::hasColumn($this->table, 'pesantren_id') && $this->tenantId) {
                    $data['pesantren_id'] = $this->tenantId;
                }
                if (Schema::hasColumn($this->table, 'created_at')) {
                    $data['created_at'] = now();
                }
                if (Schema::hasColumn($this->table, 'updated_at')) {
                    $data['updated_at'] = now();
                }

                try {
                    DB::table($this->table)->insert(collect($data)->only($schema)->all());
                    $inserted++;
                } catch (\Throwable $exception) {
                    $skipped++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => $this->offset + $read + 1, 'message' => $exception->getMessage()];
                    }
                }
            }
            fclose($handle);

            $previous = json_decode((string) $job->result, true) ?: [];
            $processed = min((int) $job->total_rows, $this->offset + $read);
            $result = [
                'inserted' => (int) ($previous['inserted'] ?? 0) + $inserted,
                'skipped' => (int) ($previous['skipped'] ?? 0) + $skipped,
                'errors' => array_slice(array_merge($previous['errors'] ?? [], $errors), 0, 20),
            ];
            $progress = $job->total_rows > 0 ? min(100, (int) floor(($processed / $job->total_rows) * 100)) : 100;

            DB::table('operation_jobs')->where('id', $this->jobId)->update([
                'processed_rows' => $processed,
                'progress' => $progress,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            if ($read > 0 && $processed < (int) $job->total_rows) {
                self::dispatch($this->jobId, $this->table, $this->tenantId, $this->sourcePath, $this->mapping, $processed, $this->chunkSize)
                    ->onQueue('imports');
                return;
            }

            $outputPath = 'imports/results/'.$job->uuid.'.json';
            $storage->put($outputPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            DB::table('operation_jobs')->where('id', $this->jobId)->update([
                'status' => 'completed',
                'progress' => 100,
                'output_path' => $outputPath,
                'file_path' => $outputPath,
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
}
