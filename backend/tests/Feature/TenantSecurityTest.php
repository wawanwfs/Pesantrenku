<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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

    public function test_owner_pesantren_resource_is_scoped_to_the_selected_active_tenant(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantIds = DB::table('pesantrens')->where('status', 'active')->orderBy('id')->pluck('id');
        $this->assertGreaterThanOrEqual(2, $tenantIds->count(), 'Seeder harus menyediakan dua tenant aktif.');
        $selectedTenant = (int) $tenantIds->first();
        $otherTenant = (int) $tenantIds->last();
        $ownerRole = DB::table('roles')->where('name', 'owner_pesantren')->value('id');

        DB::table('pesantren_user')->updateOrInsert([
            'pesantren_id' => $otherTenant,
            'user_id' => $owner->id,
        ], [
            'role_id' => $ownerRole,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', $selectedTenant)
            ->getJson('/web-api/pesantrens')
            ->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame($selectedTenant, (int) $response->json('data.0.id'));

        $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', $selectedTenant)
            ->getJson("/web-api/pesantrens/{$otherTenant}")
            ->assertNotFound();
    }

    public function test_inactive_tenant_cannot_be_selected_by_operational_users(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        DB::table('pesantrens')->where('id', $tenantId)->update(['status' => 'inactive']);

        $this->actingAsWithStepUp($owner)
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

        $membershipId = $this->actingAsWithStepUp($owner)
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

        $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', $tenantId)
            ->patchJson("/web-api/memberships/{$membershipId}", ['tenant_role_id' => $foreignTenantRole])
            ->assertUnprocessable();
    }

    public function test_deactivated_users_lose_existing_session_and_token_access(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $token = $this->postApiLogin([
            'login' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        DB::table('users')->where('id', $user->id)->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
        $this->actingAs($user->fresh())->get('/dashboard/keuangan')->assertRedirect('/login');
    }

    public function test_password_change_revokes_sessions_tokens_and_remember_login(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $token = $user->createToken('compromised-token');
        $sessionId = 'security-change-session-'.$user->id;

        DB::table('users')->where('id', $user->id)->update(['remember_token' => 'remember-me']);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TenantSecurityTest',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAsWithStepUp($superAdmin)
            ->patchJson("/web-api/users/{$user->id}", ['password' => 'NewSecurePassword123!'])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewSecurePassword123!', $fresh->password));
        $this->assertNull($fresh->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $this->seed();
        DB::table('users')->where('email', 'keuangan@pesantenku.test')->update(['deleted_at' => now()]);

        $this->postApiLogin([
            'login' => 'keuangan@pesantenku.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonMissingPath('token');
    }

    public function test_super_admin_must_select_an_active_tenant_for_every_tenant_bound_crud_path(): void
    {
        $this->seed();
        Queue::fake();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $student = DB::table('students')->firstOrFail();
        $operationCount = DB::table('operation_jobs')->count();
        $message = 'Pilih tenant aktif melalui header X-Pesantren-Id sebelum mengakses resource tenant.';

        $this->actingAsWithStepUp($superAdmin)->getJson('/web-api/students')
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $tenantId = DB::table('pesantrens')->where('status', 'active')->value('id');
        $this->actingAsWithStepUp($superAdmin)->getJson("/web-api/students?pesantren_id={$tenantId}")
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->getJson('/web-api/students/analytics')
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->get('/web-api/students/export')
            ->assertUnprocessable();
        $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/students/export/queue', ['format' => 'csv'])
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->getJson('/web-actions/jobs')
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->getJson("/web-api/students/{$student->id}")
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->getJson("/web-api/students/{$student->id}/timeline")
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/students', [
            'pesantren_id' => $tenantId,
            'class_id' => $student->class_id,
            'nis' => 'TENANT-CONTEXT-REQUIRED',
            'name' => 'Tenant Context Required',
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->patchJson("/web-api/students/{$student->id}", ['name' => 'Blocked'])
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->deleteJson("/web-api/students/{$student->id}")
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/students/bulk', [
            'action' => 'delete',
            'ids' => [$student->id],
        ])->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $payment = DB::table('payments')->where('status', 'pending')->firstOrFail();
        $this->actingAsWithStepUp($superAdmin)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->withHeader('Accept', 'application/json')->post('/web-api/students/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', "name,nis\nBlocked,TENANT-IMPORT-PREVIEW\n"),
        ])->assertUnprocessable()->assertJsonFragment(['message' => $message]);
        $this->actingAsWithStepUp($superAdmin)->withHeader('Accept', 'application/json')->post('/web-api/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', "name,nis\nBlocked,TENANT-IMPORT\n"),
            'queue' => true,
        ])->assertUnprocessable()->assertJsonFragment(['message' => $message]);

        $this->assertSame($operationCount, DB::table('operation_jobs')->count());
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => $student->name]);
        Queue::assertNothingPushed();

        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/web-api/students')
            ->assertOk();
    }

    public function test_workflow_rechecks_current_resource_permission_before_mutation(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $finance->id)->value('pesantren_id');
        $payment = DB::table('payments')->where('pesantren_id', $tenantId)->where('status', 'pending')->firstOrFail();

        DB::table('system_settings')->updateOrInsert(
            ['pesantren_id' => $tenantId, 'key' => 'permissions.matrix_override'],
            [
                'value' => json_encode([
                    'admin_keuangan' => [
                        'read' => ['payments', 'invoices'],
                        'write' => ['invoices'],
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_workflow_action_permission_can_be_revoked_without_revoking_resource_write(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $finance->id)->value('pesantren_id');
        $payment = DB::table('payments')->where('pesantren_id', $tenantId)->whereIn('status', ['pending', 'waiting'])->firstOrFail();

        DB::table('system_settings')->updateOrInsert(
            ['pesantren_id' => $tenantId, 'key' => 'permissions.matrix_override'],
            [
                'value' => json_encode([
                    'admin_keuangan' => [
                        'read' => ['payments', 'invoices'],
                        'write' => ['payments'],
                        'actions' => [],
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => $payment->status]);
    }

    public function test_custom_role_requires_and_can_receive_explicit_workflow_action_permission(): void
    {
        $this->seed();
        $academic = User::where('email', 'akademik@pesantenku.test')->firstOrFail();
        $membership = DB::table('pesantren_user')->where('user_id', $academic->id)->firstOrFail();
        $payment = DB::table('payments')
            ->where('pesantren_id', $membership->pesantren_id)
            ->whereIn('status', ['pending', 'waiting'])
            ->firstOrFail();
        $customRoleId = DB::table('tenant_roles')->insertGetId([
            'pesantren_id' => $membership->pesantren_id,
            'name' => 'payment_reviewer',
            'label' => 'Payment Reviewer',
            'description' => null,
            'permissions' => json_encode([
                'read' => ['payments', 'invoices'],
                'write' => ['payments'],
                'actions' => [],
            ], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pesantren_user')->where('id', $membership->id)->update([
            'tenant_role_id' => $customRoleId,
            'updated_at' => now(),
        ]);

        $this->actingAs($academic)
            ->withHeader('X-Pesantren-Id', (string) $membership->pesantren_id)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertForbidden();

        DB::table('tenant_roles')->where('id', $customRoleId)->update([
            'permissions' => json_encode([
                'read' => ['payments', 'invoices'],
                'write' => ['payments'],
                'actions' => ['payments.verify'],
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->actingAs($academic)
            ->withHeader('X-Pesantren-Id', (string) $membership->pesantren_id)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'success']);
    }

    public function test_totp_replay_is_rejected_across_security_purposes(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $totp = app(TotpService::class);
        $code = $totp->currentCode($superAdmin);

        $this->assertTrue($totp->verifyAndConsume($superAdmin, $code, 'login'));
        $this->assertFalse($totp->verifyAndConsume($superAdmin, $code, 'backup-restore'));
    }

    public function test_failed_login_is_audited_without_storing_the_raw_identifier(): void
    {
        $this->seed();
        $login = 'unknown-user@example.invalid';

        $this->postApiLogin([
            'login' => $login,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $audit = DB::table('audit_logs')->where('module', 'auth')->where('action', 'LOGIN_FAILED')->latest('id')->firstOrFail();
        $metadata = json_decode($audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invalid_credentials', $metadata['reason']);
        $this->assertArrayHasKey('login_hash', $metadata);
        $this->assertStringNotContainsString($login, $audit->metadata);
    }

    public function test_privileged_login_requires_mfa_and_rejects_replay_when_enforced(): void
    {
        $this->seed();
        config(['pesantrenku.auth.enforce_privileged_mfa' => true]);
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();

        $this->postApiLogin([
            'login' => $superAdmin->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonMissingPath('token');

        $code = app(TotpService::class)->currentCode($superAdmin);
        $this->postApiLogin([
            'login' => $superAdmin->email,
            'password' => 'password',
            'mfa_code' => $code,
        ])->assertOk()->assertJsonStructure(['token']);

        $this->postApiLogin([
            'login' => $superAdmin->email,
            'password' => 'password',
            'mfa_code' => $code,
        ])->assertUnprocessable()->assertJsonMissingPath('token');
    }
}
