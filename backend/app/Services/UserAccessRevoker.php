<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserAccessRevoker
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * Increment the authorization generation and revoke every credential.
     * Any request that raced with deletion is rejected by generation checks.
     */
    public function revoke(User|int $user, bool $clearRememberToken = true): int
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;
        if ($userId <= 0) {
            return 0;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'authz_version')) {
            DB::table('users')->where('id', $userId)->increment('authz_version');
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }

        if ($clearRememberToken && Schema::hasTable('users')) {
            DB::table('users')->where('id', $userId)->update(['remember_token' => null]);
        }

        $this->tenantContext->invalidateMemberships($userId);

        return (int) (DB::table('users')->where('id', $userId)->value('authz_version') ?? 0);
    }

    public function revokeMany(iterable $userIds): void
    {
        collect($userIds)
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $id) => $this->revoke($id));
    }
}
