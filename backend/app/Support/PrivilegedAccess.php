<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single definition of "privileged account" for security-sensitive guards.
 *
 * Elevated access in this application can arrive through three different
 * paths, and a guard that inspects only some of them is bypassable:
 *
 * 1. a global platform role on the user (`role_user` + `roles`);
 * 2. a built-in tenant role on an active membership (`pesantren_user.role_id`);
 * 3. a tenant custom role (`pesantren_user.tenant_role_id` + `tenant_roles`)
 *    whose permission payload grants write access to sensitive resources.
 *
 * The third path is the one that used to be missed. A custom role holding
 * write access to users, roles, memberships, tenant settings, backups or
 * billing is privileged in practice, whatever it is named.
 */
final class PrivilegedAccess
{
    /**
     * Write access to any of these resources makes a role privileged.
     *
     * These are the resources that can be used to grant access, impersonate,
     * exfiltrate, or change the security posture of a tenant.
     */
    public const SENSITIVE_WRITE_RESOURCES = [
        'users',
        'roles',
        'memberships',
        'pesantrens',
        'system-settings',
        'backups',
        'subscriptions',
        'plans',
        'tenant-modules',
    ];

    public static function isPrivileged(User $user): bool
    {
        $roles = (array) config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);

        if ($user->hasRole($roles)) {
            return true;
        }

        $hasPrivilegedMembership = DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->whereIn('roles.name', $roles)
            ->exists();

        if ($hasPrivilegedMembership) {
            return true;
        }

        return self::hasPrivilegedCustomRole($user);
    }

    /**
     * Reasons the account is considered privileged, for audit metadata.
     *
     * @return array<int, string>
     */
    public static function reasons(User $user): array
    {
        $roles = (array) config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);
        $reasons = [];

        if ($user->hasRole($roles)) {
            $reasons[] = 'global_role';
        }

        $membershipRoles = DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->whereIn('roles.name', $roles)
            ->pluck('roles.name')
            ->unique()
            ->values()
            ->all();

        foreach ($membershipRoles as $role) {
            $reasons[] = 'tenant_role:'.$role;
        }

        foreach (self::privilegedCustomRoleIds($user) as $customRoleId) {
            $reasons[] = 'tenant_custom_role:'.$customRoleId;
        }

        return $reasons;
    }

    private static function hasPrivilegedCustomRole(User $user): bool
    {
        return self::privilegedCustomRoleIds($user) !== [];
    }

    /**
     * @return array<int, int>
     */
    private static function privilegedCustomRoleIds(User $user): array
    {
        if (! Schema::hasTable('tenant_roles')) {
            return [];
        }

        $customRoles = DB::table('pesantren_user')
            ->join('tenant_roles', function ($join) {
                $join->on('pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
                    ->on('pesantren_user.pesantren_id', '=', 'tenant_roles.pesantren_id');
            })
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('tenant_roles.status', 'active')
            ->select(['tenant_roles.id', 'tenant_roles.permissions'])
            ->get();

        $privileged = [];
        foreach ($customRoles as $customRole) {
            if (self::grantsSensitiveAccess($customRole->permissions ?? null)) {
                $privileged[] = (int) $customRole->id;
            }
        }

        return $privileged;
    }

    /**
     * Decide whether a custom role permission payload is privileged.
     *
     * An unreadable payload is treated as privileged. Failing closed keeps MFA
     * enabled on an account whose real authority we cannot determine, which is
     * the recoverable direction of the error.
     */
    private static function grantsSensitiveAccess(mixed $permissions): bool
    {
        $decoded = is_array($permissions)
            ? $permissions
            : json_decode((string) $permissions, true);

        if (! is_array($decoded)) {
            return true;
        }

        $write = array_map(
            static fn ($resource) => is_string($resource) ? $resource : '',
            (array) ($decoded['write'] ?? [])
        );

        if (array_intersect($write, self::SENSITIVE_WRITE_RESOURCES) !== []) {
            return true;
        }

        foreach ((array) ($decoded['actions'] ?? []) as $ability) {
            if (! is_string($ability)) {
                continue;
            }

            if (in_array(Str::beforeLast($ability, '.'), self::SENSITIVE_WRITE_RESOURCES, true)) {
                return true;
            }
        }

        return false;
    }
}
