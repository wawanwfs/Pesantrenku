<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupRestoreService;
use App\Services\MetricRegistry;
use App\Services\RestoreApprovalService;
use App\Support\DashboardModuleRegistry;
use App\Support\TenantBackupManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class Rc6HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_cannot_select_a_platform_telemetry_module(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();

        $this->actingAs($owner)
            ->get('/owner/module/login-monitoring')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/super-admin/module/login-monitoring')
            ->assertForbidden();
    }

    public function test_authorization_generation_revokes_an_existing_mobile_token(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $token = $this->postApiLogin([
            'login' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');
        $tokenId = (int) explode('|', $token, 2)[0];

        DB::table('users')->where('id', $user->id)->increment('authz_version');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_authorization_generation_revokes_same_origin_api_session(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();

        $this->actingAs($user);
        DB::table('users')->where('id', $user->id)->increment('authz_version');
        $user->refresh();

        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertGuest('web');
    }

    public function test_mobile_token_cannot_be_replayed_from_another_device(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $token = $this->postApiLogin([
            'login' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Device-Id' => 'different-device-0002',
        ])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_access_administration_requires_step_up_mfa(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');

        $this->actingAs($owner)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/permissions/custom-roles', [
                'name' => 'without_step_up',
                'label' => 'Without Step Up',
                'read' => ['students'],
                'write' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mfa_code']);

        $this->assertDatabaseMissing('tenant_roles', ['pesantren_id' => $tenantId, 'name' => 'without_step_up']);
    }

    public function test_step_up_cache_is_bound_to_the_requested_purpose(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');

        $payload = [
            'name' => 'purpose_bound_role',
            'label' => 'Purpose Bound Role',
            'read' => ['students'],
            'write' => [],
        ];

        $this->actingAsWithStepUp($owner, 'permission-matrix')
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/permissions/custom-roles', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mfa_code']);

        $this->assertDatabaseMissing('tenant_roles', [
            'pesantren_id' => $tenantId,
            'name' => 'purpose_bound_role',
        ]);

        $this->actingAsWithStepUp($owner, 'custom-role-create')
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/permissions/custom-roles', $payload)
            ->assertCreated();
    }

    public function test_privileged_custom_role_cannot_be_assigned_to_user_without_mfa(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $membershipId = (int) DB::table('pesantren_user')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $finance->id)
            ->value('id');

        $roleId = $this->actingAsWithStepUp($owner, 'custom-role-create')
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/permissions/custom-roles', [
                'name' => 'privileged_finance_operator',
                'label' => 'Privileged Finance Operator',
                'read' => ['payments'],
                'write' => ['payments'],
                'actions' => ['payments.verify'],
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAsWithStepUp($owner, 'custom-role-assignment')
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-api/permissions/custom-roles/{$roleId}/assign", [
                'membership_ids' => [$membershipId],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('pesantren_user', [
            'id' => $membershipId,
            'tenant_role_id' => null,
        ]);
    }

    public function test_restore_manifest_rejects_security_state_tables(): void
    {
        $this->seed();
        $tenantId = (int) DB::table('pesantrens')->value('id');
        $tables = [];
        $manifest = [];
        foreach (TenantBackupManifest::TABLES as $table) {
            $tables[$table] = [];
            $manifest[$table] = [
                'rows' => 0,
                'checksum_sha256' => TenantBackupManifest::checksum([]),
            ];
        }
        $tables['pesantren_user'] = [[
            'id' => 999999,
            'pesantren_id' => $tenantId,
            'user_id' => 1,
            'role_id' => 1,
        ]];
        $snapshot = [
            'version' => TenantBackupManifest::VERSION,
            'tenant_id' => $tenantId,
            'tenant' => ['id' => $tenantId],
            'manifest' => $manifest,
            'tables' => $tables,
        ];

        $service = app(BackupRestoreService::class);
        $method = (new ReflectionClass($service))->getMethod('validateSnapshot');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('pesantren_user');
        $method->invoke($service, $snapshot, $tenantId);
    }

    public function test_disabled_tenant_module_is_enforced_on_crud_requests(): void
    {
        $this->seed();
        config(['pesantrenku.entitlements.enforced' => true]);
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $finance->id)->value('pesantren_id');
        DB::table('tenant_modules')
            ->where('pesantren_id', $tenantId)
            ->where('module', 'keuangan')
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/web-api/payments')
            ->assertForbidden();
    }

    public function test_dashboard_registry_allows_every_published_role_menu_without_exposing_platform_telemetry(): void
    {
        $expected = [
            'akademik' => ['pengumuman'],
            'santri' => ['profil', 'pelanggaran', 'pengumuman', 'qr-scanner', 'notifikasi', 'pengaturan'],
            'wali-murid' => ['pelanggaran', 'notifikasi', 'profil'],
            'guru' => ['pengumuman', 'profil', 'pengaturan'],
            'musyrif' => ['pengumuman', 'pengaturan'],
            'klinik' => ['pengumuman', 'profil', 'pengaturan'],
        ];

        foreach ($expected as $panel => $modules) {
            foreach ($modules as $module) {
                $this->assertTrue(DashboardModuleRegistry::allows($panel, $module), "{$panel}/{$module} harus berada di registry.");
                $this->assertFalse(DashboardModuleRegistry::isPlatformDataset($module));
            }
        }
        $this->assertFalse(DashboardModuleRegistry::allows('owner', 'login-monitoring'));
        $this->assertTrue(DashboardModuleRegistry::isPlatformDataset('login-monitoring'));
    }

    public function test_expired_subscription_keeps_owner_control_plane_open_but_blocks_operational_dashboard(): void
    {
        $this->seed();
        config(['pesantrenku.entitlements.enforced' => true]);
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        DB::table('subscriptions')->where('pesantren_id', $tenantId)->update([
            'status' => 'expired',
            'ends_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->get('/owner/module/paket-dan-subscription')
            ->assertOk();

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->get('/dashboard/keuangan')
            ->assertStatus(402);
    }

    public function test_restore_approval_is_invalidated_when_backup_ciphertext_metadata_changes(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        foreach ([$owner, $superAdmin] as $user) {
            DB::table('mfa_recovery_codes')->insert([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', 'rc6-'.$user->id),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'Approval fingerprint test',
            'status' => 'success',
            'file_path' => 'backups/fingerprint-test.enc',
            'encryption_provider' => 'kms',
            'encryption_key_id' => 'kms-key-v1',
            'checksum_sha256' => str_repeat('a', 64),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service = app(RestoreApprovalService::class);
        $requestRow = $service->create(DB::table('backups')->find($backupId), $owner, 'owner_pesantren', 'RC6 fingerprint regression');
        DB::table('backups')->where('id', $backupId)->update([
            'checksum_sha256' => str_repeat('b', 64),
            'encryption_key_id' => 'kms-key-v2',
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata enkripsinya berubah');
        $service->approve($requestRow, $superAdmin, 'super_admin');
    }

    public function test_scanner_registration_is_rejected_outside_the_allowed_network(): void
    {
        $this->seed();
        config(['pesantrenku.qr.allowed_networks' => ['10.0.0.0/8']]);
        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $guru->id)->value('pesantren_id');
        $this->withToken($this->qrAccessToken($guru))
            ->withHeaders($this->qrScannerHeaders($tenantId))
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->postJson('/api/v1/qr/scanner/register', ['device_name' => 'Remote relay'])
            ->assertForbidden();
    }

    public function test_operational_drill_requires_the_same_downstream_correlation(): void
    {
        $this->app['env'] = 'production';
        config([
            'broadcasting.default' => 'log',
            'pesantrenku.observability.alertmanager_api_url' => 'http://alertmanager.test/api/v2/alerts',
            'pesantrenku.observability.loki_query_url' => 'http://loki.test/loki/api/v1/query_range',
            'pesantrenku.observability.drill_mail_to' => 'operations@pesantenku.test',
            'pesantrenku.observability.drill_confirmation_url' => 'https://receiver.test/drill',
            'pesantrenku.observability.drill_confirmation_token' => str_repeat('t', 40),
            'pesantrenku.observability.drill_timeout_seconds' => 15,
        ]);
        Mail::fake();
        Http::fake([
            'http://alertmanager.test/*' => Http::response([], 200),
            'http://loki.test/*' => Http::response(['status' => 'success', 'data' => ['result' => [['stream' => ['job' => 'docker'], 'values' => []]]]], 200),
            'https://receiver.test/*' => Http::response([
                'correlation' => 'rc6-operational-test',
                'alert_received' => true,
                'mail_received' => true,
                'reverb_received' => true,
            ], 200),
        ]);

        $status = Artisan::call('pesantenku:operational-drill', [
            '--correlation' => 'rc6-operational-test',
            '--timeout' => 15,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertNotNull(Cache::get('pesantenku:operational-drill:last-success'));
    }

    public function test_metric_exporter_is_single_leader_and_isolated_by_revision(): void
    {
        config([
            'pesantrenku.observability.metric_retention_days' => 14,
            'pesantrenku.observability.metric_exporter_lease_seconds' => 45,
            'pesantrenku.observability.metric_identity' => [
                'instance' => 'stable-a',
                'revision' => 'stable-rev',
                'track' => 'stable',
            ],
        ]);
        $stable = new MetricRegistry;
        $stable->increment('pesantenku_rc6_isolation_total');
        $stableOutput = $stable->exposition();
        $this->assertStringContainsString('pesantenku_metrics_exporter_leader{instance="stable-a",revision="stable-rev",track="stable"} 1', $stableOutput);
        $this->assertStringContainsString('pesantenku_rc6_isolation_total', $stableOutput);

        config(['pesantrenku.observability.metric_identity.instance' => 'stable-b']);
        $stableFollower = new MetricRegistry;
        $followerOutput = $stableFollower->exposition();
        $this->assertStringContainsString('pesantenku_metrics_exporter_leader{instance="stable-b",revision="stable-rev",track="stable"} 0', $followerOutput);
        $this->assertStringNotContainsString('pesantenku_rc6_isolation_total', $followerOutput);

        config([
            'pesantrenku.observability.metric_identity' => [
                'instance' => 'canary-a',
                'revision' => 'canary-rev',
                'track' => 'canary',
            ],
        ]);
        $canary = new MetricRegistry;
        $canary->increment('pesantenku_rc6_canary_total');
        $canaryOutput = $canary->exposition();
        $this->assertStringContainsString('pesantenku_rc6_canary_total', $canaryOutput);
        $this->assertStringNotContainsString('revision="stable-rev"', $canaryOutput);
    }
}
