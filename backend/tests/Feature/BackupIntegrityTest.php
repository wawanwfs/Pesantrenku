<?php

namespace Tests\Feature;

use App\Jobs\CreateTenantBackupJob;
use App\Models\User;
use App\Services\BackupCipher;
use App\Services\BackupRestoreService;
use App\Services\OperationStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_is_complete_and_restore_removes_post_snapshot_rows(): void
    {
        $this->seed();
        Storage::fake('local');
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');

        collect(range(1, 1005))->chunk(150)->each(function ($numbers) use ($tenantId) {
            DB::table('announcements')->insert($numbers->map(fn (int $number) => [
                'pesantren_id' => $tenantId,
                'title' => "Pengumuman backup {$number}",
                'body' => 'Data uji kelengkapan snapshot.',
                'category' => 'test',
                'audience' => 'all',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });
        $expectedCount = DB::table('announcements')->where('pesantren_id', $tenantId)->count();

        Queue::fake();
        $backupId = $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
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

        DB::table('announcements')->insert([
            'pesantren_id' => $tenantId,
            'title' => 'Dibuat setelah snapshot',
            'body' => 'Baris ini harus hilang setelah restore.',
            'category' => 'test',
            'audience' => 'all',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(BackupRestoreService::class)->restore(DB::table('backups')->find($backupId));

        $this->assertSame($expectedCount, DB::table('announcements')->where('pesantren_id', $tenantId)->count());
        $this->assertDatabaseMissing('announcements', ['pesantren_id' => $tenantId, 'title' => 'Dibuat setelah snapshot']);
    }

    public function test_restore_mutation_rolls_back_when_completion_state_cannot_be_committed(): void
    {
        $this->seed();
        Storage::fake('local');
        Queue::fake();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');

        $backupId = $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->postJson('/web-actions/backup/run')
            ->assertAccepted()
            ->json('backup_id');

        $queuedBackup = null;
        Queue::assertPushed(CreateTenantBackupJob::class, function (CreateTenantBackupJob $job) use (&$queuedBackup) {
            $queuedBackup = $job;

            return true;
        });
        $this->assertNotNull($queuedBackup);
        app()->call([$queuedBackup, 'handle']);

        DB::table('announcements')->insert([
            'pesantren_id' => $tenantId,
            'title' => 'Harus tetap ada setelah rollback restore',
            'body' => 'Fault injection pada callback penyelesaian.',
            'category' => 'test',
            'audience' => 'all',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(BackupRestoreService::class)->restore(
                DB::table('backups')->find($backupId),
                fn () => throw new \RuntimeException('Simulasi kegagalan pencatatan status restore.'),
            );
            $this->fail('Restore harus melempar exception fault-injection.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan pencatatan status restore.', $exception->getMessage());
        }

        $this->assertDatabaseHas('announcements', [
            'pesantren_id' => $tenantId,
            'title' => 'Harus tetap ada setelah rollback restore',
        ]);
    }

    public function test_backup_key_rotation_uses_verified_versioned_pointer_swap(): void
    {
        [$tenantId, $backupId] = $this->createCompletedBackupForRotation();
        $before = DB::table('backups')->find($backupId);
        $this->assertNotNull($before);
        $oldPath = $before->file_path;

        $this->assertSame(0, Artisan::call('pesantenku:rotate-backup-keys', ['--tenant' => $tenantId]));

        $after = DB::table('backups')->find($backupId);
        $this->assertNotNull($after);
        $this->assertNotSame($oldPath, $after->file_path);
        $this->assertStringStartsWith($oldPath.'.rotation-', $after->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($after->file_path);
        $contents = Storage::disk('local')->get($after->file_path);
        $this->assertSame(hash('sha256', $contents), $after->checksum_sha256);
        $this->assertNotSame('', app(BackupCipher::class)->decrypt($contents, $after->encryption_provider, $after->encryption_key_id));
        $this->assertDatabaseHas('secret_rotations', ['status' => 'completed']);
    }

    public function test_backup_key_rotation_reports_partial_when_old_object_cleanup_fails(): void
    {
        [$tenantId, $backupId] = $this->createCompletedBackupForRotation();
        $oldPath = DB::table('backups')->where('id', $backupId)->value('file_path');

        $this->app->instance(OperationStorage::class, new class($oldPath) extends OperationStorage
        {
            public function __construct(private readonly string $blockedPath) {}

            public function delete(string $path): bool
            {
                return $path === $this->blockedPath ? false : parent::delete($path);
            }
        });

        $this->assertSame(1, Artisan::call('pesantenku:rotate-backup-keys', ['--tenant' => $tenantId]));

        $after = DB::table('backups')->find($backupId);
        $this->assertNotNull($after);
        $this->assertNotSame($oldPath, $after->file_path);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($after->file_path);
        $rotation = DB::table('secret_rotations')->latest('id')->firstOrFail();
        $this->assertSame('partial', $rotation->status);
        $metadata = json_decode($rotation->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($oldPath, $metadata['orphaned_old_objects'][(string) $backupId]['path']);
    }

    private function createCompletedBackupForRotation(): array
    {
        $this->seed();
        Storage::fake('local');
        Queue::fake();
        config(['backup-security.provider' => 'local', 'pesantrenku.operations.disk' => 'local']);
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $tenantId = (int) DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        $backupId = $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->postJson('/web-actions/backup/run')
            ->assertAccepted()
            ->json('backup_id');

        $queuedBackup = null;
        Queue::assertPushed(CreateTenantBackupJob::class, function (CreateTenantBackupJob $job) use (&$queuedBackup) {
            $queuedBackup = $job;

            return true;
        });
        $this->assertNotNull($queuedBackup);
        app()->call([$queuedBackup, 'handle']);
        $this->assertDatabaseHas('backups', ['id' => $backupId, 'status' => 'success']);

        return [$tenantId, (int) $backupId];
    }
}
