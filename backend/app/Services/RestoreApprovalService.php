<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreApprovalService
{
    public function create(object $backup, User $user, string $reason): object
    {
        $role = $user->hasRole('super_admin') ? 'super_admin' : 'owner_pesantren';
        $approvals = [[
            'role' => $role,
            'user_id' => $user->id,
            'approved_at' => now()->toIso8601String(),
        ]];
        $status = $this->isApproved($approvals) ? 'approved' : 'pending';
        $id = DB::table('backup_restore_requests')->insertGetId([
            'backup_id' => $backup->id,
            'pesantren_id' => $backup->pesantren_id,
            'requested_by' => $user->id,
            'status' => $status,
            'approvals' => json_encode($approvals),
            'reason' => $reason,
            'expires_at' => now()->addMinutes((int) config('backup-security.request_ttl_minutes', 60)),
            'approved_at' => $status === 'approved' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('backup_restore_requests')->find($id);
    }

    public function approve(object $requestRow, User $user): object
    {
        if ($requestRow->expires_at && now()->greaterThan($requestRow->expires_at)) {
            DB::table('backup_restore_requests')->where('id', $requestRow->id)->update(['status' => 'expired', 'updated_at' => now()]);
            throw new \RuntimeException('Permintaan restore sudah kedaluwarsa.');
        }
        if (! in_array($requestRow->status, ['pending', 'approved'], true)) {
            throw new \RuntimeException('Permintaan restore tidak dapat disetujui pada status ini.');
        }
        $role = $user->hasRole('super_admin') ? 'super_admin' : 'owner_pesantren';
        $approvals = json_decode((string) $requestRow->approvals, true) ?: [];
        if (! collect($approvals)->contains(fn ($approval) => ($approval['role'] ?? null) === $role)) {
            $approvals[] = ['role' => $role, 'user_id' => $user->id, 'approved_at' => now()->toIso8601String()];
        }
        $status = $this->isApproved($approvals) ? 'approved' : 'pending';
        DB::table('backup_restore_requests')->where('id', $requestRow->id)->update([
            'approvals' => json_encode($approvals),
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
            'updated_at' => now(),
        ]);

        return DB::table('backup_restore_requests')->find($requestRow->id);
    }

    private function isApproved(array $approvals): bool
    {
        $roles = collect($approvals)->pluck('role')->unique();
        $required = max(2, (int) config('backup-security.required_approvals', 2));

        return $roles->contains('owner_pesantren')
            && $roles->contains('super_admin')
            && $roles->count() >= $required;
    }
}
