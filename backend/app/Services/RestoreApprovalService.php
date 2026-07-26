<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreApprovalService
{
    public function create(object $backup, User $user, string $role, string $reason): object
    {
        $this->assertApprovalRole($role);

        return DB::transaction(function () use ($backup, $user, $role, $reason) {
            $lockedBackup = DB::table('backups')
                ->where('id', $backup->id)
                ->where('pesantren_id', $backup->pesantren_id)
                ->lockForUpdate()
                ->first();
            if (! $lockedBackup) {
                throw new \RuntimeException('Backup tidak ditemukan pada tenant aktif.');
            }
            if (! in_array($lockedBackup->status, ['success', 'restored', 'restore_failed'], true)) {
                throw new \RuntimeException('Backup belum siap atau sedang diproses.');
            }
            $approvedChangeHash = $this->changeHash($lockedBackup);

            DB::table('backup_restore_requests')
                ->where('backup_id', $backup->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired', 'updated_at' => now()]);

            $activeRequest = DB::table('backup_restore_requests')
                ->where('backup_id', $backup->id)
                ->whereIn('status', ['pending', 'approved', 'executing'])
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();
            if ($activeRequest) {
                throw new \RuntimeException('Backup ini sudah memiliki permintaan restore aktif.');
            }

            $approvals = [[
                'role' => $role,
                'user_id' => $user->id,
                'approved_at' => now()->toIso8601String(),
                'authz_version' => (int) ($user->authz_version ?? 1),
                'change_hash' => $approvedChangeHash,
            ]];
            $status = $this->isApproved($approvals) ? 'approved' : 'pending';
            $id = DB::table('backup_restore_requests')->insertGetId([
                'backup_id' => $backup->id,
                'pesantren_id' => $backup->pesantren_id,
                'requested_by' => $user->id,
                'status' => $status,
                'approvals' => json_encode($approvals, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'approved_change_hash' => $approvedChangeHash,
                'reason' => $reason,
                'expires_at' => now()->addMinutes((int) config('backup-security.request_ttl_minutes', 60)),
                'approved_at' => $status === 'approved' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('backup_restore_requests')->find($id);
        }, 3);
    }

    public function approve(object $requestRow, User $user, string $role): object
    {
        $this->assertApprovalRole($role);

        $result = DB::transaction(function () use ($requestRow, $user, $role) {
            $current = DB::table('backup_restore_requests')->where('id', $requestRow->id)->lockForUpdate()->first();
            if (! $current) {
                throw new \RuntimeException('Permintaan restore tidak ditemukan.');
            }
            if ($current->expires_at && now()->greaterThan($current->expires_at)) {
                DB::table('backup_restore_requests')->where('id', $current->id)->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

                return null;
            }
            if (! in_array($current->status, ['pending', 'approved'], true)) {
                throw new \RuntimeException('Permintaan restore tidak dapat disetujui pada status ini.');
            }

            $backup = DB::table('backups')
                ->where('id', $current->backup_id)
                ->where('pesantren_id', $current->pesantren_id)
                ->lockForUpdate()
                ->first();
            $currentChangeHash = $backup ? $this->changeHash($backup) : '';
            if (! $backup || ! is_string($current->approved_change_hash) || ! hash_equals($current->approved_change_hash, $currentChangeHash)) {
                throw new \RuntimeException('Persetujuan restore gugur karena backup atau metadata enkripsinya berubah.');
            }

            $approvals = json_decode((string) $current->approvals, true) ?: [];
            if (! collect($approvals)->contains(fn ($approval) => (int) ($approval['user_id'] ?? 0) === (int) $user->id)) {
                $approvals[] = [
                    'role' => $role,
                    'user_id' => $user->id,
                    'approved_at' => now()->toIso8601String(),
                    'authz_version' => (int) ($user->authz_version ?? 1),
                    'change_hash' => $currentChangeHash,
                ];
            }
            $status = $this->isApproved($approvals) ? 'approved' : 'pending';
            DB::table('backup_restore_requests')->where('id', $current->id)->update([
                'approvals' => json_encode($approvals, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => $status,
                'approved_at' => $status === 'approved' ? now() : null,
                'updated_at' => now(),
            ]);

            return DB::table('backup_restore_requests')->find($current->id);
        }, 3);

        if (! $result) {
            throw new \RuntimeException('Permintaan restore sudah kedaluwarsa.');
        }

        return $result;
    }

    public function assertExecutable(object $requestRow): void
    {
        $backup = DB::table('backups')
            ->where('id', $requestRow->backup_id)
            ->where('pesantren_id', $requestRow->pesantren_id)
            ->first();
        $currentChangeHash = $backup ? $this->changeHash($backup) : '';
        if (! $backup || ! is_string($requestRow->approved_change_hash) || ! hash_equals($requestRow->approved_change_hash, $currentChangeHash)) {
            throw new \RuntimeException('Persetujuan restore gugur karena backup atau metadata enkripsinya berubah.');
        }

        $approvals = json_decode((string) $requestRow->approvals, true) ?: [];
        if (! $this->isApproved($approvals)) {
            throw new \RuntimeException('Quorum persetujuan restore tidak lagi valid.');
        }

        foreach ($approvals as $approval) {
            $userId = (int) ($approval['user_id'] ?? 0);
            $role = (string) ($approval['role'] ?? '');
            $version = (int) ($approval['authz_version'] ?? 0);
            $approvalChangeHash = (string) ($approval['change_hash'] ?? '');
            if ($approvalChangeHash === '' || ! hash_equals($currentChangeHash, $approvalChangeHash)) {
                throw new \RuntimeException('Persetujuan restore tidak terikat ke perubahan backup yang sedang dieksekusi.');
            }
            $user = User::query()->with('roles')->whereKey($userId)->where('status', 'active')->whereNull('deleted_at')->first();
            if (! $user || $version <= 0 || (int) ($user->authz_version ?? 1) !== $version) {
                throw new \RuntimeException('Persetujuan restore gugur karena otorisasi approver berubah.');
            }
            if (! $user->mfa_enabled || ! filled($user->mfa_secret) || ! DB::table('mfa_recovery_codes')->where('user_id', $userId)->whereNull('used_at')->exists()) {
                throw new \RuntimeException('Persetujuan restore gugur karena MFA approver tidak lagi siap.');
            }
            if ($role === 'super_admin') {
                if (! $user->hasRole('super_admin')) {
                    throw new \RuntimeException('Persetujuan super admin sudah tidak valid.');
                }
            } elseif ($role === 'owner_pesantren') {
                $owner = DB::table('pesantren_user')
                    ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
                    ->where('pesantren_user.pesantren_id', $requestRow->pesantren_id)
                    ->where('pesantren_user.user_id', $userId)
                    ->where('pesantren_user.status', 'active')
                    ->where('roles.name', 'owner_pesantren')
                    ->exists();
                if (! $owner) {
                    throw new \RuntimeException('Persetujuan owner tenant sudah tidak valid.');
                }
            } else {
                throw new \RuntimeException('Role persetujuan restore tidak dikenal.');
            }
        }
    }

    private function changeHash(object $backup): string
    {
        return hash('sha256', json_encode([
            'backup_id' => (int) $backup->id,
            'pesantren_id' => (int) $backup->pesantren_id,
            'file_path' => (string) $backup->file_path,
            'checksum_sha256' => (string) $backup->checksum_sha256,
            'encryption_provider' => (string) $backup->encryption_provider,
            'encryption_key_id' => (string) $backup->encryption_key_id,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function assertApprovalRole(string $role): void
    {
        if (! in_array($role, ['owner_pesantren', 'super_admin'], true)) {
            throw new \RuntimeException('Role persetujuan restore tidak valid.');
        }
    }

    private function isApproved(array $approvals): bool
    {
        $collection = collect($approvals);
        $roles = $collection->pluck('role')->unique();
        $approvers = $collection->pluck('user_id')->filter()->unique();
        $required = max(2, (int) config('backup-security.required_approvals', 2));

        return $roles->contains('owner_pesantren')
            && $roles->contains('super_admin')
            && $approvers->count() >= $required;
    }
}
