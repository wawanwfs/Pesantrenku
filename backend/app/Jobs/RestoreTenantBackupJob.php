<?php

namespace App\Jobs;

use App\Services\BackupRestoreService;
use App\Services\RestoreApprovalService;
use App\Services\UserAccessRevoker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestoreTenantBackupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $restoreRequestId,
        private readonly int $actorUserId,
    ) {}

    public function handle(BackupRestoreService $restoreService, RestoreApprovalService $approvals, UserAccessRevoker $accessRevoker): void
    {
        $lock = Cache::lock("pesantenku:restore-request:{$this->restoreRequestId}", $this->timeout + 60);
        if (! $lock->get()) {
            // Duplicate delivery while another worker owns the destructive operation is harmless.
            return;
        }

        try {
            $requestRow = DB::table('backup_restore_requests')->where('id', $this->restoreRequestId)->first();
            if (! $requestRow || in_array($requestRow->status, ['executed', 'failed', 'rejected', 'expired'], true)) {
                return;
            }
            if ($requestRow->status !== 'executing') {
                throw new \RuntimeException('Permintaan restore tidak lagi berada pada status executing.');
            }
            $approvals->assertExecutable($requestRow);

            $backup = DB::table('backups')
                ->where('id', $requestRow->backup_id)
                ->where('pesantren_id', $requestRow->pesantren_id)
                ->first();
            if (! $backup) {
                throw new \RuntimeException('Backup restore tidak ditemukan.');
            }
            if ($backup->status === 'restored') {
                return;
            }
            if ($backup->status !== 'restoring') {
                throw new \RuntimeException('Backup tidak lagi berada pada status restoring.');
            }

            $tenantUserIds = DB::table('pesantren_user')
                ->where('pesantren_id', $requestRow->pesantren_id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            try {
                $restoreService->restore($backup, function (int $restoredRows) use ($requestRow, $backup, $tenantUserIds, $accessRevoker) {
                    $backupUpdated = DB::table('backups')
                        ->where('id', $backup->id)
                        ->where('status', 'restoring')
                        ->update([
                            'status' => 'restored',
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);
                    $requestUpdated = DB::table('backup_restore_requests')
                        ->where('id', $requestRow->id)
                        ->where('status', 'executing')
                        ->update([
                            'status' => 'executed',
                            'executed_at' => now(),
                            'executed_by' => $this->actorUserId,
                            'result' => json_encode(['restored_rows' => $restoredRows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);

                    if ($backupUpdated !== 1 || $requestUpdated !== 1) {
                        throw new \RuntimeException('Status restore berubah sebelum penyelesaian dicatat.');
                    }

                    // Assignments and other authorization scopes are restored as
                    // business data. Revoke every tenant credential in the same
                    // transaction so no pre-restore session can retain stale scope.
                    $accessRevoker->revokeMany($tenantUserIds);

                    $this->audit(
                        (int) $requestRow->pesantren_id,
                        'Backup dipulihkan setelah persetujuan berlapis',
                        [
                            'restore_request_id' => (int) $requestRow->id,
                            'backup_id' => (int) $backup->id,
                            'restored_rows' => $restoredRows,
                        ],
                    );
                });
            } catch (\Throwable $exception) {
                DB::transaction(function () use ($requestRow, $backup, $exception) {
                    DB::table('backups')
                        ->where('id', $backup->id)
                        ->where('status', 'restoring')
                        ->update(['status' => 'restore_failed', 'updated_at' => now()]);
                    DB::table('backup_restore_requests')
                        ->where('id', $requestRow->id)
                        ->where('status', 'executing')
                        ->update([
                            'status' => 'failed',
                            'result' => json_encode([
                                'error' => class_basename($exception),
                                'message' => Str::limit($exception->getMessage(), 1000),
                            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);

                    $this->audit(
                        (int) $requestRow->pesantren_id,
                        'Restore backup gagal',
                        [
                            'restore_request_id' => (int) $requestRow->id,
                            'backup_id' => (int) $backup->id,
                            'error' => class_basename($exception),
                        ],
                    );
                }, 3);

                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $requestRow = DB::table('backup_restore_requests')->where('id', $this->restoreRequestId)->first();
        if (! $requestRow) {
            return;
        }

        DB::transaction(function () use ($requestRow, $exception) {
            $requestUpdated = DB::table('backup_restore_requests')
                ->where('id', $requestRow->id)
                ->where('status', 'executing')
                ->update([
                    'status' => 'failed',
                    'result' => json_encode([
                        'error' => $exception ? class_basename($exception) : 'QueueFailure',
                        'message' => $exception ? Str::limit($exception->getMessage(), 1000) : 'Restore gagal di worker antrean.',
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            DB::table('backups')
                ->where('id', $requestRow->backup_id)
                ->where('status', 'restoring')
                ->update(['status' => 'restore_failed', 'updated_at' => now()]);

            if ($requestUpdated === 1) {
                $this->audit(
                    (int) $requestRow->pesantren_id,
                    'Restore backup gagal di worker',
                    [
                        'restore_request_id' => (int) $requestRow->id,
                        'backup_id' => (int) $requestRow->backup_id,
                        'error' => $exception ? class_basename($exception) : 'QueueFailure',
                    ],
                );
            }
        }, 3);
    }

    private function audit(int $tenantId, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $this->actorUserId,
            'action' => $action,
            'module' => 'Backup Restore',
            'ip_address' => null,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
