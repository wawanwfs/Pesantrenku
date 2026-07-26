<?php

namespace Tests\Feature;

use App\Jobs\ProcessResourceImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_student_import_is_atomic_when_plan_quota_would_be_exceeded(): void
    {
        $this->seed();
        config(['pesantrenku.entitlements.enforced' => true]);

        $tu = User::where('email', 'tu@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $tu->id)->value('pesantren_id');
        $existing = DB::table('students')->where('pesantren_id', $tenantId)->whereNull('deleted_at')->count();
        $this->setStudentQuota($tenantId, $existing + 1);
        $csv = UploadedFile::fake()->createWithContent(
            'quota-students.csv',
            "nis,name,gender,status\nQUOTA-001,Quota Satu,Laki-laki,active\nQUOTA-002,Quota Dua,Perempuan,active\n",
        );

        $this->actingAs($tu)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->post('/web-api/students/import', ['file' => $csv])
            ->assertConflict();

        $this->assertDatabaseMissing('students', ['nis' => 'QUOTA-001']);
        $this->assertDatabaseMissing('students', ['nis' => 'QUOTA-002']);
    }

    public function test_queued_student_import_rechecks_quota_when_worker_runs(): void
    {
        $this->seed();
        config(['pesantrenku.entitlements.enforced' => true]);
        Storage::fake('local');
        Queue::fake();

        $tu = User::where('email', 'tu@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $tu->id)->value('pesantren_id');
        $existing = DB::table('students')->where('pesantren_id', $tenantId)->whereNull('deleted_at')->count();
        $this->setStudentQuota($tenantId, $existing + 2);
        $csv = UploadedFile::fake()->createWithContent(
            'queued-quota-students.csv',
            "nis,name,gender,status\nQWORK-001,Worker Satu,Laki-laki,active\nQWORK-002,Worker Dua,Perempuan,active\n",
        );

        $this->actingAs($tu)
            ->withHeader('X-Pesantren-Id', (string) $tenantId)
            ->post('/web-api/students/import', [
                'file' => $csv,
                'queue' => '1',
            ])
            ->assertAccepted();

        $queuedImport = null;
        Queue::assertPushed(ProcessResourceImportJob::class, function (ProcessResourceImportJob $job) use (&$queuedImport) {
            $queuedImport = $job;

            return true;
        });
        $this->assertNotNull($queuedImport);

        $classId = DB::table('students')->where('pesantren_id', $tenantId)->value('class_id');
        DB::table('students')->insert([
            'pesantren_id' => $tenantId,
            'class_id' => $classId,
            'nis' => 'QWORK-EXTERNAL',
            'name' => 'Pendaftaran Paralel',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app()->call([$queuedImport, 'handle']);
            $this->fail('Worker import seharusnya menolak quota yang berubah setelah enqueue.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Quota santri', $exception->getMessage());
        }

        $this->assertDatabaseMissing('students', ['nis' => 'QWORK-001']);
        $this->assertDatabaseMissing('students', ['nis' => 'QWORK-002']);
    }

    private function setStudentQuota(int $tenantId, int $quota): void
    {
        $planId = DB::table('subscriptions')
            ->where('pesantren_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->value('plan_id');

        DB::table('plans')->where('id', $planId)->update([
            'max_students' => $quota,
            'updated_at' => now(),
        ]);
    }
}
