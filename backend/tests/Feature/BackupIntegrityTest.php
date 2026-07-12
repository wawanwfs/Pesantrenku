<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $backupId = $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->postJson('/web-actions/backup/run')
            ->assertOk()
            ->json('backup_id');

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
}
