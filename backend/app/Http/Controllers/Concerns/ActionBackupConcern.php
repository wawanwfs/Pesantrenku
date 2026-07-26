<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\CreateTenantBackupJob;
use App\Jobs\RestoreTenantBackupJob;
use App\Support\QueueNames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ActionBackupConcern
{
    public function runBackup(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        $tenantId = $isSuperAdmin
            ? $this->tenantContext->activePesantrenId($request)
            : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        abort_unless($tenantId, 422, 'Pilih tenant sebelum menjalankan backup. Backup platform penuh harus dilakukan oleh infrastruktur database.');

        try {
            $this->tenantBackup->assertWithinLimits($tenantId);
        } catch (\RuntimeException $exception) {
            abort(413, $exception->getMessage());
        }

        $backup = DB::transaction(function () use ($tenantId, $user, $request) {
            $tenant = DB::table('pesantrens')->where('id', $tenantId)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($tenant, 404);
            $active = DB::table('backups')
                ->where('pesantren_id', $tenantId)
                ->whereIn('status', ['queued', 'processing'])
                ->exists();
            abort_if($active, 409, 'Tenant ini sudah memiliki backup aktif.');

            $name = "tenant-{$tenantId}".'-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.backup.enc';
            $filePath = 'backups/'.$name;
            $id = DB::table('backups')->insertGetId([
                'pesantren_id' => $tenantId,
                'name' => $name,
                'status' => 'queued',
                'file_path' => $filePath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'pesantren_id' => $tenantId,
                'user_id' => $user->id,
                'action' => 'Backup tenant masuk antrean',
                'module' => 'Backup',
                'ip_address' => $request->ip(),
                'metadata' => json_encode([
                    'backup_id' => $id,
                    'name' => $name,
                    'disk' => $this->operationStorage->diskName(),
                    'correlation_id' => $request->attributes->get('correlation_id'),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) ['id' => $id, 'name' => $name];
        }, 3);

        try {
            CreateTenantBackupJob::dispatch($backup->id, $user->id)->onQueue(QueueNames::BACKUPS);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($backup, $tenantId, $user, $request, $exception) {
                DB::table('backups')->where('id', $backup->id)->where('status', 'queued')->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error' => Str::limit($exception->getMessage(), 2000),
                    'updated_at' => now(),
                ]);
                DB::table('audit_logs')->insert([
                    'pesantren_id' => $tenantId,
                    'user_id' => $user->id,
                    'action' => 'Backup tenant gagal masuk antrean',
                    'module' => 'Backup',
                    'ip_address' => $request->ip(),
                    'metadata' => json_encode([
                        'backup_id' => $backup->id,
                        'error' => class_basename($exception),
                        'correlation_id' => $request->attributes->get('correlation_id'),
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 3);
            throw $exception;
        }

        $id = $backup->id;

        return response()->json([
            'message' => 'Backup tenant masuk antrean.',
            'status' => 'queued',
            'backup_id' => $id,
        ], 202);
    }

    public function downloadBackup(Request $request, int $id)
    {
        $backup = $this->scopedBackup($request, $id);
        abort_unless(in_array($backup->status, ['success', 'restored', 'restore_failed'], true), 409, 'Backup belum selesai atau gagal dibuat.');
        abort_unless($backup->file_path && $this->operationStorage->exists($backup->file_path), 404, 'File backup tidak ditemukan.');

        if ($this->operationStorage->diskName() !== 'local' && $url = $this->operationStorage->temporaryUrl($backup->file_path, (int) config('pesantrenku.operations.signed_url_minutes', 10))) {
            return redirect()->away($url);
        }

        return $this->operationStorage->download($backup->file_path, $backup->name);
    }

    public function restoreBackup(Request $request, int $id)
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'in:RESTORE'],
            'mfa_code' => ['required', 'digits:6'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $backup = $this->scopedBackup($request, $id);
        abort_unless($backup->pesantren_id, 422, 'Restore saat ini hanya mendukung backup tenant.');
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $this->totp->verifyAndConsume($user, $validated['mfa_code'], 'backup-restore'), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        try {
            $restoreRequest = $this->restoreApprovals->create(
                $backup,
                $user,
                $this->tenantContext->isSuperAdmin($request) ? 'super_admin' : 'owner_pesantren',
                $validated['reason'],
            );
        } catch (\RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
        $this->auditRestore($request, 'Permintaan restore dibuat', $restoreRequest, ['backup_id' => $id]);

        return response()->json([
            'message' => 'Permintaan restore dibuat dan menunggu persetujuan berlapis.',
            'request' => $this->formatRestoreRequest($restoreRequest),
        ], 202);
    }

    public function restoreRequests(Request $request)
    {
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        $tenantId = $isSuperAdmin
            ? $this->tenantContext->activePesantrenId($request)
            : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        abort_unless($tenantId, 422, 'Pilih tenant sebelum melihat permintaan restore.');
        $rows = DB::table('backup_restore_requests as restore_requests')
            ->join('backups', 'backups.id', '=', 'restore_requests.backup_id')
            ->join('users', 'users.id', '=', 'restore_requests.requested_by')
            ->when($tenantId, fn ($query) => $query->where('restore_requests.pesantren_id', $tenantId))
            ->select('restore_requests.*', 'backups.name as backup_name', 'users.name as requester_name')
            ->latest('restore_requests.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->formatRestoreRequest($row));

        return response()->json(['data' => $rows]);
    }

    public function approveRestore(Request $request, int $id)
    {
        $validated = $request->validate(['mfa_code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user, 401);
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        abort_unless($this->totp->verifyAndConsume($user, $validated['mfa_code'], 'backup-restore'), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        $row = DB::table('backup_restore_requests')->find($id);
        abort_unless($row, 404);
        $tenantId = $isSuperAdmin
            ? $this->tenantContext->activePesantrenId($request)
            : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        abort_unless($tenantId, 422, 'Pilih tenant sebelum menyetujui restore.');
        abort_unless((int) $row->pesantren_id === (int) $tenantId, 404);
        try {
            $updated = $this->restoreApprovals->approve($row, $user, $isSuperAdmin ? 'super_admin' : 'owner_pesantren');
        } catch (\RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
        $this->auditRestore($request, 'Permintaan restore disetujui', $updated);

        return response()->json(['message' => 'Persetujuan restore dicatat.', 'request' => $this->formatRestoreRequest($updated)]);
    }

    public function executeRestore(Request $request, int $id)
    {
        $validated = $request->validate(['mfa_code' => ['required', 'digits:6']]);
        $user = $request->user()?->loadMissing('roles');
        abort_unless($user && $this->tenantContext->isSuperAdmin($request), 403, 'Eksekusi restore hanya dapat dilakukan super admin.');
        abort_unless($this->totp->verifyAndConsume($user, $validated['mfa_code'], 'backup-restore'), 422, 'Kode MFA tidak valid atau sudah kedaluwarsa.');
        $tenantId = $this->tenantContext->activePesantrenId($request);
        abort_unless($tenantId, 422, 'Pilih tenant sebelum mengeksekusi restore.');

        $claim = DB::transaction(function () use ($id, $tenantId, $request) {
            $row = DB::table('backup_restore_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless((int) $row->pesantren_id === (int) $tenantId, 404);
            if ($row->expires_at && now()->greaterThan($row->expires_at)) {
                DB::table('backup_restore_requests')->where('id', $id)->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

                return ['expired' => true];
            }
            abort_unless($row->status === 'approved', 409, 'Permintaan restore belum siap atau sudah diklaim proses lain.');
            try {
                $this->restoreApprovals->assertExecutable($row);
            } catch (\RuntimeException $exception) {
                abort(409, $exception->getMessage());
            }

            $backup = DB::table('backups')
                ->where('id', $row->backup_id)
                ->where('pesantren_id', $tenantId)
                ->lockForUpdate()
                ->first();
            abort_unless($backup && $backup->pesantren_id, 404);
            abort_unless(in_array($backup->status, ['success', 'restored', 'restore_failed'], true), 409, 'Backup sedang diproses atau tidak dapat dipulihkan.');

            DB::table('backup_restore_requests')->where('id', $id)->update(['status' => 'executing', 'updated_at' => now()]);
            DB::table('backups')->where('id', $backup->id)->update(['status' => 'restoring', 'updated_at' => now()]);
            $claimedRequest = DB::table('backup_restore_requests')->find($id);
            $claimedBackup = DB::table('backups')->find($backup->id);
            $this->auditRestore($request, 'Restore backup diklaim untuk antrean', $claimedRequest, [
                'backup_id' => (int) $claimedBackup->id,
            ]);

            return [
                'expired' => false,
                'request' => $claimedRequest,
                'backup' => $claimedBackup,
                'previous_backup_status' => $backup->status,
            ];
        }, 3);

        abort_if($claim['expired'], 422, 'Permintaan restore sudah kedaluwarsa.');

        try {
            RestoreTenantBackupJob::dispatch($id, $user->id)->onQueue(QueueNames::BACKUPS);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($id, $claim, $request, $exception) {
                DB::table('backup_restore_requests')->where('id', $id)->where('status', 'executing')->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);
                DB::table('backups')->where('id', $claim['backup']->id)->where('status', 'restoring')->update([
                    'status' => $claim['previous_backup_status'],
                    'updated_at' => now(),
                ]);
                $restoredRequest = DB::table('backup_restore_requests')->find($id);
                $this->auditRestore($request, 'Restore backup gagal masuk antrean', $restoredRequest, [
                    'backup_id' => (int) $claim['backup']->id,
                    'error' => class_basename($exception),
                ]);
            }, 3);
            throw $exception;
        }

        return response()->json([
            'message' => 'Restore tenant masuk antrean pemulihan.',
            'request' => $this->formatRestoreRequest($claim['request']),
        ], 202);
    }

    private function scopedBackup(Request $request, int $id): object
    {
        $isSuperAdmin = $this->assertOwnerOrSuper($request);
        $tenantId = $isSuperAdmin
            ? $this->tenantContext->activePesantrenId($request)
            : $this->tenantContext->activePesantrenId($request, 'owner_pesantren');
        abort_unless($tenantId, 422, 'Pilih tenant sebelum mengakses backup.');
        $backup = DB::table('backups')->where('id', $id)->where('pesantren_id', $tenantId)->first();
        abort_unless($backup, 404);

        return $backup;
    }

    private function formatRestoreRequest(object $row): array
    {
        return [
            'id' => $row->id,
            'backup_id' => $row->backup_id,
            'backup_name' => $row->backup_name ?? null,
            'pesantren_id' => $row->pesantren_id,
            'requested_by' => $row->requested_by,
            'requester_name' => $row->requester_name ?? null,
            'status' => $row->status,
            'reason' => $row->reason,
            'approvals' => json_decode((string) $row->approvals, true) ?: [],
            'expires_at' => $row->expires_at,
            'approved_at' => $row->approved_at,
            'executed_at' => $row->executed_at,
            'result' => is_string($row->result ?? null) ? (json_decode($row->result, true) ?: $row->result) : ($row->result ?? null),
            'created_at' => $row->created_at,
        ];
    }

    private function auditRestore(Request $request, string $action, object $restoreRequest, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => $restoreRequest->pesantren_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'Backup Restore Approval',
            'row_id' => $restoreRequest->id,
            'ip_address' => $request->ip(),
            'metadata' => json_encode($metadata + [
                'restore_request_id' => $restoreRequest->id,
                'status' => $restoreRequest->status,
                'approvals' => json_decode((string) $restoreRequest->approvals, true) ?: [],
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
