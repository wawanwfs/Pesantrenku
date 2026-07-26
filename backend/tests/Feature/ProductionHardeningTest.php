<?php

namespace Tests\Feature;

use App\Jobs\BuildResourceExportJob;
use App\Jobs\RestoreTenantBackupJob;
use App\Models\User;
use App\Services\BackupCipher;
use App\Services\MetricRegistry;
use App\Services\OperationJobRecoveryService;
use App\Services\RestoreApprovalService;
use App\Services\TotpService;
use App\Support\QueueNames;
use App\Support\SafeSpreadsheet;
use App\Support\SignedImportRow;
use App\Support\TenantBackupManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_role_does_not_leak_into_another_tenant_workflow(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $otherTenant = DB::table('pesantrens')->where('name', 'Pesantren Al-Falah')->value('id');
        $guardianRole = DB::table('roles')->where('name', 'wali_murid')->value('id');

        DB::table('pesantren_user')->insert([
            'pesantren_id' => $otherTenant,
            'user_id' => $finance->id,
            'role_id' => $guardianRole,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = DB::table('payments')->where('pesantren_id', $otherTenant)->whereIn('status', ['pending', 'waiting'])->first();
        $this->assertNotNull($payment, 'Seeder harus menyediakan pembayaran pending pada tenant kedua.');

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $otherTenant)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertForbidden();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => $payment->status]);
    }

    public function test_control_resources_cannot_be_delegated_or_mutated_generically(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $auditId = DB::table('audit_logs')->value('id');
        $this->assertNotNull($auditId, 'Seeder harus menyediakan audit log.');

        $this->actingAsWithStepUp($owner, 'custom-role-create')->postJson('/web-api/permissions/custom-roles', [
            'name' => 'unsafe_auditor',
            'label' => 'Unsafe Auditor',
            'read' => ['audit-logs'],
            'write' => ['backups'],
        ])->assertUnprocessable();

        $this->actingAsWithStepUp($superAdmin)
            ->patchJson("/web-api/audit-logs/{$auditId}", ['action' => 'tampered'])
            ->assertStatus(405);
        $this->actingAsWithStepUp($superAdmin)
            ->deleteJson("/web-api/audit-logs/{$auditId}")
            ->assertStatus(405);
    }

    public function test_workflow_status_cannot_be_changed_through_generic_or_bulk_crud(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $payment = DB::table('payments')->whereIn('status', ['pending', 'waiting'])->firstOrFail();

        $this->actingAs($finance)
            ->patchJson("/web-api/payments/{$payment->id}", ['status' => 'success'])
            ->assertUnprocessable();

        $this->actingAs($finance)
            ->postJson('/web-api/payments/bulk', [
                'action' => 'status',
                'status' => 'success',
                'ids' => [$payment->id],
            ])
            ->assertStatus(405);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => $payment->status]);
    }

    public function test_processed_workflow_records_are_immutable_through_generic_crud(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $payment = DB::table('payments')->whereIn('status', ['pending', 'waiting'])->firstOrFail();
        DB::table('payments')->where('id', $payment->id)->update(['status' => 'success']);

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $payment->pesantren_id)
            ->patchJson("/web-api/payments/{$payment->id}", ['amount' => ((float) $payment->amount) + 1])
            ->assertConflict();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => $payment->amount, 'status' => 'success']);
    }

    public function test_platform_accounts_and_tenants_reject_bulk_lifecycle_mutations(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $userId = User::where('email', 'owner@pesantenku.test')->value('id');
        $tenantId = DB::table('pesantrens')->value('id');

        $this->actingAsWithStepUp($superAdmin)
            ->postJson('/web-api/users/bulk', ['action' => 'delete', 'ids' => [$userId]])
            ->assertStatus(405);

        $this->actingAsWithStepUp($superAdmin)
            ->postJson('/web-api/pesantrens/bulk', ['action' => 'delete', 'ids' => [$tenantId]])
            ->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $userId]);
        $this->assertDatabaseHas('pesantrens', ['id' => $tenantId]);
    }

    public function test_owner_cannot_disable_tenant_or_remove_the_last_owner(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $membershipId = DB::table('pesantren_user')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->where('pesantren_user.pesantren_id', $tenantId)
            ->where('pesantren_user.user_id', $owner->id)
            ->where('roles.name', 'owner_pesantren')
            ->value('pesantren_user.id');

        $this->actingAsWithStepUp($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->patchJson("/web-api/pesantrens/{$tenantId}", ['status' => 'inactive'])
            ->assertUnprocessable();

        $this->actingAsWithStepUp($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->deleteJson("/web-api/memberships/{$membershipId}")
            ->assertUnprocessable();
    }

    public function test_qr_requires_registered_mobile_scanner_and_is_single_use(): void
    {
        $this->seed();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $santri->id)->value('pesantren_id');

        $code = $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/qr/generate', ['purpose' => 'attendance'])
            ->assertCreated()
            ->json('code');

        $this->assertDatabaseHas('qr_tokens', [
            'code' => hash('sha256', $code),
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('qr_tokens', ['code' => $code]);
        $this->actingAs($guru)->postJson('/web-actions/qr/scan', ['code' => $code])->assertNotFound();

        $registration = $this->registerQrScanner($guru, $tenantId);
        $challenge = $this->requestQrChallenge($guru, $tenantId, $code);
        $this->submitQrScan($guru, $tenantId, $code, $registration, $challenge)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('side_effect.type', 'attendance_recorded');
        $this->submitQrScan($guru, $tenantId, $code, $registration, $challenge)->assertConflict();

        $this->assertDatabaseHas('qr_tokens', ['code' => hash('sha256', $code), 'status' => 'used']);
        $this->assertDatabaseHas('attendances', ['session' => 'qr', 'status' => 'hadir']);
    }

    public function test_teacher_cannot_scan_attendance_qr_outside_an_effective_assignment(): void
    {
        $this->seed();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $santri->id)->value('pesantren_id');
        $student = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $santri->id)
            ->firstOrFail();
        $teacherIds = DB::table('teachers')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $guru->id)
            ->pluck('id');
        DB::table('teacher_assignments')
            ->where('pesantren_id', $tenantId)
            ->whereIn('teacher_id', $teacherIds)
            ->update(['status' => 'active', 'ended_at' => today()->subDay()]);
        DB::table('attendances')
            ->where('pesantren_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('date', today()->toDateString())
            ->where('session', 'qr')
            ->delete();

        $code = $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/qr/generate', ['purpose' => 'attendance'])
            ->assertCreated()
            ->json('code');

        $this->registerQrScanner($guru, (int) $tenantId);
        $this->withToken($this->qrAccessToken($guru))
            ->withHeaders($this->qrScannerHeaders((int) $tenantId))
            ->postJson('/api/v1/qr/scan/challenge', ['code' => $code])
            ->assertForbidden();

        $this->assertDatabaseHas('qr_tokens', ['code' => hash('sha256', $code), 'status' => 'active']);
        $this->assertDatabaseMissing('attendances', [
            'pesantren_id' => $tenantId,
            'student_id' => $student->id,
            'date' => today()->toDateString(),
            'session' => 'qr',
        ]);
    }

    public function test_queued_export_preserves_santri_personal_scope(): void
    {
        $this->seed();
        Storage::fake('local');
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $santri->id)->value('pesantren_id');
        $ownStudent = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $santri->id)
            ->firstOrFail();
        DB::table('students')->insert([
            'pesantren_id' => $tenantId,
            'class_id' => $ownStudent->class_id,
            'nis' => 'PRIVATE-ASYNC-EXPORT',
            'name' => 'Santri Rahasia Tenant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uuid = $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/students/export/queue', ['format' => 'csv'])
            ->assertAccepted()
            ->json('job.uuid');

        $job = DB::table('operation_jobs')->where('uuid', $uuid)->firstOrFail();
        $contents = Storage::disk('local')->get($job->output_path);
        $this->assertStringContainsString($ownStudent->name, $contents);
        $this->assertStringNotContainsString('Santri Rahasia Tenant', $contents);
        $this->assertSame(1, (int) $job->processed_rows);
    }

    public function test_queued_export_fails_without_artifact_when_membership_is_revoked_before_processing(): void
    {
        $this->seed();
        Storage::fake('local');
        Queue::fake();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $membership = DB::table('pesantren_user')
            ->where('user_id', $santri->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        $uuid = $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $membership->pesantren_id)
            ->postJson('/web-api/students/export/queue', ['format' => 'csv'])
            ->assertAccepted()
            ->json('job.uuid');

        $queuedExport = null;
        Queue::assertPushed(BuildResourceExportJob::class, function (BuildResourceExportJob $job) use (&$queuedExport) {
            $queuedExport = $job;

            return true;
        });
        $this->assertNotNull($queuedExport);
        DB::table('pesantren_user')->where('id', $membership->id)->update(['status' => 'inactive']);

        try {
            app()->call([$queuedExport, 'handle']);
            $this->fail('Export harus ditolak ketika membership actor sudah dicabut.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Membership atau custom role actor operasi antrean sudah dicabut', $exception->getMessage());
            $queuedExport->failed($exception);
        }

        $job = DB::table('operation_jobs')->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('failed', $job->status);
        $this->assertNull($job->output_path);
        $this->assertNull($job->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
    }

    public function test_job_status_never_exposes_signed_url_and_download_reauthorizes_permission_and_entitlement(): void
    {
        $this->seed();
        config(['pesantrenku.entitlements.enforced' => true]);
        Storage::fake('local');
        Queue::fake();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')
            ->where('user_id', $finance->id)
            ->where('status', 'active')
            ->value('pesantren_id');

        $uuid = $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/payments/export/queue', ['format' => 'csv'])
            ->assertAccepted()
            ->json('job.uuid');

        $queuedExport = null;
        Queue::assertPushed(BuildResourceExportJob::class, function (BuildResourceExportJob $job) use (&$queuedExport) {
            $queuedExport = $job;

            return true;
        });
        $this->assertNotNull($queuedExport);

        DB::table('system_settings')->updateOrInsert([
            'pesantren_id' => $tenantId,
            'key' => 'permissions.matrix_override',
        ], [
            'value' => json_encode([
                'admin_keuangan' => ['read' => [], 'write' => [], 'actions' => []],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app()->call([$queuedExport, 'handle']);
            $this->fail('Export harus ditolak ketika permission resource dicabut sebelum worker berjalan.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Permission payments.read actor operasi antrean sudah dicabut', $exception->getMessage());
            $queuedExport->failed($exception);
        }

        $this->assertDatabaseHas('operation_jobs', ['uuid' => $uuid, 'status' => 'failed']);
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));

        DB::table('system_settings')
            ->where('pesantren_id', $tenantId)
            ->where('key', 'permissions.matrix_override')
            ->delete();
        Queue::fake();

        $allowedUuid = $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/payments/export/queue', ['format' => 'csv'])
            ->assertAccepted()
            ->json('job.uuid');
        $allowedExport = null;
        Queue::assertPushed(BuildResourceExportJob::class, function (BuildResourceExportJob $job) use (&$allowedExport) {
            $allowedExport = $job;

            return true;
        });
        $this->assertNotNull($allowedExport);
        app()->call([$allowedExport, 'handle']);
        $this->assertDatabaseHas('operation_jobs', ['uuid' => $allowedUuid, 'status' => 'completed']);

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson("/web-actions/jobs/{$allowedUuid}")
            ->assertOk()
            ->assertJsonPath('download_url', "/web-actions/jobs/{$allowedUuid}/download")
            ->assertJsonMissingPath('signed_url');
        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/web-actions/jobs')
            ->assertOk()
            ->assertJsonMissingPath('data.0.signed_url');

        DB::table('tenant_modules')
            ->where('pesantren_id', $tenantId)
            ->where('module', 'keuangan')
            ->update(['is_enabled' => false, 'updated_at' => now()]);

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->get("/web-actions/jobs/{$allowedUuid}/download")
            ->assertForbidden();

        DB::table('tenant_modules')
            ->where('pesantren_id', $tenantId)
            ->where('module', 'keuangan')
            ->update(['is_enabled' => true, 'updated_at' => now()]);

        DB::table('system_settings')->updateOrInsert([
            'pesantren_id' => $tenantId,
            'key' => 'permissions.matrix_override',
        ], [
            'value' => json_encode([
                'admin_keuangan' => ['read' => [], 'write' => [], 'actions' => []],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson("/web-actions/jobs/{$allowedUuid}")
            ->assertOk()
            ->assertJsonPath('download_url', null)
            ->assertJsonMissingPath('signed_url');

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->get("/web-actions/jobs/{$allowedUuid}/download")
            ->assertForbidden();
    }

    public function test_exports_neutralize_spreadsheet_formulas(): void
    {
        $this->assertSame("\"'=2+2\"", SafeSpreadsheet::csvCell('=2+2'));
        $this->assertStringContainsString('ss:Type="String"', SafeSpreadsheet::xmlRow(['=2+2']));
        $this->assertStringNotContainsString('<Data ss:Type="Formula">', SafeSpreadsheet::xmlRow(['=2+2']));
    }

    public function test_queued_import_rows_are_signed_and_tamper_evident(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $encoded = SignedImportRow::encode(['name' => 'Ali', 'amount' => 1250.50]);

        $this->assertSame(['name' => 'Ali', 'amount' => 1250.50], SignedImportRow::decode($encoded));

        $envelope = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $envelope['data']['name'] = 'Budi';
        $tampered = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Integritas file import antrean gagal diverifikasi');
        SignedImportRow::decode($tampered);
    }

    public function test_backup_cipher_rejects_plaintext_and_metadata_mismatch(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'backup-security.provider' => 'local',
        ]);
        $cipher = app(BackupCipher::class);
        $encrypted = $cipher->encrypt('{"tenant_id":1}');

        $this->assertSame(
            '{"tenant_id":1}',
            $cipher->decrypt($encrypted['contents'], $encrypted['provider'], $encrypted['key_id'])
        );

        try {
            $cipher->decrypt('{"tenant_id":1}');
            $this->fail('Plaintext backup seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Envelope backup', $exception->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Penyedia kunci backup tidak cocok');
        $cipher->decrypt($encrypted['contents'], 'kms', $encrypted['key_id']);
    }

    public function test_tenant_backup_manifest_excludes_platform_entitlements(): void
    {
        $this->assertSame(4, TenantBackupManifest::VERSION);
        $this->assertNotContains('subscriptions', TenantBackupManifest::TABLES);
        $this->assertNotContains('tenant_modules', TenantBackupManifest::TABLES);
    }

    public function test_unknown_or_internal_fields_are_rejected_instead_of_silently_written(): void
    {
        $this->seed();
        $academic = User::where('email', 'akademik@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $academic->id)->value('pesantren_id');
        $student = DB::table('students')->where('pesantren_id', $tenantId)->firstOrFail();

        $this->actingAs($academic)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->patchJson("/web-api/students/{$student->id}", [
                'name' => 'Nama Valid',
                'deleted_at' => now()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deleted_at']);

        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => $student->name]);
    }

    public function test_idempotency_keys_are_replayed_per_tenant_not_globally(): void
    {
        $this->seed();
        config(['pesantrenku.security.require_idempotency_key' => true]);

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantIds = DB::table('pesantren_user')
            ->where('user_id', $owner->id)
            ->orderBy('pesantren_id')
            ->pluck('pesantren_id');
        $this->assertCount(2, $tenantIds);
        $key = 'tenant-scope-regression-0001';

        $firstResponse = $this->actingAsWithStepUp($owner)
            ->withHeaders(['X-Pesantren-Id' => (string) $tenantIds[0], 'Idempotency-Key' => $key])
            ->postJson('/web-actions/qr/generate', ['purpose' => 'identity'])
            ->assertCreated();

        $this->actingAsWithStepUp($owner)
            ->withHeaders(['X-Pesantren-Id' => (string) $tenantIds[0], 'Idempotency-Key' => $key])
            ->postJson('/web-actions/qr/generate', ['purpose' => 'identity'])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJson(['code' => $firstResponse->json('code')]);

        $this->actingAsWithStepUp($owner)
            ->withHeaders(['X-Pesantren-Id' => (string) $tenantIds[1], 'Idempotency-Key' => $key])
            ->postJson('/web-actions/qr/generate', ['purpose' => 'identity'])
            ->assertCreated();

        $this->assertSame(1, DB::table('qr_tokens')->where('pesantren_id', $tenantIds[0])->where('user_id', $owner->id)->count());
        $this->assertSame(1, DB::table('qr_tokens')->where('pesantren_id', $tenantIds[1])->where('user_id', $owner->id)->count());
    }

    public function test_explicit_tenant_header_has_precedence_over_query_and_session(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantIds = DB::table('pesantren_user')
            ->where('user_id', $owner->id)
            ->orderBy('pesantren_id')
            ->pluck('pesantren_id');

        $this->actingAsWithStepUp($owner)
            ->withSession(['active_pesantren_id' => $tenantIds[1]])
            ->withHeader('X-Pesantren-Id', (string) $tenantIds[0])
            ->postJson('/web-actions/qr/generate?pesantren_id='.$tenantIds[1], ['purpose' => 'identity'])
            ->assertCreated();

        $this->assertDatabaseHas('qr_tokens', ['pesantren_id' => $tenantIds[0], 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('qr_tokens', ['pesantren_id' => $tenantIds[1], 'user_id' => $owner->id]);
    }

    public function test_malformed_tenant_header_is_rejected_instead_of_falling_back_to_first_membership(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();

        $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', 'not-a-tenant-id')
            ->getJson('/web-api/students')
            ->assertUnprocessable();
    }

    public function test_api_tenant_switch_uses_new_tenant_even_when_header_contains_previous_tenant(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantIds = DB::table('pesantren_user')
            ->where('user_id', $owner->id)
            ->orderBy('pesantren_id')
            ->pluck('pesantren_id');
        $token = $this->postApiLogin([
            'login' => $owner->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->withHeader('X-Pesantren-Id', (string) $tenantIds[0])
            ->postJson('/api/v1/tenant/switch', ['pesantren_id' => $tenantIds[1]])
            ->assertOk()
            ->assertJsonPath('active_pesantren.id', $tenantIds[1]);
    }

    public function test_global_permission_matrix_is_read_only_until_superadmin_selects_tenant(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();

        $this->actingAsWithStepUp($superAdmin, 'permission-matrix')
            ->getJson('/web-api/permissions/matrix')
            ->assertOk()
            ->assertJson(['source' => 'global', 'editable' => false, 'tenant_id' => null]);

        $this->actingAsWithStepUp($superAdmin, 'permission-matrix')
            ->withHeader('Idempotency-Key', 'global-permission-readonly-0001')
            ->patchJson('/web-api/permissions/matrix', [
                'role' => 'guru',
                'read' => ['students'],
                'write' => [],
            ])
            ->assertUnprocessable();
    }

    public function test_new_payment_cannot_start_in_a_final_status(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $finance->id)->value('pesantren_id');
        $invoice = DB::table('invoices')->where('pesantren_id', $tenantId)->firstOrFail();

        $this->actingAs($finance)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/payments', [
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'amount' => 1,
                'status' => 'paid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_custom_role_assignment_rejects_memberships_from_another_tenant(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $memberships = DB::table('pesantren_user')
            ->where('user_id', $owner->id)
            ->orderBy('pesantren_id')
            ->get();
        $this->assertCount(2, $memberships);

        $roleId = $this->actingAsWithStepUp($owner, 'custom-role-create')
            ->withHeader('X-Pesantren-Id', (string) $memberships[0]->pesantren_id)
            ->postJson('/web-api/permissions/custom-roles', [
                'name' => 'tenant_reporter',
                'label' => 'Tenant Reporter',
                'read' => ['students'],
                'write' => [],
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAsWithStepUp($owner, 'custom-role-assignment')
            ->withHeader('X-Pesantren-Id', (string) $memberships[0]->pesantren_id)
            ->postJson("/web-api/permissions/custom-roles/{$roleId}/assign", [
                'membership_ids' => [$memberships[1]->id],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('pesantren_user', [
            'id' => $memberships[1]->id,
            'tenant_role_id' => null,
        ]);
    }

    public function test_custom_role_from_a_secondary_membership_cannot_override_the_active_membership(): void
    {
        $this->seed();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')
            ->where('user_id', $santri->id)
            ->orderBy('id')
            ->value('pesantren_id');
        $activeMembership = DB::table('pesantren_user')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $santri->id)
            ->orderBy('id')
            ->firstOrFail();
        $this->assertSame('santri', DB::table('roles')->where('id', $activeMembership->role_id)->value('name'));
        $waliRoleId = DB::table('roles')->where('name', 'wali_murid')->value('id');
        $customRoleId = DB::table('tenant_roles')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'secondary_membership_writer',
            'label' => 'Secondary Membership Writer',
            'permissions' => json_encode(['read' => ['students'], 'write' => ['students']], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pesantren_user')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $santri->id,
            'role_id' => $waliRoleId,
            'tenant_role_id' => $customRoleId,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $student = DB::table('students')
            ->where('pesantren_id', $tenantId)
            ->where('user_id', $santri->id)
            ->firstOrFail();

        $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->patchJson("/web-api/students/{$student->id}", ['name' => 'Escalated'])
            ->assertForbidden();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => $student->name]);
    }

    public function test_inactive_tenant_cannot_be_selected_as_an_operational_context(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->value('id');
        DB::table('pesantrens')->where('id', $tenantId)->update(['status' => 'inactive']);

        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/qr/generate', ['purpose' => 'identity'])
            ->assertNotFound();
    }

    public function test_tenant_cannot_queue_a_second_backup_while_one_is_active(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        DB::table('backups')->insert([
            'pesantren_id' => $tenantId,
            'name' => 'already-active.backup.enc',
            'status' => 'processing',
            'file_path' => 'backups/already-active.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/backup/run')
            ->assertConflict();

        $this->assertSame(1, DB::table('backups')->where('pesantren_id', $tenantId)->count());
    }

    public function test_application_backup_refuses_unbounded_tenant_snapshots(): void
    {
        $this->seed();
        config(['pesantrenku.backup.max_rows' => 1]);
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');

        Storage::fake('local');
        Queue::fake();
        $this->actingAsWithStepUp($owner)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/backup/run')
            ->assertStatus(413);

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('backups', [
            'pesantren_id' => $tenantId,
            'status' => 'queued',
        ]);
    }

    public function test_seed_data_passes_domain_integrity_preflight(): void
    {
        $this->seed();
        $this->artisan('pesantenku:domain-preflight')->assertSuccessful();
    }

    public function test_tenant_users_cannot_read_other_users_notification_logs(): void
    {
        $this->seed();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $other = User::where('email', 'wali@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $santri->id)->value('pesantren_id');

        $ownId = DB::table('notification_logs')->insertGetId([
            'pesantren_id' => $tenantId,
            'user_id' => $santri->id,
            'title' => 'Notifikasi milik santri',
            'body' => 'own',
            'type' => 'info',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherId = DB::table('notification_logs')->insertGetId([
            'pesantren_id' => $tenantId,
            'user_id' => $other->id,
            'title' => 'Notifikasi pengguna lain',
            'body' => 'private',
            'type' => 'info',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($santri)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->getJson('/web-api/notifications?per_page=100')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownId));
        $this->assertFalse($ids->contains($otherId));
    }

    public function test_broadcast_notification_read_state_is_isolated_per_user(): void
    {
        $this->seed();
        $first = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $second = User::where('email', 'wali@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')
            ->where('user_id', $first->id)
            ->where('status', 'active')
            ->value('pesantren_id');
        $this->assertDatabaseHas('pesantren_user', [
            'pesantren_id' => $tenantId,
            'user_id' => $second->id,
            'status' => 'active',
        ]);

        $notificationId = DB::table('notification_logs')->insertGetId([
            'pesantren_id' => $tenantId,
            'user_id' => null,
            'title' => 'Pengumuman bersama',
            'body' => 'Status baca setiap pengguna harus terpisah.',
            'type' => 'info',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($first)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/notifications/read', ['ids' => [$notificationId]])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertNull(DB::table('notification_logs')->where('id', $notificationId)->value('read_at'));
        $this->assertDatabaseHas('notification_receipts', [
            'notification_id' => $notificationId,
            'user_id' => $first->id,
        ]);
        $this->assertDatabaseMissing('notification_receipts', [
            'notification_id' => $notificationId,
            'user_id' => $second->id,
        ]);

        $firstPayload = $this->actingAs($first)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/web-actions/notifications')
            ->assertOk()
            ->json('data');
        $secondPayload = $this->actingAs($second)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/web-actions/notifications')
            ->assertOk()
            ->json('data');

        $this->assertNotNull(collect($firstPayload)->firstWhere('id', $notificationId)['read_at'] ?? null);
        $this->assertNull(collect($secondPayload)->firstWhere('id', $notificationId)['read_at'] ?? null);
    }

    public function test_teacher_and_musyrif_without_explicit_assignments_receive_no_operational_scope(): void
    {
        $this->seed();
        $teacher = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $musyrif = User::where('email', 'musyrif@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $teacher->id)->value('pesantren_id');
        $teacherIds = DB::table('teachers')->where('pesantren_id', $tenantId)->where('user_id', $teacher->id)->pluck('id');

        DB::table('teacher_assignments')->where('pesantren_id', $tenantId)->whereIn('teacher_id', $teacherIds)->delete();
        DB::table('musyrif_room_assignments')->where('pesantren_id', $tenantId)->where('user_id', $musyrif->id)->delete();

        $teacherResponse = $this->actingAs($teacher)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->getJson('/web-api/students?per_page=100')
            ->assertOk();
        $this->assertSame([], $teacherResponse->json('data'));

        $musyrifResponse = $this->actingAs($musyrif)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->getJson('/web-api/rooms?per_page=100')
            ->assertOk();
        $this->assertSame([], $musyrifResponse->json('data'));

        $teacherDashboard = $this->withToken($this->qrAccessToken($teacher))
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/api/v1/dashboard/guru')
            ->assertOk();
        $this->assertSame([], $teacherDashboard->json('data.students'));
        $this->assertSame([], $teacherDashboard->json('data.invoices'));
        $this->assertSame([], $teacherDashboard->json('data.medical'));

        $musyrifDashboard = $this->withToken($this->qrAccessToken($musyrif))
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->getJson('/api/v1/dashboard/musyrif')
            ->assertOk();
        $this->assertSame([], $musyrifDashboard->json('data.students'));
        $this->assertSame([], $musyrifDashboard->json('data.invoices'));
        $this->assertSame([], $musyrifDashboard->json('data.medical'));
    }

    public function test_unknown_dashboard_role_never_falls_back_to_super_admin_payload(): void
    {
        $this->seed();
        $teacher = User::where('email', 'guru@pesantenku.test')->firstOrFail();

        $this->withToken($this->qrAccessToken($teacher))
            ->getJson('/api/v1/dashboard/not-a-role')
            ->assertNotFound();
    }

    public function test_payment_verification_preserves_invoice_balance_and_rejects_overpayment(): void
    {
        $this->seed();
        $finance = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $payment = DB::table('payments')->whereIn('status', ['pending', 'waiting', 'paid'])->firstOrFail();
        $invoice = DB::table('invoices')->where('id', $payment->invoice_id)->firstOrFail();

        $this->actingAs($finance)
            ->withSession(['active_pesantren_id' => $payment->pesantren_id])
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertOk();

        $expectedStatus = ((float) $payment->amount === (float) $invoice->amount) ? 'paid' : 'unpaid';
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => $expectedStatus]);

        $overpaymentId = DB::table('payments')->insertGetId([
            'pesantren_id' => $payment->pesantren_id,
            'invoice_id' => $invoice->id,
            'student_id' => $invoice->student_id,
            'amount' => ((float) $invoice->amount) + 1,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($finance)
            ->withSession(['active_pesantren_id' => $payment->pesantren_id])
            ->postJson("/web-actions/workflow/payments/{$overpaymentId}/verify")
            ->assertUnprocessable();
        $this->assertDatabaseHas('payments', ['id' => $overpaymentId, 'status' => 'pending']);
    }

    public function test_attendance_resources_require_their_own_domain_fields(): void
    {
        $this->seed();
        $academic = User::where('email', 'akademik@pesantenku.test')->firstOrFail();
        $musyrif = User::where('email', 'musyrif@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $academic->id)->value('pesantren_id');
        $studentId = DB::table('students')->where('pesantren_id', $tenantId)->value('id');
        $roomId = DB::table('rooms')->where('pesantren_id', $tenantId)->value('id');
        $attendanceCount = DB::table('attendances')->where('pesantren_id', $tenantId)->count();
        $dormAttendanceCount = DB::table('dorm_attendances')->where('pesantren_id', $tenantId)->count();

        $this->actingAs($academic)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/attendances', [
                'student_id' => $studentId,
                'date' => now()->toDateString(),
                'status' => 'hadir',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);

        $this->actingAs($musyrif)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-api/dorm-attendances', [
                'student_id' => $studentId,
                'room_id' => $roomId,
                'date' => now()->toDateString(),
                'status' => 'hadir',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activity']);

        $this->assertSame($attendanceCount, DB::table('attendances')->where('pesantren_id', $tenantId)->count());
        $this->assertSame($dormAttendanceCount, DB::table('dorm_attendances')->where('pesantren_id', $tenantId)->count());
    }

    public function test_expired_idempotency_processing_lease_can_be_reclaimed_safely(): void
    {
        $this->seed();
        config(['pesantrenku.security.require_idempotency_key' => true]);

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $key = 'expired-processing-lease-0001';
        $scope = "tenant:{$tenantId} POST web-actions/qr/generate";

        DB::table('idempotency_keys')->insert([
            'user_id' => $owner->id,
            'scope' => $scope,
            'key' => $key,
            'request_hash' => str_repeat('0', 64),
            'status' => 'processing',
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $this->actingAsWithStepUp($owner)
            ->withHeaders([
                'X-Pesantren-Id' => (string) $tenantId,
                'Idempotency-Key' => $key,
            ])
            ->postJson('/web-actions/qr/generate', ['purpose' => 'identity'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'user_id' => $owner->id,
            'scope' => $scope,
            'key' => $key,
            'status' => 'completed',
        ]);
        $this->assertSame(1, DB::table('qr_tokens')->where('pesantren_id', $tenantId)->where('user_id', $owner->id)->count());
    }

    public function test_expired_restore_approval_is_committed_before_the_error_is_returned(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'expired-approval.backup.enc',
            'status' => 'success',
            'file_path' => 'backups/expired-approval.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('backup_restore_requests')->insertGetId([
            'backup_id' => $backupId,
            'pesantren_id' => $tenantId,
            'requested_by' => $owner->id,
            'status' => 'pending',
            'approvals' => json_encode([], JSON_THROW_ON_ERROR),
            'reason' => 'Menguji permintaan restore yang sudah kedaluwarsa.',
            'expires_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(RestoreApprovalService::class)->approve(
                DB::table('backup_restore_requests')->find($requestId),
                $owner,
                'owner_pesantren',
            );
            $this->fail('Approval yang kedaluwarsa harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Permintaan restore sudah kedaluwarsa.', $exception->getMessage());
        }

        $this->assertDatabaseHas('backup_restore_requests', ['id' => $requestId, 'status' => 'expired']);
    }

    public function test_expired_restore_execution_is_committed_without_claiming_the_backup(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'expired-execution.backup.enc',
            'status' => 'success',
            'file_path' => 'backups/expired-execution.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('backup_restore_requests')->insertGetId([
            'backup_id' => $backupId,
            'pesantren_id' => $tenantId,
            'requested_by' => $owner->id,
            'status' => 'approved',
            'approvals' => json_encode([
                ['role' => 'owner_pesantren', 'user_id' => $owner->id],
                ['role' => 'super_admin', 'user_id' => $superAdmin->id],
            ], JSON_THROW_ON_ERROR),
            'reason' => 'Menguji eksekusi restore yang sudah kedaluwarsa.',
            'expires_at' => now()->subMinute(),
            'approved_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(3),
            'updated_at' => now(),
        ]);

        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/backup/restore-requests/{$requestId}/execute", [
                'mfa_code' => app(TotpService::class)->currentCode($superAdmin),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('backup_restore_requests', ['id' => $requestId, 'status' => 'expired']);
        $this->assertDatabaseHas('backups', ['id' => $backupId, 'status' => 'success']);
    }

    public function test_metrics_are_isolated_by_instance_track_and_revision(): void
    {
        config([
            'cache.default' => 'array',
            'pesantrenku.observability.metric_identity' => [
                'instance' => 'stable-pod-1',
                'revision' => 'stable-revision',
                'track' => 'stable',
            ],
        ]);
        $stable = new MetricRegistry;
        $stable->increment('pesantenku_http_requests_total', ['status' => '200'], 7);

        config(['pesantrenku.observability.metric_identity' => [
            'instance' => 'canary-pod-1',
            'revision' => 'canary-revision',
            'track' => 'canary',
        ]]);
        $canary = new MetricRegistry;
        $canary->increment('pesantenku_http_requests_total', ['status' => '500'], 2);

        $stableMetrics = $stable->exposition();
        $canaryMetrics = $canary->exposition();

        $this->assertStringContainsString('instance="stable-pod-1",revision="stable-revision",status="200",track="stable"} 7', $stableMetrics);
        $this->assertStringNotContainsString('canary-pod-1', $stableMetrics);
        $this->assertStringContainsString('instance="canary-pod-1",revision="canary-revision",status="500",track="canary"} 2', $canaryMetrics);
        $this->assertStringNotContainsString('stable-pod-1', $canaryMetrics);
    }

    public function test_bootstrap_command_can_provision_mfa_for_an_existing_privileged_owner(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $owner->forceFill([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'remember_token' => 'bootstrap-remember-token',
        ])->save();
        $token = $owner->createToken('bootstrap-stale-token');
        $sessionId = 'bootstrap-session-'.$owner->id;
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ProductionHardeningTest',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('mfa_recovery_codes')->where('user_id', $owner->id)->delete();
        $secretFile = tempnam(sys_get_temp_dir(), 'pesantenku-mfa-');
        file_put_contents($secretFile, 'JBSWY3DPEHPK3PXP');

        try {
            $exitCode = Artisan::call('pesantenku:bootstrap-admin', [
                'email' => $owner->email,
                '--mfa-secret-file' => $secretFile,
                '--if-missing' => true,
            ]);
        } finally {
            @unlink($secretFile);
        }

        $this->assertSame(0, $exitCode);
        $freshOwner = $owner->fresh();
        $this->assertTrue($freshOwner->mfa_enabled);
        $this->assertNull($freshOwner->remember_token);
        $this->assertSame(10, DB::table('mfa_recovery_codes')->where('user_id', $owner->id)->whereNull('used_at')->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_duplicate_restore_delivery_does_not_fail_the_active_worker_claim(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'duplicate-delivery.backup.enc',
            'status' => 'restoring',
            'file_path' => 'backups/duplicate-delivery.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('backup_restore_requests')->insertGetId([
            'backup_id' => $backupId,
            'pesantren_id' => $tenantId,
            'requested_by' => $owner->id,
            'status' => 'executing',
            'approvals' => json_encode([], JSON_THROW_ON_ERROR),
            'reason' => 'Menguji duplicate delivery ketika worker utama memegang lock.',
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lock = Cache::lock("pesantenku:restore-request:{$requestId}", 60);
        $this->assertTrue($lock->get());
        try {
            app()->call([new RestoreTenantBackupJob($requestId, $superAdmin->id), 'handle']);
        } finally {
            $lock->release();
        }

        $this->assertDatabaseHas('backup_restore_requests', ['id' => $requestId, 'status' => 'executing']);
        $this->assertDatabaseHas('backups', ['id' => $backupId, 'status' => 'restoring']);
    }

    public function test_restore_request_cannot_be_executed_after_another_worker_claims_it(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'claimed.backup.enc',
            'status' => 'restoring',
            'file_path' => 'backups/claimed.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('backup_restore_requests')->insertGetId([
            'backup_id' => $backupId,
            'pesantren_id' => $tenantId,
            'requested_by' => $owner->id,
            'status' => 'executing',
            'approvals' => json_encode([
                ['role' => 'owner_pesantren', 'user_id' => $owner->id],
                ['role' => 'super_admin', 'user_id' => $superAdmin->id],
            ], JSON_THROW_ON_ERROR),
            'reason' => 'Menguji klaim eksekusi restore yang atomik.',
            'expires_at' => now()->addMinutes(30),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/backup/restore-requests/{$requestId}/execute", [
                'mfa_code' => app(TotpService::class)->currentCode($superAdmin),
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('backup_restore_requests', ['id' => $requestId, 'status' => 'executing']);
        $this->assertDatabaseHas('backups', ['id' => $backupId, 'status' => 'restoring']);
    }

    public function test_only_one_active_restore_request_can_exist_for_a_backup(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = DB::table('backups')->insertGetId([
            'pesantren_id' => $tenantId,
            'name' => 'single-active-request.backup.enc',
            'status' => 'success',
            'file_path' => 'backups/single-active-request.backup.enc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $backup = DB::table('backups')->find($backupId);
        $service = app(RestoreApprovalService::class);

        $service->create($backup, $owner, 'owner_pesantren', 'Permintaan restore pertama untuk pengujian atomisitas.');

        try {
            $service->create($backup, $owner, 'owner_pesantren', 'Permintaan restore kedua yang harus ditolak.');
            $this->fail('Permintaan restore aktif kedua harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Backup ini sudah memiliki permintaan restore aktif.', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('backup_restore_requests')->where('backup_id', $backupId)->count());
    }

    public function test_async_job_payloads_use_versioned_queue_protocols(): void
    {
        $this->assertSame('backups-v1', QueueNames::BACKUPS);
        $this->assertSame('imports-v3', QueueNames::IMPORTS);
        $this->assertSame('exports-v3', QueueNames::EXPORTS);
        $this->assertSame(count(QueueNames::monitored()), count(array_unique(QueueNames::monitored())));
    }

    public function test_stale_export_operation_is_claimed_and_redispatched_once(): void
    {
        Queue::fake();
        config([
            'pesantrenku.operations.processing_timeout_minutes' => 16,
            'pesantrenku.operations.max_recovery_attempts' => 3,
        ]);

        $jobId = DB::table('operation_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'pesantren_id' => null,
            'user_id' => null,
            'type' => 'export',
            'resource' => 'plans',
            'status' => 'processing',
            'queue_name' => QueueNames::EXPORTS,
            'progress' => 25,
            'total_rows' => 10,
            'processed_rows' => 2,
            'recovery_attempts' => 0,
            'payload' => json_encode([
                'table' => 'plans',
                'format' => 'csv',
                'filters' => [],
            ], JSON_THROW_ON_ERROR),
            'started_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $result = app(OperationJobRecoveryService::class)->recover();

        $this->assertSame(1, $result['recovered']);
        Queue::assertPushed(BuildResourceExportJob::class, fn (BuildResourceExportJob $job) => $job->queue === QueueNames::EXPORTS);
        $this->assertDatabaseHas('operation_jobs', [
            'id' => $jobId,
            'status' => 'queued',
            'queue_name' => QueueNames::EXPORTS,
            'recovery_attempts' => 1,
        ]);
    }

    public function test_stale_operation_stops_after_bounded_recovery_attempts(): void
    {
        Queue::fake();
        config([
            'pesantrenku.operations.processing_timeout_minutes' => 16,
            'pesantrenku.operations.max_recovery_attempts' => 1,
        ]);

        $jobId = DB::table('operation_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'pesantren_id' => null,
            'user_id' => null,
            'type' => 'export',
            'resource' => 'plans',
            'status' => 'processing',
            'queue_name' => QueueNames::EXPORTS,
            'progress' => 10,
            'total_rows' => 10,
            'processed_rows' => 1,
            'recovery_attempts' => 1,
            'payload' => json_encode(['table' => 'plans', 'format' => 'csv', 'filters' => []], JSON_THROW_ON_ERROR),
            'started_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $result = app(OperationJobRecoveryService::class)->recover();

        $this->assertSame(1, $result['failed']);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('operation_jobs', ['id' => $jobId, 'status' => 'failed']);
        $this->assertNotNull(DB::table('operation_jobs')->where('id', $jobId)->value('finished_at'));
    }
}
