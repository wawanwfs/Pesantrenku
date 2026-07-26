<?php

namespace App\Jobs;

use App\Services\EntitlementService;
use App\Services\OperationStorage;
use App\Services\ResourceAuthorization;
use App\Support\QueueNames;
use App\Support\ResourceCatalog;
use App\Support\SignedImportRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcessResourceImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

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

    public function handle(
        OperationStorage $storage,
        ResourceAuthorization $authorization,
        EntitlementService $entitlements,
    ): void {
        $lock = Cache::lock("operation-job:import:{$this->jobId}", $this->timeout + 120);
        if (! $lock->get()) {
            return;
        }

        $nextOffset = null;
        try {
            $nextOffset = $this->process($storage, $authorization, $entitlements);
        } finally {
            $lock->release();
        }

        if ($nextOffset !== null) {
            self::dispatch(
                $this->jobId,
                $this->table,
                $this->tenantId,
                $this->sourcePath,
                $this->mapping,
                $nextOffset,
                $this->chunkSize,
            )->onQueue(QueueNames::IMPORTS);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('operation_jobs')
            ->where('id', $this->jobId)
            ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
            ->update([
                'status' => 'failed',
                'error' => Str::limit($exception?->getMessage() ?: 'Worker import berhenti tanpa menyelesaikan proses.', 4000),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function process(
        OperationStorage $storage,
        ResourceAuthorization $authorization,
        EntitlementService $entitlements,
    ): ?int {
        $job = DB::table('operation_jobs')->where('id', $this->jobId)->first();
        if (! $job || in_array($job->status, ['completed', 'cancelled', 'expired'], true)) {
            return null;
        }
        $payload = json_decode((string) $job->payload, true) ?: [];
        $jobTenantId = $job->pesantren_id ? (int) $job->pesantren_id : null;
        if (
            $job->type !== 'import'
            || ($payload['table'] ?? null) !== $this->table
            || ResourceCatalog::resourceForTable($this->table) !== $job->resource
            || $jobTenantId !== $this->tenantId
            || (string) $job->source_path !== $this->sourcePath
        ) {
            throw new \RuntimeException('Identitas queued import tidak cocok dengan operation job.');
        }
        $this->assertActorBinding($job, $payload);
        $authorization->assertQueuedActor(
            (int) $job->user_id,
            $this->tenantId,
            (string) $job->resource,
            'write',
            is_array($payload['actor_context'] ?? null) ? $payload['actor_context'] : [],
        );

        DB::table('operation_jobs')->where('id', $this->jobId)->update([
            'status' => 'processing',
            'started_at' => $job->started_at ?: now(),
            'finished_at' => null,
            'error' => null,
            'updated_at' => now(),
        ]);

        try {
            $schema = Schema::getColumnListing($this->table);
            if (! in_array('id', $schema, true)) {
                throw new \RuntimeException("Tabel import {$this->table} tidak memiliki primary key id.");
            }

            $handle = $storage->readStream($this->sourcePath);
            if (! $handle) {
                throw new \RuntimeException('File temporary import tidak dapat dibuka.');
            }

            try {
                $chunk = $this->readChunk($handle, $schema);
            } finally {
                fclose($handle);
            }

            $state = DB::transaction(function () use ($chunk, $job, $entitlements) {
                $locked = DB::table('operation_jobs')->where('id', $this->jobId)->lockForUpdate()->first();
                if (! $locked || in_array($locked->status, ['completed', 'cancelled', 'expired'], true)) {
                    return ['terminal' => true];
                }

                $currentOffset = (int) $locked->processed_rows;
                $totalRows = (int) $locked->total_rows;
                if ($currentOffset < $this->offset) {
                    throw new \RuntimeException('Chunk import diterima di luar urutan. Worker akan mencoba kembali setelah chunk sebelumnya selesai.');
                }

                // A retry can arrive after this chunk was committed but before the
                // next chunk was dispatched. Never insert the same rows twice.
                if ($currentOffset > $this->offset) {
                    return [
                        'terminal' => false,
                        'processed' => $currentOffset,
                        'total' => $totalRows,
                        'result' => json_decode((string) $locked->result, true) ?: [],
                    ];
                }

                if ($chunk['read'] === 0 && $currentOffset < $totalRows) {
                    throw new \RuntimeException('File import berakhir sebelum seluruh baris tervalidasi dapat diproses.');
                }

                $entitlements->assertQueuedWriteQuota(
                    $this->tenantId,
                    (string) $job->resource,
                    count($chunk['rows']),
                );

                $inserted = 0;
                $skipped = $chunk['skipped'];
                $errors = $chunk['errors'];
                foreach ($chunk['rows'] as $entry) {
                    try {
                        DB::table($this->table)->insert($entry['data']);
                        $inserted++;
                    } catch (\Throwable $exception) {
                        $skipped++;
                        if (count($errors) < 20) {
                            $errors[] = [
                                'row' => $entry['row'] ?? null,
                                'message' => Str::limit($exception->getMessage(), 1000),
                            ];
                        }
                    }
                }

                $previous = json_decode((string) $locked->result, true) ?: [];
                $processed = min($totalRows, $this->offset + $chunk['read']);
                $result = [
                    'inserted' => (int) ($previous['inserted'] ?? 0) + $inserted,
                    'skipped' => (int) ($previous['skipped'] ?? 0) + $skipped,
                    'errors' => array_slice(array_merge($previous['errors'] ?? [], $errors), 0, 20),
                ];
                $progress = $totalRows > 0 ? min(100, (int) floor(($processed / $totalRows) * 100)) : 100;

                DB::table('operation_jobs')->where('id', $this->jobId)->update([
                    'status' => 'processing',
                    'processed_rows' => $processed,
                    'progress' => $progress,
                    'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'error' => null,
                    'updated_at' => now(),
                ]);

                return [
                    'terminal' => false,
                    'processed' => $processed,
                    'total' => $totalRows,
                    'result' => $result,
                ];
            }, 3);

            if ($state['terminal'] ?? false) {
                return null;
            }

            if ($state['processed'] < $state['total']) {
                return (int) $state['processed'];
            }

            $outputPath = 'imports/results/'.$job->uuid.'.json';
            $contents = json_encode($state['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (! $storage->put($outputPath, $contents)) {
                throw new \RuntimeException('Ringkasan hasil import tidak dapat disimpan.');
            }

            DB::table('operation_jobs')
                ->where('id', $this->jobId)
                ->where('status', 'processing')
                ->where('processed_rows', '>=', $state['total'])
                ->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'output_path' => $outputPath,
                    'file_path' => $outputPath,
                    'error' => null,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            return null;
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
        }
    }

    private function readChunk($handle, array $schema): array
    {
        $lineNumber = 0;
        $read = 0;
        $skipped = 0;
        $errors = [];
        $rows = [];

        while (($line = fgets($handle)) !== false) {
            if ($lineNumber++ < $this->offset) {
                continue;
            }
            if ($read >= $this->chunkSize) {
                break;
            }

            $read++;
            try {
                $row = SignedImportRow::decode($line);
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException(
                    'Import antrean dihentikan pada baris '.($this->offset + $read).': '.$exception->getMessage(),
                    previous: $exception,
                );
            }

            $data = [];
            foreach ($this->mapping as $source => $target) {
                if (! $target || ! in_array($target, $schema, true) || ! array_key_exists($source, $row)) {
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

            $rows[] = [
                'row' => $this->offset + $read,
                'data' => collect($data)->only($schema)->all(),
            ];
        }

        return compact('read', 'skipped', 'errors', 'rows');
    }

    private function assertActorBinding(object $job, array $payload): void
    {
        $actorContext = $payload['actor_context'] ?? null;
        if (
            ! $job->user_id
            || ! is_array($actorContext)
            || (int) ($actorContext['version'] ?? 0) !== 1
            || (int) ($actorContext['user_id'] ?? 0) !== (int) $job->user_id
        ) {
            throw new \RuntimeException('Binding actor queued import tidak valid.');
        }

        $userIsActive = DB::table('users')
            ->where('id', $job->user_id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
        if (! $userIsActive) {
            throw new \RuntimeException('Actor queued import sudah tidak aktif.');
        }

        if (($actorContext['role'] ?? null) === 'super_admin') {
            $active = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $job->user_id)
                ->where('roles.name', 'super_admin')
                ->exists();
            if (! $active) {
                throw new \RuntimeException('Role platform actor queued import sudah dicabut.');
            }

            return;
        }

        if (! $this->tenantId) {
            throw new \RuntimeException('Queued import non-platform tidak memiliki tenant aktif.');
        }

        $membership = DB::table('pesantren_user')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->where('pesantren_user.id', (int) ($actorContext['membership_id'] ?? 0))
            ->where('pesantren_user.user_id', $job->user_id)
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
            throw new \RuntimeException('Membership actor queued import sudah tidak aktif.');
        }
    }
}
