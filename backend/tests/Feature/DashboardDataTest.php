<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_uses_database_counts_instead_of_demo_numbers(): void
    {
        $this->seed();
        $user = User::where('email', 'superadmin@pesantenku.test')->firstOrFail();

        $this->actingAs($user)
            ->get('/dashboard/super-admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.stats.0.value', number_format(DB::table('pesantrens')->whereNull('deleted_at')->count(), 0, ',', '.'))
                ->where('dashboard.stats.1.value', number_format(DB::table('users')->whereNull('deleted_at')->count(), 0, ',', '.'))
                ->where('dashboard.stats.2.value', number_format(DB::table('students')->whereNull('deleted_at')->count(), 0, ',', '.'))
                ->has('dashboard.lineChart', 6));
    }

    public function test_finance_dashboard_totals_match_active_tenant_data(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $user->id)->value('pesantren_id');
        $income = (float) DB::table('payments')->where('pesantren_id', $tenantId)->where('status', 'success')->sum('amount');
        $expected = 'Rp '.number_format($income, 0, ',', '.');

        $this->actingAs($user)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/dashboard/keuangan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.stats.0.value', $expected)
                ->has('dashboard.lineChart', 6));
    }

    public function test_santri_dashboard_profile_and_summary_are_scoped_to_logged_in_user(): void
    {
        $this->seed();
        $user = User::where('email', 'santri@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $user->id)->value('pesantren_id');
        $student = DB::table('students')->where('pesantren_id', $tenantId)->where('user_id', $user->id)->first();

        $this->actingAs($user)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/dashboard/santri')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.profile.name', $user->name)
                ->where('dashboard.students.0.id', $student->id)
                ->has('dashboard.summary', 4));
    }

    public function test_guru_dashboard_uses_human_readable_schedule_relations(): void
    {
        $this->seed();
        $user = User::where('email', 'guru@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $user->id)->value('pesantren_id');

        $this->actingAs($user)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/dashboard/guru')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard.schedules.0', fn (Assert $schedule) => $schedule
                    ->where('kelas', '9A')
                    ->where('mata_pelajaran', 'Bahasa Arab')
                    ->missing('pesantren_id')
                    ->missing('class_id')
                    ->missing('subject_id')
                    ->etc()));
    }

    public function test_inertia_shared_payload_hides_credentials_protected_settings_and_other_users_notifications(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();
        $otherUser = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();
        $tenantId = DB::table('pesantren_user')->where('user_id', $owner->id)->value('pesantren_id');
        DB::table('system_settings')->insert([
            'pesantren_id' => $tenantId,
            'key' => 'permissions.hidden_from_inertia',
            'value' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notification_logs')->insert([
            'pesantren_id' => $tenantId,
            'user_id' => $otherUser->id,
            'title' => 'Notifikasi privat pengguna lain',
            'body' => 'Tidak boleh masuk payload owner.',
            'type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/owner/module/user-dan-role')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shared.users', fn ($users) => collect($users)->every(
                    fn ($user) => ! array_key_exists('password', $user)
                        && ! array_key_exists('remember_token', $user)
                        && ! array_key_exists('mfa_secret', $user)
                ))
                ->where('shared.notifications', fn ($notifications) => collect($notifications)->doesntContain('title', 'Notifikasi privat pengguna lain')));

        $this->actingAs($owner)
            ->withSession(['active_pesantren_id' => $tenantId])
            ->get('/owner/module/pengaturan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shared.settings', fn ($settings) => collect($settings)->every(
                    fn ($setting) => ! str_starts_with($setting['kunci'], 'permissions.')
                )));
    }
}
