<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_permissions_follow_the_active_membership_role(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $secondTenant = DB::table('pesantrens')->where('name', 'Pesantren Al-Falah')->value('id');
        $guardianRole = DB::table('roles')->where('name', 'wali_murid')->value('id');

        DB::table('pesantren_user')->insert([
            'pesantren_id' => $secondTenant,
            'user_id' => $user->id,
            'role_id' => $guardianRole,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withHeader('X-Pesantren-Id', $secondTenant)
            ->getJson('/web-api/bank-accounts')
            ->assertForbidden();
    }

    public function test_inactive_tenant_cannot_be_selected_by_operational_users(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        DB::table('pesantrens')->where('id', $tenantId)->update(['status' => 'inactive']);

        $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/dashboard/owner')
            ->assertForbidden();
    }

    public function test_foreign_keys_from_another_tenant_are_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        $otherTenant = DB::table('pesantrens')->where('name', 'Pesantren Al-Falah')->value('id');
        $studentId = DB::table('students')->where('pesantren_id', $tenantId)->value('id');
        $foreignInvoice = DB::table('invoices')->where('pesantren_id', $otherTenant)->value('id');
        $paymentMethod = DB::table('payment_methods')->where('pesantren_id', $tenantId)->value('id');

        $this->actingAs($user)
            ->withHeader('X-Pesantren-Id', $tenantId)
            ->postJson('/web-api/payments', [
                'invoice_id' => $foreignInvoice,
                'student_id' => $studentId,
                'payment_method_id' => $paymentMethod,
                'amount' => 100000,
                'paid_at' => now()->toDateString(),
                'status' => 'pending',
            ])
            ->assertUnprocessable();
    }

    public function test_non_owner_cannot_mutate_memberships(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $finance->id)->value('pesantren_id');
        $ownerMembership = DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('user_id', $owner->id)->value('id');

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', $tenantId)
            ->deleteJson("/web-api/memberships/{$ownerMembership}")
            ->assertForbidden();
        $this->assertDatabaseHas('pesantren_user', ['id' => $ownerMembership]);
    }

    public function test_owner_can_invite_new_user_but_cannot_assign_foreign_tenant_role(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $otherTenant = DB::table('pesantrens')->where('id', '!=', $tenantId)->value('id');
        $roleId = DB::table('roles')->where('name', 'wali_murid')->value('id');
        $newUser = User::factory()->create(['status' => 'active']);

        $membershipId = $this->actingAs($owner)
            ->withHeader('X-Pesantren-Id', $tenantId)
            ->postJson('/web-api/memberships', [
                'user_id' => $newUser->id,
                'role_id' => $roleId,
                'status' => 'active',
                'joined_at' => now()->toDateTimeString(),
            ])
            ->assertCreated()
            ->json('id');

        $foreignTenantRole = DB::table('tenant_roles')->insertGetId([
            'pesantren_id' => $otherTenant,
            'name' => 'foreign_role',
            'label' => 'Foreign Role',
            'description' => null,
            'permissions' => json_encode(['read' => ['payments'], 'write' => ['payments']]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withHeader('X-Pesantren-Id', $tenantId)
            ->patchJson("/web-api/memberships/{$membershipId}", ['tenant_role_id' => $foreignTenantRole])
            ->assertUnprocessable();
    }

    public function test_deactivated_users_lose_existing_session_and_token_access(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $this->actingAs($user->fresh())->get('/dashboard/keuangan')->assertRedirect('/login');
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $this->seed();
        DB::table('users')->where('email', 'keuangan@pesantenku.test')->update(['deleted_at' => now()]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'keuangan@pesantenku.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonMissingPath('token');
    }
}
