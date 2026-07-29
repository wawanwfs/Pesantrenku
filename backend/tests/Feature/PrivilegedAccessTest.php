<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PrivilegedAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the third privilege path that used to be missed by the MFA guard: a
 * tenant custom role whose permission payload grants write access to sensitive
 * resources. Such a role is privileged in practice, whatever it is named.
 *
 * Fixtures are resolved from the seeder by role name rather than by hardcoded
 * e-mail so the test does not break when seeded accounts are renamed.
 */
class PrivilegedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrative_account_without_sensitive_grants_is_not_privileged(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');

        $this->assertFalse(PrivilegedAccess::isPrivileged($user));
        $this->assertSame([], PrivilegedAccess::reasons($user));
    }

    public function test_owner_membership_is_privileged_and_reported(): void
    {
        $this->seed();
        $owner = $this->tenantUserWithRole('owner_pesantren');

        $this->assertTrue(PrivilegedAccess::isPrivileged($owner));

        $reasons = PrivilegedAccess::reasons($owner);
        $this->assertNotEmpty($reasons);
        $this->assertTrue(
            in_array('global_role', $reasons, true)
                || in_array('tenant_role:owner_pesantren', $reasons, true),
            'owner privilege must be reported through a global or tenant role reason',
        );
    }

    public function test_custom_role_with_sensitive_write_access_is_privileged(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $roleId = $this->attachCustomRole($user, 'operator-akses', [
            'read' => ['users'],
            'write' => ['users'],
            'actions' => [],
        ]);

        $this->assertTrue(PrivilegedAccess::isPrivileged($user->fresh()));
        $this->assertContains('tenant_custom_role:'.$roleId, PrivilegedAccess::reasons($user->fresh()));
    }

    public function test_custom_role_limited_to_operational_resources_is_not_privileged(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $this->attachCustomRole($user, 'operator-akademik', [
            'read' => ['students', 'classes'],
            'write' => ['students'],
            'actions' => ['students.export'],
        ]);

        $this->assertFalse(PrivilegedAccess::isPrivileged($user->fresh()));
    }

    public function test_sensitive_action_ability_alone_makes_a_custom_role_privileged(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $this->attachCustomRole($user, 'operator-backup', [
            'read' => [],
            'write' => [],
            'actions' => ['backups.restore'],
        ]);

        $this->assertTrue(
            PrivilegedAccess::isPrivileged($user->fresh()),
            'an action ability on a sensitive resource is elevated access even without write access',
        );
    }

    public function test_every_declared_sensitive_resource_elevates_a_custom_role(): void
    {
        $this->seed();

        foreach (PrivilegedAccess::SENSITIVE_WRITE_RESOURCES as $index => $resource) {
            $user = $this->tenantUserWithRole('admin_tu');
            $this->attachCustomRole($user, 'sensitive-'.$index, [
                'read' => [],
                'write' => [$resource],
                'actions' => [],
            ]);

            $this->assertTrue(
                PrivilegedAccess::isPrivileged($user->fresh()),
                "write access to {$resource} must be treated as privileged",
            );
        }
    }

    public function test_unreadable_permission_payload_fails_closed(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $roleId = $this->attachCustomRole($user, 'operator-rusak', ['write' => ['students']]);

        DB::table('tenant_roles')->where('id', $roleId)->update([
            'permissions' => 'bukan-json-yang-valid',
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            PrivilegedAccess::isPrivileged($user->fresh()),
            'an account whose real authority cannot be determined must stay protected',
        );
    }

    public function test_revoked_custom_role_is_ignored(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $roleId = $this->attachCustomRole($user, 'operator-dicabut', [
            'write' => ['users'],
        ]);

        DB::table('tenant_roles')->where('id', $roleId)->update([
            'status' => 'revoked',
            'updated_at' => now(),
        ]);

        $this->assertFalse(PrivilegedAccess::isPrivileged($user->fresh()));
    }

    public function test_inactive_membership_does_not_confer_privilege(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');
        $this->attachCustomRole($user, 'operator-nonaktif', [
            'write' => ['users'],
        ]);

        DB::table('pesantren_user')
            ->where('user_id', $user->id)
            ->update(['status' => 'inactive']);

        $this->assertFalse(PrivilegedAccess::isPrivileged($user->fresh()));
    }

    public function test_privileged_role_list_is_configurable(): void
    {
        $this->seed();
        $user = $this->tenantUserWithRole('admin_tu');

        $this->assertFalse(PrivilegedAccess::isPrivileged($user));

        config(['pesantrenku.auth.privileged_roles' => ['super_admin', 'owner_pesantren', 'admin_tu']]);

        $this->assertTrue(PrivilegedAccess::isPrivileged($user->fresh()));
        $this->assertContains('tenant_role:admin_tu', PrivilegedAccess::reasons($user->fresh()));
    }

    private function tenantUserWithRole(string $roleName): User
    {
        $userId = DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('roles.name', $roleName)
            ->where('pesantren_user.status', 'active')
            ->value('pesantren_user.user_id');

        $this->assertNotNull($userId, "seeder must provide an active membership with role {$roleName}");

        return User::findOrFail($userId);
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function attachCustomRole(User $user, string $name, array $permissions): int
    {
        $membership = DB::table('pesantren_user')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        $this->assertNotNull($membership, 'user must hold an active membership');

        $roleId = (int) DB::table('tenant_roles')->insertGetId([
            'pesantren_id' => $membership->pesantren_id,
            'name' => $name,
            'label' => 'Peran Uji '.$name,
            'description' => 'Dibuat oleh PrivilegedAccessTest.',
            'permissions' => json_encode($permissions),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pesantren_user')
            ->where('user_id', $user->id)
            ->where('pesantren_id', $membership->pesantren_id)
            ->update(['tenant_role_id' => $roleId]);

        return $roleId;
    }
}
