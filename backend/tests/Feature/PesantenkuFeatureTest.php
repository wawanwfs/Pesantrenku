<?php

namespace Tests\Feature;

use App\Jobs\CreateTenantBackupJob;
use App\Jobs\RestoreTenantBackupJob;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PesantenkuFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_proxy_https_generates_secure_asset_urls(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'pesantrenku.ai',
                'X-Forwarded-Host' => 'pesantrenku.ai',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/login');

        $response->assertOk()
            ->assertSee('https://pesantrenku.ai/build/', false)
            ->assertDontSee('http://pesantrenku.ai/build/', false);
    }

    public function test_api_login_returns_sanctum_token_and_roles(): void
    {
        $this->seed();

        $response = $this->postApiLogin([
            'login' => 'superadmin@pesantenku.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['email'], 'roles'])
            ->assertJsonPath('roles.0', 'super_admin');

        $tenantResponse = $this->postApiLogin([
            'login' => 'keuangan@pesantenku.test',
            'password' => 'password',
        ]);

        $tenantResponse->assertOk()
            ->assertJsonPath('roles.0', 'admin_keuangan')
            ->assertJsonPath('active_pesantren.name', 'Pondok Pesantren Darul Hikmah');
    }

    public function test_web_login_discards_stale_intended_url_from_another_role(): void
    {
        $this->seed();

        $response = $this
            ->withSession(['url.intended' => '/musyrif/module/perizinan'])
            ->post('/login', [
                'login' => 'keuangan@pesantenku.test',
                'password' => 'password',
            ]);

        $response->assertRedirect('/dashboard/keuangan');
        $this->assertAuthenticatedAs(User::where('email', 'keuangan@pesantenku.test')->firstOrFail());
        $this->assertNull(session('url.intended'));
    }

    public function test_system_setting_create_is_idempotent_for_existing_tenant_key(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        $value = json_encode(['title' => 'Kwitansi Audit'], JSON_THROW_ON_ERROR);

        $this->actingAsWithStepUp($owner)
            ->postJson('/web-api/system-settings', ['key' => 'pdf.receipt_template', 'value' => $value])
            ->assertOk()
            ->assertJsonPath('key', 'pdf.receipt_template');

        $this->assertSame(1, DB::table('system_settings')->where('pesantren_id', $tenantId)->where('key', 'pdf.receipt_template')->count());
        $this->assertSame($value, DB::table('system_settings')->where('pesantren_id', $tenantId)->where('key', 'pdf.receipt_template')->value('value'));
    }

    public function test_dashboard_endpoint_returns_seeded_stats(): void
    {
        $this->seed();
        $token = $this->postApiLogin([
            'login' => 'akademik@pesantenku.test',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/dashboard/akademik')
            ->assertOk()
            ->assertJsonPath('data.stats.0.label', 'Total Siswa')
            ->assertJsonPath('data.stats.5.label', 'Rata-rata Nilai');
    }

    public function test_invoice_attendance_and_grade_data_are_seeded(): void
    {
        $this->seed();

        $darulId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');

        $this->assertDatabaseHas('invoices', ['pesantren_id' => $darulId, 'invoice_number' => 'INV/DH/2025/05/001', 'status' => 'paid']);
        $this->assertDatabaseHas('payments', ['pesantren_id' => $darulId, 'status' => 'pending']);
        $this->assertGreaterThan(0, DB::table('grades')->where('average_score', '>=', 80)->count());

        $this->assertDatabaseHas('attendances', ['student_id' => 1, 'status' => 'hadir']);
    }

    public function test_tenant_admin_can_only_read_active_pesantren_data(): void
    {
        $this->seed();

        $darulId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        $falahId = DB::table('pesantrens')->where('name', 'Pesantren Al-Falah')->value('id');
        $token = $this->postApiLogin([
            'login' => 'keuangan@pesantenku.test',
            'password' => 'password',
        ])->json('token');

        $response = $this->withToken($token)->getJson('/api/v1/students');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertTrue(collect($response->json('data'))->every(fn ($row) => $row['pesantren_id'] === $darulId));
        $response->assertJsonMissing(['name' => 'Nabila Azizah']);

        $forbiddenTenant = $this->withToken($token)
            ->withHeader('X-Pesantren-Id', (string) $falahId)
            ->getJson('/api/v1/students');
        $this->assertSame(403, $forbiddenTenant->getStatusCode(), $forbiddenTenant->getContent());
    }

    public function test_owner_can_switch_between_owned_pesantrens(): void
    {
        $this->seed();

        $falahId = DB::table('pesantrens')->where('name', 'Pesantren Al-Falah')->value('id');
        $token = $this->postApiLogin([
            'login' => 'owner@pesantenku.test',
            'password' => 'password',
        ])->json('token');

        $response = $this->withToken($token)
            ->withHeader('X-Pesantren-Id', (string) $falahId)
            ->getJson('/api/v1/students');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->every(fn ($row) => $row['pesantren_id'] === $falahId));
        $response->assertJsonFragment(['name' => 'Nabila Azizah']);
    }

    public function test_web_panels_enforce_platform_and_tenant_roles(): void
    {
        $this->seed();

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();

        $this->actingAsWithStepUp($owner)->get('/dashboard/owner')->assertOk();
        $ownerDenied = $this->actingAsWithStepUp($owner)->get('/dashboard/super-admin');
        $this->assertSame(403, $ownerDenied->getStatusCode(), $ownerDenied->getContent());

        $this->actingAs($keuangan)->get('/dashboard/keuangan')->assertOk();
        $financeDenied = $this->actingAs($keuangan)->get('/dashboard/super-admin');
        $this->assertSame(403, $financeDenied->getStatusCode(), $financeDenied->getContent());
    }

    public function test_operational_roles_can_create_allowed_records(): void
    {
        $this->seed();

        $studentId = DB::table('students')->where('name', 'Najwa Karimah')->value('id');
        $classId = DB::table('schedules')->where('teacher_name', 'Usth. Siti Aisyah')->value('class_id');
        $subjectId = DB::table('subjects')->where('name', 'Bahasa Arab')->value('id');
        $roomId = DB::table('rooms')->where('name', 'C-07')->value('id');
        $invoiceId = DB::table('invoices')->where('student_id', $studentId)->value('id');
        $paymentMethodId = DB::table('payment_methods')->where('name', 'Transfer Bank')->value('id');

        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $otherStudentId = DB::table('students')->where('id', '!=', $studentId)->value('id');

        $this->actingAs($santri)
            ->getJson('/web-api/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Najwa Karimah'])
            ->assertJsonMissing(['name' => 'Ahmad Zaky']);

        $this->actingAs($santri)->postJson('/web-api/permits', [
            'student_id' => $studentId,
            'type' => 'Izin Keluarga',
            'start_at' => '2026-07-08 08:00:00',
            'end_at' => '2026-07-08 17:00:00',
            'reason' => 'Keperluan keluarga',
            'status' => 'pending',
        ])->assertCreated();

        $invalidPermit = $this->actingAs($santri)->postJson('/web-api/permits', [
            'student_id' => $otherStudentId,
            'type' => 'Izin Tidak Sah',
            'start_at' => '2026-07-08 08:00:00',
            'end_at' => '2026-07-08 17:00:00',
            'reason' => 'Harus ditolak',
            'status' => 'pending',
        ]);
        $this->assertSame(403, $invalidPermit->getStatusCode(), $invalidPermit->getContent());

        $deniedPlan = $this->actingAs($santri)->postJson('/web-api/plans', [
            'name' => 'Tidak boleh',
            'code' => 'DENIED',
        ]);
        $this->assertSame(403, $deniedPlan->getStatusCode(), $deniedPlan->getContent());

        $wali = User::where('email', 'wali@pesantenku.test')->firstOrFail();
        $this->actingAs($wali)->postJson('/web-api/payments', [
            'invoice_id' => $invoiceId,
            'student_id' => $studentId,
            'payment_method_id' => $paymentMethodId,
            'amount' => 125000,
            'paid_at' => '2026-07-08 09:00:00',
            'status' => 'pending',
            'reference' => 'WALI-AUDIT-001',
        ])->assertCreated();

        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $this->actingAs($guru)->postJson('/web-api/assignments', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'title' => 'Tugas Audit Browser',
            'due_date' => '2026-07-20',
            'status' => 'given',
        ])->assertCreated();

        $musyrif = User::where('email', 'musyrif@pesantenku.test')->firstOrFail();
        $this->actingAs($musyrif)->postJson('/web-api/dorm-attendances', [
            'student_id' => $studentId,
            'room_id' => $roomId,
            'date' => '2026-07-08',
            'activity' => 'Apel malam',
            'status' => 'hadir',
        ])->assertCreated();

        $klinik = User::where('email', 'klinik@pesantenku.test')->firstOrFail();
        $this->actingAs($klinik)->postJson('/web-api/medical-queues', [
            'student_id' => $studentId,
            'queue_number' => 'AUD-001',
            'status' => 'waiting',
            'estimated_time' => '10:30',
        ])->assertCreated();

        $this->assertDatabaseHas('permits', ['student_id' => $studentId, 'type' => 'Izin Keluarga']);
        $this->assertDatabaseHas('payments', ['reference' => 'WALI-AUDIT-001']);
        $this->assertDatabaseHas('assignments', ['title' => 'Tugas Audit Browser']);
        $this->assertDatabaseHas('dorm_attendances', ['activity' => 'Apel malam']);
        $this->assertDatabaseHas('medical_queues', ['queue_number' => 'AUD-001']);
    }

    public function test_csv_import_notifications_and_backup_actions_are_stateful(): void
    {
        $this->seed();

        $akademik = User::where('email', 'akademik@pesantenku.test')->firstOrFail();
        $csv = UploadedFile::fake()->createWithContent('programs.csv', "name,code,level\nProgram Audit,AUDIT,Test\n");

        $this->actingAs($akademik)
            ->post('/web-api/programs/import', ['file' => $csv])
            ->assertOk()
            ->assertJsonPath('inserted', 1);

        $this->assertDatabaseHas('programs', ['name' => 'Program Audit', 'code' => 'AUDIT']);

        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $santri->id)->where('status', 'active')->value('pesantren_id');
        $notificationId = DB::table('notification_logs')->insertGetId([
            'pesantren_id' => $tenantId,
            'user_id' => null,
            'title' => 'Pengumuman pengujian receipt',
            'body' => 'Broadcast tidak boleh dibaca global.',
            'type' => 'info',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($santri)
            ->postJson('/web-actions/notifications/read', ['ids' => [$notificationId]])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertNull(DB::table('notification_logs')->where('id', $notificationId)->value('read_at'));
        $this->assertDatabaseHas('notification_receipts', [
            'notification_id' => $notificationId,
            'user_id' => $santri->id,
        ]);

        $qr = $this->actingAs($santri)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/qr/generate', ['purpose' => 'attendance'])
            ->assertCreated()
            ->assertJsonStructure(['code', 'purpose', 'expires_at'])
            ->json('code');

        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $registration = $this->registerQrScanner($guru, (int) $tenantId);
        $challenge = $this->requestQrChallenge($guru, (int) $tenantId, $qr);
        $this->submitQrScan($guru, (int) $tenantId, $qr, $registration, $challenge)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('side_effect.type', 'attendance_recorded');

        $this->assertDatabaseHas('qr_tokens', ['code' => hash('sha256', $qr), 'status' => 'used']);
        $this->assertDatabaseHas('attendances', ['session' => 'qr', 'status' => 'hadir']);

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        Storage::fake('local');

        $backupId = $this->createAndProcessTenantBackup($owner);

        $this->assertDatabaseHas('backups', ['id' => $backupId, 'status' => 'success']);
        $filePath = DB::table('backups')->where('id', $backupId)->value('file_path');
        Storage::disk('local')->assertExists($filePath);
    }

    public function test_workflow_transitions_filters_and_operational_scopes(): void
    {
        $this->seed();

        $permit = DB::table('permits')->where('status', 'pending')->first();
        $musyrif = User::where('email', 'musyrif@pesantenku.test')->firstOrFail();

        $this->actingAs($musyrif)
            ->postJson("/web-actions/workflow/permits/{$permit->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('permits', ['id' => $permit->id, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'permits', 'action' => 'APPROVE permits']);

        $studentId = DB::table('students')->where('name', 'Najwa Karimah')->value('id');
        $rejectPermitId = DB::table('permits')->insertGetId([
            'pesantren_id' => DB::table('students')->where('id', $studentId)->value('pesantren_id'),
            'student_id' => $studentId,
            'type' => 'Izin Audit Ditolak',
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'reason' => 'Audit note wajib',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($musyrif)
            ->postJson("/web-actions/workflow/permits/{$rejectPermitId}/reject")
            ->assertStatus(422);

        $this->actingAs($musyrif)
            ->postJson("/web-actions/workflow/permits/{$rejectPermitId}/reject", ['note' => 'Dokumen izin belum lengkap.'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('notification_logs', ['title' => 'Perizinan ditolak']);

        $this->actingAs($musyrif)
            ->getJson("/web-api/permits/{$rejectPermitId}/timeline")
            ->assertOk()
            ->assertJsonFragment(['note' => 'Dokumen izin belum lengkap.']);

        $payment = DB::table('payments')->where('status', 'pending')->first();
        $paymentId = $payment->id;
        $invoiceId = $payment->invoice_id;

        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $this->actingAs($keuangan)
            ->postJson("/web-actions/workflow/payments/{$paymentId}/verify")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'success']);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'unpaid']);

        $this->actingAs($keuangan)
            ->getJson('/web-api/payments?status=success&date_from=2025-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertJsonFragment(['reference' => $payment->reference]);

        $guru = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $this->assertDatabaseHas('teacher_assignments', ['status' => 'active']);
        $this->assertDatabaseHas('musyrif_room_assignments', ['status' => 'active']);

        $teacherId = DB::table('teachers')->where('user_id', $guru->id)->value('id');
        $assignment = DB::table('teacher_assignments')->where('teacher_id', $teacherId)->first();
        $allowedClassId = $assignment->class_id;
        $allowedSubjectId = $assignment->subject_id;
        $otherStudent = DB::table('students')->where('name', 'Ahmad Zaky')->first();
        $otherClassId = $otherStudent->class_id;
        $otherStudentId = $otherStudent->id;

        $this->actingAs($guru)
            ->getJson('/web-api/classes')
            ->assertOk()
            ->assertJsonFragment(['id' => $allowedClassId])
            ->assertJsonMissing(['id' => $otherClassId]);

        $invalidGrade = $this->actingAs($guru)->postJson('/web-api/grades', [
            'student_id' => $otherStudentId,
            'class_id' => $otherClassId,
            'subject_id' => $allowedSubjectId,
            'semester' => 'Audit',
            'assignment_score' => 80,
            'midterm_score' => 80,
            'final_score' => 80,
            'attitude_score' => 80,
            'average_score' => 80,
            'grade' => 'B',
        ]);
        $this->assertSame(403, $invalidGrade->getStatusCode(), $invalidGrade->getContent());

        $maleRoomId = DB::table('rooms')->where('name', 'A-01')->value('id');
        $maleStudentId = DB::table('students')->where('room', 'A-01')->value('id');
        $invalidDormAttendance = $this->actingAs($musyrif)->postJson('/web-api/dorm-attendances', [
            'student_id' => $maleStudentId,
            'room_id' => $maleRoomId,
            'date' => '2026-07-09',
            'activity' => 'Audit scope',
            'status' => 'hadir',
        ]);
        $this->assertSame(403, $invalidDormAttendance->getStatusCode(), $invalidDormAttendance->getContent());

        $this->actingAs($keuangan)
            ->getJson('/web-api/payments/analytics?status=success&date_from=2025-01-01&date_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('completed', 3);

        $this->actingAs($keuangan)
            ->get('/web-api/payments/export?format=excel&status=success')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');

        $this->actingAs($keuangan)
            ->get('/web-api/payments/export?format=pdf&status=success')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_hardening_import_bulk_notifications_permission_matrix_and_relation_labels(): void
    {
        $this->seed();

        $studentId = DB::table('students')->where('name', 'Najwa Karimah')->value('id');
        $tu = User::where('email', 'tu@pesantenku.test')->firstOrFail();
        $akademik = User::where('email', 'akademik@pesantenku.test')->firstOrFail();
        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $santri = User::where('email', 'santri@pesantenku.test')->firstOrFail();

        $this->actingAs($keuangan)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->postJson('/web-api/payments', ['student_id' => $studentId, 'status' => 'pending'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $legacyXls = UploadedFile::fake()->createWithContent('students.xls', "nis\tname\tgender\tstatus\nIMP-001\tFormat Lama\tLaki-laki\tactive\n");
        $this->actingAs($tu)
            ->post('/web-api/students/import', ['file' => $legacyXls])
            ->assertStatus(422);

        $csv = UploadedFile::fake()->createWithContent('students.csv', "nis,name,gender,status\nIMP-001,Santri Import Aman,Laki-laki,active\n");
        $this->actingAs($tu)
            ->post('/web-api/students/import', ['file' => $csv])
            ->assertOk()
            ->assertJsonPath('inserted', 1)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseHas('students', ['nis' => 'IMP-001', 'name' => 'Santri Import Aman']);

        $teacherId = DB::table('teachers')->where('name', 'Ust. Ahmad Fauzi')->value('id');
        $this->actingAs($akademik)
            ->postJson('/web-api/teachers/bulk', [
                'action' => 'status',
                'status' => 'inactive',
                'ids' => [$teacherId],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('teachers', ['id' => $teacherId, 'status' => 'active']);

        $this->actingAs($santri)
            ->getJson('/web-actions/notifications')
            ->assertOk()
            ->assertJsonStructure(['data', 'unread', 'polled_at']);

        $this->actingAsWithStepUp($owner)
            ->getJson('/web-api/permissions/matrix')
            ->assertOk()
            ->assertJsonPath('matrix.owner_pesantren.label', 'Owner Pesantren')
            ->assertJsonFragment(['path' => '/web-api/payments/bulk']);

        $matrixDenied = $this->actingAs($keuangan)
            ->getJson('/web-api/permissions/matrix');
        $this->assertSame(403, $matrixDenied->getStatusCode(), $matrixDenied->getContent());

        $this->actingAs($keuangan)
            ->getJson('/web-api/payments?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data' => [['student_name', 'invoice_name']]]);

        $pdf = $this->actingAs($keuangan)
            ->get('/web-api/payments/export?format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('Kwitansi Pembayaran', $pdf->getContent());
    }

    public function test_production_readiness_permission_editor_import_preview_jobs_sse_and_security_headers(): void
    {
        $this->seed();

        $health = $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('queue_table', 'ok');

        $this->assertSame('nosniff', $health->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $health->headers->get('X-Frame-Options'));

        $tu = User::where('email', 'tu@pesantenku.test')->firstOrFail();
        $csv = UploadedFile::fake()->createWithContent('students.csv', "nis,nama_lengkap,gender,status\nPRV-001,Santri Preview,Laki-laki,active\n");

        $this->actingAs($tu)
            ->post('/web-api/students/import/preview', [
                'file' => $csv,
                'mapping' => json_encode(['nama_lengkap' => 'name']),
            ])
            ->assertOk()
            ->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('mapping.nama_lengkap', 'name');

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $this->actingAsWithStepUp($owner, 'permission-matrix')
            ->patchJson('/web-api/permissions/matrix', [
                'role' => 'guru',
                'read' => ['students', 'classes', 'subjects'],
                'write' => ['grades'],
            ])
            ->assertOk()
            ->assertJsonPath('source', 'tenant');

        $this->assertDatabaseHas('system_settings', ['key' => 'permissions.matrix_override']);

        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $jobUuid = $this->actingAs($keuangan)
            ->postJson('/web-api/payments/export/queue', ['format' => 'excel'])
            ->assertStatus(202)
            ->json('job.uuid');

        $job = DB::table('operation_jobs')->where('uuid', $jobUuid)->first();
        $this->assertNotNull($job);
        $this->assertSame('export', $job->type);
        $this->assertSame('payments', $job->resource);
        $this->assertContains($job->status, ['queued', 'processing', 'completed']);

        $this->actingAs($keuangan)
            ->getJson("/web-actions/jobs/{$jobUuid}")
            ->assertOk()
            ->assertJsonPath('uuid', $jobUuid);

        $stream = $this->actingAs($keuangan)
            ->get('/web-actions/notifications/stream')
            ->assertOk();

        $this->assertStringContainsString('text/event-stream', $stream->headers->get('Content-Type'));
        $this->assertStringContainsString('event: notifications', $stream->streamedContent());
    }

    public function test_scaling_download_chunked_import_custom_roles_queue_monitor_rate_limit_and_restore(): void
    {
        $this->seed();
        Storage::fake('local');
        config()->set('pesantrenku.import.chunk_size', 2);

        $tu = User::where('email', 'tu@pesantenku.test')->firstOrFail();
        $csv = UploadedFile::fake()->createWithContent('large-students.csv', "nis,name,gender,status\nCHK-001,Chunk Satu,Laki-laki,active\nCHK-002,Chunk Dua,Perempuan,active\nCHK-003,Chunk Tiga,Laki-laki,active\n");
        $importUuid = $this->actingAs($tu)->post('/web-api/students/import', [
            'file' => $csv,
            'queue' => '1',
            'mapping' => json_encode(['nis' => 'nis', 'name' => 'name', 'gender' => 'gender', 'status' => 'status']),
        ])->assertStatus(202)->json('job.uuid');

        $this->assertDatabaseHas('students', ['nis' => 'CHK-001']);
        $this->assertDatabaseHas('students', ['nis' => 'CHK-003']);
        $importJob = DB::table('operation_jobs')->where('uuid', $importUuid)->first();
        $this->assertSame('completed', $importJob->status);
        $this->assertSame(100, (int) $importJob->progress);
        Storage::disk('local')->assertExists($importJob->source_path);
        Storage::disk('local')->assertExists($importJob->output_path);
        $this->actingAs($tu)->get("/web-actions/jobs/{$importUuid}/download")->assertOk();

        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $customRoleId = $this->actingAsWithStepUp($owner, 'custom-role-create')->postJson('/web-api/permissions/custom-roles', [
            'name' => 'finance_viewer',
            'label' => 'Finance Viewer',
            'description' => 'Hanya melihat data santri.',
            'read' => ['students'],
            'write' => [],
            'status' => 'active',
        ])->assertCreated()->json('id');

        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $membershipId = DB::table('pesantren_user')->where('user_id', $keuangan->id)->value('id');
        $this->actingAsWithStepUp($owner, 'custom-role-assignment')->postJson("/web-api/permissions/custom-roles/{$customRoleId}/assign", [
            'membership_ids' => [$membershipId],
        ])->assertOk()->assertJsonPath('assigned', 1);

        $this->actingAs($keuangan)->getJson('/web-api/students')->assertOk();
        $customDenied = $this->actingAs($keuangan)->getJson('/web-api/payments');
        $this->assertSame(403, $customDenied->getStatusCode(), $customDenied->getContent());
        $this->assertDatabaseHas('audit_logs', ['module' => 'tenant_roles']);

        $this->actingAsWithStepUp($owner)->getJson('/web-actions/queue/monitor')
            ->assertOk()
            ->assertJsonStructure(['workers' => ['pending', 'reserved', 'failed'], 'operations', 'failed_jobs']);

        $program = DB::table('programs')->first();
        $backupId = $this->createAndProcessTenantBackup($owner);
        $this->actingAsWithStepUp($owner)->get("/web-actions/backup/{$backupId}/download")->assertOk();
        $this->assertDatabaseHas('backups', ['id' => $backupId, 'encryption_provider' => 'local', 'encryption_key_id' => 'app-key']);
        DB::table('programs')->where('id', $program->id)->update(['name' => 'Nama Rusak']);
        $ownerCode = app(TotpService::class)->currentCode($owner->fresh());
        DB::table('mfa_recovery_codes')->insert([
            [
                'user_id' => $owner->id,
                'code_hash' => hash('sha256', 'feature-owner-'.$owner->id),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => User::where('email', 'superadmin@pesantenku.test')->value('id'),
                'code_hash' => hash('sha256', 'feature-super-admin'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $restoreRequestId = $this->actingAsWithStepUp($owner)->postJson("/web-actions/backup/{$backupId}/restore", [
            'confirmation' => 'RESTORE',
            'mfa_code' => $ownerCode,
            'reason' => 'Memulihkan data program setelah simulasi kerusakan.',
        ])->assertStatus(202)->assertJsonPath('request.status', 'pending')->json('request.id');
        $tenantId = DB::table('backups')->where('id', $backupId)->value('pesantren_id');
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();
        $superCode = app(TotpService::class)->currentCode($superAdmin);
        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/backup/restore-requests/{$restoreRequestId}/approve", ['mfa_code' => $superCode])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');
        Cache::forget("pesantenku:mfa-replay:{$superAdmin->id}:".intdiv(time(), 30));
        $superExecuteCode = app(TotpService::class)->currentCode($superAdmin);
        Queue::fake();
        $this->actingAsWithStepUp($superAdmin)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson("/web-actions/backup/restore-requests/{$restoreRequestId}/execute", ['mfa_code' => $superExecuteCode])
            ->assertAccepted()
            ->assertJsonPath('message', 'Restore tenant masuk antrean pemulihan.')
            ->assertJsonPath('request.status', 'executing');

        $queuedRestore = null;
        Queue::assertPushed(RestoreTenantBackupJob::class, function (RestoreTenantBackupJob $job) use (&$queuedRestore) {
            $queuedRestore = $job;

            return true;
        });
        $this->assertNotNull($queuedRestore);
        app()->call([$queuedRestore, 'handle']);

        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name' => $program->name]);
        $this->assertDatabaseHas('backup_restore_requests', ['id' => $restoreRequestId, 'status' => 'executed']);

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);
            $response = $this->postApiLogin([
                'login' => 'rate-limit@example.test',
                'password' => 'wrong-password',
            ]);
        }
        $this->assertSame(429, $response->getStatusCode(), $response->getContent());
    }

    public function test_observability_metrics_and_correlation_headers_are_available(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'test-correlation-1234')->get('/health');

        $response->assertOk()
            ->assertHeader('X-Correlation-ID', 'test-correlation-1234')
            ->assertHeader('X-DB-Query-Count')
            ->assertHeader('X-DB-Slow-Query-Count')
            ->assertJsonStructure(['status', 'database', 'storage_disk', 'broadcast']);

        $metrics = $this->get('/metrics')->assertOk();
        $this->assertStringContainsString('pesantenku_http_requests_total', $metrics->getContent());
        $this->assertStringContainsString('pesantenku_database_queries_total', $metrics->getContent());
        $this->assertStringContainsString('pesantenku_database_slow_queries_total', $metrics->getContent());
        $this->assertStringContainsString('pesantenku_queue_depth', $metrics->getContent());
        $this->assertStringContainsString('pesantenku_reverb_connections', $metrics->getContent());
    }

    public function test_queue_wait_and_runtime_metrics_are_recorded(): void
    {
        $this->seed();
        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();

        $this->actingAs($keuangan)
            ->postJson('/web-api/payments/export/queue', ['format' => 'excel'])
            ->assertAccepted();

        $metrics = $this->get('/metrics')->assertOk()->getContent();
        $this->assertStringContainsString('pesantenku_queue_wait_duration_count', $metrics);
        $this->assertStringContainsString('pesantenku_queue_job_duration_count', $metrics);
    }

    public function test_payment_workflow_replays_the_same_idempotent_response(): void
    {
        $this->seed();
        $keuangan = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $payment = DB::table('payments')->whereIn('status', ['pending', 'waiting', 'paid'])->first();
        $this->assertNotNull($payment);
        $key = 'payment-verification-test-1234';

        $first = $this->actingAs($keuangan)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertOk();

        $second = $this->actingAs($keuangan)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/web-actions/workflow/payments/{$payment->id}/verify")
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('status'), $second->json('status'));
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_disaster_recovery_drill_validates_a_tenant_backup(): void
    {
        $this->seed();
        Storage::fake('local');
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $backupId = $this->createAndProcessTenantBackup($owner);

        $this->artisan('pesantenku:dr-drill', ['--backup-id' => $backupId])
            ->expectsOutputToContain('Backup integrity')
            ->assertSuccessful();

        $reports = Storage::disk('local')->allFiles('dr-drills');
        $this->assertCount(1, $reports);
    }

    public function test_user_credentials_are_redacted_from_crud_audit_and_exports(): void
    {
        $this->seed();
        Storage::fake('local');
        $superAdmin = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();

        $list = $this->actingAsWithStepUp($superAdmin)->getJson('/web-api/users?per_page=100')->assertOk();
        foreach ($list->json('data') as $row) {
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('remember_token', $row);
            $this->assertArrayNotHasKey('mfa_secret', $row);
        }

        $this->actingAsWithStepUp($superAdmin)->getJson("/web-api/users/{$superAdmin->id}")
            ->assertOk()
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('mfa_secret');

        $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/users', [
            'name' => 'Credential Safe User',
            'email' => 'credential-safe@example.test',
            'password' => 'secret-password',
            'mfa_secret' => 'must-not-be-accepted',
            'remember_token' => 'must-not-be-accepted',
            'status' => 'active',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['mfa_secret', 'remember_token']);

        $created = $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/users', [
            'name' => 'Credential Safe User',
            'email' => 'credential-safe@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ])->assertCreated();
        $created->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('mfa_secret');

        $userId = $created->json('id');
        $this->assertNull(DB::table('users')->where('id', $userId)->value('mfa_secret'));
        $this->assertNull(DB::table('users')->where('id', $userId)->value('remember_token'));
        $audit = DB::table('audit_logs')->where('module', 'users')->latest('id')->value('metadata');
        $this->assertStringNotContainsString('password', (string) $audit);
        $this->assertStringNotContainsString('remember_token', (string) $audit);
        $this->assertStringNotContainsString('mfa_secret', (string) $audit);

        $syncExport = $this->actingAsWithStepUp($superAdmin)->get('/web-api/users/export?format=csv')->assertOk();
        $header = strtok($syncExport->getContent(), "\n");
        $this->assertStringNotContainsString('password', $header);
        $this->assertStringNotContainsString('remember_token', $header);
        $this->assertStringNotContainsString('mfa_secret', $header);

        $jobUuid = $this->actingAsWithStepUp($superAdmin)->postJson('/web-api/users/export/queue', ['format' => 'csv'])
            ->assertStatus(202)
            ->json('job.uuid');
        $job = DB::table('operation_jobs')->where('uuid', $jobUuid)->first();
        $queuedHeader = strtok(Storage::disk('local')->get($job->output_path), "\n");
        $this->assertStringNotContainsString('password', $queuedHeader);
        $this->assertStringNotContainsString('remember_token', $queuedHeader);
        $this->assertStringNotContainsString('mfa_secret', $queuedHeader);

        $import = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,password\nImport User,import-user@example.test,plaintext-secret\n"
        );
        $preview = $this->actingAsWithStepUp($superAdmin)->post('/web-api/users/import/preview', [
            'file' => $import,
            'mapping' => json_encode(['name' => 'name', 'email' => 'email', 'password' => 'password']),
        ])->assertOk();
        $this->assertContains('password', $preview->json('columns'));
        $this->assertSame('password', $preview->json('mapping.password'));

        $import = UploadedFile::fake()->createWithContent(
            'users.csv',
            "name,email,password\nImport User,import-user@example.test,plaintext-secret\n"
        );
        $this->actingAsWithStepUp($superAdmin)->post('/web-api/users/import', [
            'file' => $import,
            'mapping' => json_encode(['name' => 'name', 'email' => 'email', 'password' => 'password']),
        ])->assertOk()->assertJsonPath('inserted', 1);
        $this->assertTrue(Hash::check('plaintext-secret', DB::table('users')->where('email', 'import-user@example.test')->value('password')));
    }

    public function test_owner_cannot_manage_or_read_protected_system_settings(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantrens')->where('name', 'Pondok Pesantren Darul Hikmah')->value('id');
        $protectedId = DB::table('system_settings')->insertGetId([
            'pesantren_id' => $tenantId,
            'key' => 'permissions.internal_test',
            'value' => json_encode(['enabled' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsWithStepUp($owner)->postJson('/web-api/system-settings', [
            'key' => 'permissions.matrix_override',
            'value' => '{}',
        ])->assertForbidden();
        $this->actingAsWithStepUp($owner)->getJson("/web-api/system-settings/{$protectedId}")->assertNotFound();
        $this->actingAsWithStepUp($owner)->getJson('/web-api/system-settings?per_page=100')
            ->assertOk()
            ->assertJsonMissing(['key' => 'permissions.internal_test']);

        $this->actingAsWithStepUp($owner)->postJson('/web-api/system-settings', [
            'key' => 'pdf.receipt_template',
            'value' => json_encode(['title' => 'Kwitansi Tenant']),
        ])->assertOk();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed();
        DB::table('users')->where('email', 'keuangan@pesantenku.test')->update(['status' => 'inactive']);

        $this->postApiLogin([
            'login' => 'keuangan@pesantenku.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonMissingPath('token');
    }

    public function test_owner_queued_pesantren_export_is_limited_to_active_tenant(): void
    {
        $this->seed();
        Storage::fake('local');
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();

        $jobUuid = $this->actingAsWithStepUp($owner)->postJson('/web-api/pesantrens/export/queue', [
            'format' => 'csv',
            'search' => 'Pesantren',
        ])->assertStatus(202)->json('job.uuid');

        $job = DB::table('operation_jobs')->where('uuid', $jobUuid)->first();
        $contents = Storage::disk('local')->get($job->output_path);
        $this->assertStringContainsString('Pondok Pesantren Darul Hikmah', $contents);
        $this->assertStringNotContainsString('Pesantren Al-Falah', $contents);
        $this->assertSame(1, (int) $job->processed_rows);
    }

    private function createAndProcessTenantBackup(User $actor, ?int $tenantId = null): int
    {
        $tenantId ??= (int) DB::table('pesantren_user')
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->value('pesantren_id');

        Queue::fake();
        $backupId = (int) $this->actingAs($actor)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->postJson('/web-actions/backup/run')
            ->assertAccepted()
            ->assertJsonPath('status', 'queued')
            ->json('backup_id');

        $queuedBackup = null;
        Queue::assertPushed(CreateTenantBackupJob::class, function (CreateTenantBackupJob $job) use (&$queuedBackup) {
            $queuedBackup = $job;

            return true;
        });
        $this->assertNotNull($queuedBackup);
        app()->call([$queuedBackup, 'handle']);

        return $backupId;
    }
}
