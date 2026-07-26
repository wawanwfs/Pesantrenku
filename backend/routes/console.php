<?php

use App\Services\OperationJobRecoveryService;
use App\Services\OperationStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pesantenku:cleanup-files', function () {
    $storage = app(OperationStorage::class);
    $jobs = DB::table('operation_jobs')->whereNotNull('expires_at')->where('expires_at', '<=', now())->get();
    $cleaned = 0;
    $retained = 0;

    foreach ($jobs as $job) {
        $updates = ['updated_at' => now()];
        $allDeleted = true;

        foreach (['source_path', 'output_path', 'file_path'] as $column) {
            $path = $job->{$column};
            if (! $path) {
                continue;
            }

            $deleted = ! $storage->exists($path)
                || ($storage->delete($path) && ! $storage->exists($path));
            if ($deleted) {
                $updates[$column] = null;
            } else {
                $allDeleted = false;
                $retained++;
            }
        }

        if ($allDeleted && $job->status === 'completed') {
            $updates['status'] = 'expired';
        }
        DB::table('operation_jobs')->where('id', $job->id)->update($updates);
        $cleaned += $allDeleted ? 1 : 0;
    }

    $this->info("{$cleaned} operation job dibersihkan; {$retained} object gagal dihapus dan metadata dipertahankan untuk retry.");
})->purpose('Delete expired import/export files and retain metadata when object deletion fails');

Artisan::command('pesantenku:recover-operations', function (OperationJobRecoveryService $recovery) {
    $result = $recovery->recover();
    $this->info("{$result['recovered']} operasi didispatch ulang; {$result['failed']} ditandai gagal; {$result['examined']} diperiksa.");
})->purpose('Recover stale queued or processing import/export operations safely');

Schedule::command('pesantenku:recover-operations')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::call(function () {
    $storage = app(OperationStorage::class);
    $processingCutoff = now()->subMinutes(max(16, (int) config('pesantrenku.backup.creation_timeout_minutes', 20)));
    $queuedCutoff = now()->subMinutes(max(30, (int) config('pesantrenku.backup.queue_wait_timeout_minutes', 60)));
    $candidateIds = DB::table('backups')
        ->where(function ($query) use ($processingCutoff, $queuedCutoff) {
            $query->where(function ($processing) use ($processingCutoff) {
                $processing->where('status', 'processing')->where('updated_at', '<=', $processingCutoff);
            })->orWhere(function ($queued) use ($queuedCutoff) {
                $queued->where('status', 'queued')->where('updated_at', '<=', $queuedCutoff);
            });
        })
        ->pluck('id');

    foreach ($candidateIds as $id) {
        $backup = DB::transaction(function () use ($id, $processingCutoff, $queuedCutoff) {
            $row = DB::table('backups')->where('id', $id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, ['queued', 'processing'], true)) {
                return null;
            }
            $cutoff = $row->status === 'processing' ? $processingCutoff : $queuedCutoff;
            if (! $row->updated_at || Carbon::parse($row->updated_at)->greaterThan($cutoff)) {
                return null;
            }

            DB::table('backups')->where('id', $row->id)->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error' => 'Worker backup tidak menyelesaikan proses sebelum batas waktu operasional.',
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pesantren_id' => $row->pesantren_id,
                'user_id' => null,
                'action' => 'Backup tenant gagal karena worker timeout',
                'module' => 'Backup',
                'ip_address' => null,
                'metadata' => json_encode(['backup_id' => (int) $row->id, 'previous_status' => $row->status], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $row;
        }, 3);

        if ($backup?->file_path) {
            $storage->delete($backup->file_path);
        }
    }
})->name('recover-stale-tenant-backups')->everyTenMinutes()->withoutOverlapping();

Schedule::call(fn () => DB::table('idempotency_keys')->where('expires_at', '<=', now())->delete())
    ->name('cleanup-idempotency-keys')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('pesantenku:cleanup-files')->dailyAt('02:30')->withoutOverlapping();

Schedule::call(function () {
    $cutoff = now()->subMinutes(max(15, (int) config('pesantrenku.backup.restore_execution_timeout_minutes', 30)));
    DB::transaction(function () use ($cutoff) {
        $stale = DB::table('backup_restore_requests')
            ->where('status', 'executing')
            ->where('updated_at', '<=', $cutoff)
            ->lockForUpdate()
            ->get();

        foreach ($stale as $requestRow) {
            DB::table('backup_restore_requests')->where('id', $requestRow->id)->where('status', 'executing')->update([
                'status' => 'failed',
                'result' => json_encode([
                    'error' => 'RestoreExecutionTimeout',
                    'message' => 'Worker restore tidak menyelesaikan proses sebelum batas waktu operasional.',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            DB::table('backups')->where('id', $requestRow->backup_id)->where('status', 'restoring')->update([
                'status' => 'restore_failed',
                'updated_at' => now(),
            ]);
        }
    }, 3);
})->name('recover-stale-tenant-restores')->everyTenMinutes()->withoutOverlapping();
