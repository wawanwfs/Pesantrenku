<?php

namespace App\Services;

use App\Jobs\BuildResourceExportJob;
use App\Jobs\ProcessResourceImportJob;
use App\Support\QueueNames;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationJobRecoveryService
{
    public function __construct(private readonly OperationStorage $storage) {}

    public function recover(): array
    {
        $processingCutoff = now()->subMinutes(max(16, (int) config('pesantrenku.operations.processing_timeout_minutes', 20)));
        $queuedCutoff = now()->subMinutes(max(30, (int) config('pesantrenku.operations.queue_wait_timeout_minutes', 60)));
        $maxAttempts = max(1, min(10, (int) config('pesantrenku.operations.max_recovery_attempts', 3)));

        $candidateIds = DB::table('operation_jobs')
            ->whereIn('type', ['import', 'export'])
            ->where(function ($query) use ($processingCutoff, $queuedCutoff) {
                $query->where(function ($processing) use ($processingCutoff) {
                    $processing->where('status', 'processing')->where('updated_at', '<=', $processingCutoff);
                })->orWhere(function ($queued) use ($queuedCutoff) {
                    $queued->where('status', 'queued')->where('updated_at', '<=', $queuedCutoff);
                });
            })
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $recovered = 0;
        $failed = 0;
        foreach ($candidateIds as $id) {
            $claim = DB::transaction(function () use ($id, $processingCutoff, $queuedCutoff, $maxAttempts) {
                $job = DB::table('operation_jobs')->where('id', $id)->lockForUpdate()->first();
                if (! $job || ! in_array($job->status, ['queued', 'processing'], true)) {
                    return null;
                }

                $cutoff = $job->status === 'processing' ? $processingCutoff : $queuedCutoff;
                if (! $job->updated_at || Carbon::parse($job->updated_at)->greaterThan($cutoff)) {
                    return null;
                }

                $attempts = (int) ($job->recovery_attempts ?? 0);
                if ($attempts >= $maxAttempts) {
                    DB::table('operation_jobs')->where('id', $job->id)->update([
                        'status' => 'failed',
                        'error' => 'Batas pemulihan worker terlampaui. Periksa queue backend dan ulangi operasi secara manual.',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return ['failed' => true];
                }

                $payload = json_decode((string) $job->payload, true) ?: [];
                try {
                    $descriptor = $this->descriptor($job, $payload);
                } catch (\Throwable $exception) {
                    DB::table('operation_jobs')->where('id', $job->id)->update([
                        'status' => 'failed',
                        'error' => Str::limit($exception->getMessage(), 4000),
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return ['failed' => true];
                }

                DB::table('operation_jobs')->where('id', $job->id)->update([
                    'status' => 'queued',
                    'queue_name' => $descriptor['queue'],
                    'recovery_attempts' => $attempts + 1,
                    'last_recovered_at' => now(),
                    'started_at' => null,
                    'finished_at' => null,
                    'error' => 'Operasi didispatch ulang setelah heartbeat worker melewati batas waktu.',
                    'updated_at' => now(),
                ]);

                return $descriptor;
            }, 3);

            if (! $claim) {
                continue;
            }
            if ($claim['failed'] ?? false) {
                $failed++;

                continue;
            }

            try {
                if ($claim['type'] === 'import') {
                    ProcessResourceImportJob::dispatch(
                        $claim['id'],
                        $claim['table'],
                        $claim['tenant_id'],
                        $claim['source_path'],
                        $claim['mapping'],
                        $claim['offset'],
                        $claim['chunk_size'],
                    )->onQueue(QueueNames::IMPORTS);
                } else {
                    BuildResourceExportJob::dispatch(
                        $claim['id'],
                        $claim['table'],
                        $claim['tenant_id'],
                        $claim['format'],
                    )->onQueue(QueueNames::EXPORTS);
                }
                $recovered++;
            } catch (\Throwable $exception) {
                DB::table('operation_jobs')->where('id', $claim['id'])->where('status', 'queued')->update([
                    'error' => Str::limit('Dispatch pemulihan gagal: '.$exception->getMessage(), 4000),
                    'updated_at' => now(),
                ]);
            }
        }

        return ['recovered' => $recovered, 'failed' => $failed, 'examined' => $candidateIds->count()];
    }

    private function descriptor(object $job, array $payload): array
    {
        $table = $payload['table'] ?? null;
        if (! is_string($table) || ! preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new \RuntimeException('Metadata tabel job tidak valid; operasi lama tidak aman untuk didispatch ulang.');
        }

        if ($job->type === 'import') {
            $mapping = $payload['mapping'] ?? null;
            if (! is_array($mapping) || ! $job->source_path || ! $this->storage->exists($job->source_path)) {
                throw new \RuntimeException('Source atau mapping import tidak tersedia untuk pemulihan.');
            }

            return [
                'type' => 'import',
                'queue' => QueueNames::IMPORTS,
                'id' => (int) $job->id,
                'table' => $table,
                'tenant_id' => $job->pesantren_id ? (int) $job->pesantren_id : null,
                'source_path' => (string) $job->source_path,
                'mapping' => $mapping,
                'offset' => (int) $job->processed_rows,
                'chunk_size' => max(1, min(1000, (int) ($payload['chunk_size'] ?? config('pesantrenku.import.chunk_size', 250)))),
            ];
        }

        $format = strtolower((string) ($payload['format'] ?? 'csv'));
        if (! in_array($format, ['csv', 'xls', 'xlsx', 'excel'], true)) {
            throw new \RuntimeException('Format export job tidak valid untuk pemulihan.');
        }

        return [
            'type' => 'export',
            'queue' => QueueNames::EXPORTS,
            'id' => (int) $job->id,
            'table' => $table,
            'tenant_id' => $job->pesantren_id ? (int) $job->pesantren_id : null,
            'format' => $format,
        ];
    }
}
