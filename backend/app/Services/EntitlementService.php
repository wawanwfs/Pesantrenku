<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntitlementService
{
    private const CONTROL_PLANE_RESOURCES = [
        'pesantrens', 'plans', 'subscriptions', 'tenant-modules', 'memberships',
        'notifications', 'audit-logs', 'system-settings', 'backups',
    ];

    private const RESOURCE_MODULE = [
        'academic-years' => 'akademik', 'programs' => 'akademik', 'subjects' => 'akademik',
        'classes' => 'akademik', 'teachers' => 'akademik', 'teacher-assignments' => 'akademik',
        'schedules' => 'akademik', 'attendances' => 'akademik', 'grades' => 'akademik',
        'report-cards' => 'akademik', 'memorization-records' => 'akademik',
        'assignments' => 'akademik', 'materials' => 'akademik',

        'invoices' => 'keuangan', 'invoice-items' => 'keuangan', 'payments' => 'keuangan',
        'payment-methods' => 'keuangan', 'bank-accounts' => 'keuangan',
        'expense-categories' => 'keuangan', 'expenses' => 'keuangan',
        'cashflow-entries' => 'keuangan', 'discounts' => 'keuangan',

        'students' => 'tata_usaha', 'guardians' => 'tata_usaha', 'staff' => 'tata_usaha',
        'letters' => 'tata_usaha', 'room-mutations' => 'tata_usaha', 'alumni' => 'tata_usaha',

        'rooms' => 'asrama', 'room-assignments' => 'asrama',
        'musyrif-room-assignments' => 'asrama', 'dorm-attendances' => 'asrama',
        'violations' => 'asrama', 'permits' => 'asrama', 'daily-notes' => 'asrama',

        'clinics' => 'klinik', 'doctors' => 'klinik', 'medicines' => 'klinik',
        'medicine-stocks' => 'klinik', 'medical-queues' => 'klinik',
        'medical-records' => 'klinik', 'medical-examinations' => 'klinik',
        'prescriptions' => 'klinik', 'sick-letters' => 'klinik', 'referrals' => 'klinik',
    ];

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function assertResource(Request $request, string $resource): void
    {
        if (! $this->enforced() || $this->tenantContext->isSuperAdmin($request)) {
            return;
        }

        $tenantId = (int) $this->tenantContext->requireActivePesantrenId($request);
        if (in_array($resource, self::CONTROL_PLANE_RESOURCES, true)) {
            if ($this->tenantContext->activeRole($request) === 'owner_pesantren') {
                return;
            }
            $this->assertSubscription($tenantId);

            return;
        }

        $this->assertSubscription($tenantId);
        $this->assertModuleEnabled($tenantId, self::RESOURCE_MODULE[$resource] ?? null);
    }

    public function assertQueuedResource(int $tenantId, string $resource): void
    {
        if (! $this->enforced() || in_array($resource, self::CONTROL_PLANE_RESOURCES, true)) {
            return;
        }

        $this->assertSubscription($tenantId);
        $this->assertModuleEnabled($tenantId, self::RESOURCE_MODULE[$resource] ?? null, true);
    }

    public function assertDashboard(Request $request, string $role): void
    {
        if ($role === 'super-admin' || ! $this->enforced()) {
            return;
        }

        $tenantId = (int) $this->tenantContext->requireActivePesantrenId($request);
        if ($role === 'owner') {
            return;
        }

        $this->assertSubscription($tenantId);
        $this->assertModuleEnabled($tenantId, $this->moduleForPanel($role));
    }

    public function assertModule(Request $request, string $panel, string $module): void
    {
        if ($panel === 'super-admin' || ! $this->enforced()) {
            return;
        }

        $tenantId = (int) $this->tenantContext->requireActivePesantrenId($request);
        if ($panel === 'owner') {
            return;
        }

        $this->assertSubscription($tenantId);
        $this->assertModuleEnabled($tenantId, $this->moduleForDashboardModule($panel, $module));
    }

    /** Must run inside the mutation transaction. */
    public function assertWriteQuota(Request $request, string $resource, int $additionalRows = 1): void
    {
        if ($additionalRows <= 0 || ! $this->enforced() || $this->tenantContext->isSuperAdmin($request)) {
            return;
        }
        if (! in_array($resource, ['students', 'memberships'], true)) {
            return;
        }

        $tenantId = (int) $this->tenantContext->requireActivePesantrenId($request);
        $subscription = $this->activeSubscriptionQuery($tenantId)->lockForUpdate()->first();
        abort_unless($subscription, 402, 'Subscription tenant tidak aktif.');
        $plan = DB::table('plans')->where('id', $subscription->plan_id)->where('status', 'active')->lockForUpdate()->first();
        abort_unless($plan, 402, 'Plan subscription tidak aktif.');

        if ($resource === 'students' && (int) $plan->max_students > 0) {
            $count = DB::table('students')->where('pesantren_id', $tenantId)->whereNull('deleted_at')->count();
            abort_if($count + $additionalRows > (int) $plan->max_students, 409, 'Quota santri pada plan telah tercapai.');
        }
        if ($resource === 'memberships' && (int) $plan->max_users > 0) {
            $count = DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('status', 'active')->distinct('user_id')->count('user_id');
            abort_if($count + $additionalRows > (int) $plan->max_users, 409, 'Quota pengguna pada plan telah tercapai.');
        }
    }

    /** Must run inside the queued mutation transaction. */
    public function assertQueuedWriteQuota(?int $tenantId, string $resource, int $additionalRows): void
    {
        if ($additionalRows <= 0 || ! $this->enforced() || ! $tenantId || ! in_array($resource, ['students', 'memberships'], true)) {
            return;
        }

        $subscription = $this->activeSubscriptionQuery($tenantId)->lockForUpdate()->first();
        if (! $subscription) {
            throw new \RuntimeException('Subscription tenant actor import tidak aktif.');
        }

        $plan = DB::table('plans')->where('id', $subscription->plan_id)->where('status', 'active')->lockForUpdate()->first();
        if (! $plan) {
            throw new \RuntimeException('Plan subscription actor import tidak aktif.');
        }

        if ($resource === 'students' && (int) $plan->max_students > 0) {
            $count = DB::table('students')->where('pesantren_id', $tenantId)->whereNull('deleted_at')->count();
            if ($count + $additionalRows > (int) $plan->max_students) {
                throw new \RuntimeException('Quota santri pada plan telah tercapai saat worker import berjalan.');
            }
        }
        if ($resource === 'memberships' && (int) $plan->max_users > 0) {
            $count = DB::table('pesantren_user')->where('pesantren_id', $tenantId)->where('status', 'active')->distinct('user_id')->count('user_id');
            if ($count + $additionalRows > (int) $plan->max_users) {
                throw new \RuntimeException('Quota pengguna pada plan telah tercapai saat worker import berjalan.');
            }
        }
    }

    public static function moduleAllowlist(): array
    {
        return ['akademik', 'keuangan', 'tata_usaha', 'asrama', 'klinik', 'monitoring'];
    }

    private function moduleForPanel(string $panel): ?string
    {
        return match ($panel) {
            'keuangan' => 'keuangan',
            'akademik', 'guru' => 'akademik',
            'tu' => 'tata_usaha',
            'musyrif' => 'asrama',
            'klinik' => 'klinik',
            default => null,
        };
    }

    private function moduleForDashboardModule(string $panel, string $module): ?string
    {
        $panelModule = $this->moduleForPanel($panel);
        if ($panelModule !== null) {
            return $panelModule;
        }

        return match ($panel) {
            'santri' => match ($module) {
                'akademik', 'raport', 'tahfidz', 'presensi', 'jadwal' => 'akademik',
                'perizinan', 'pelanggaran' => 'asrama',
                'kesehatan' => 'klinik',
                'billing' => 'keuangan',
                default => null,
            },
            'wali-murid' => match ($module) {
                'akademik', 'raport', 'presensi', 'jadwal-anak' => 'akademik',
                'perizinan', 'pelanggaran' => 'asrama',
                'kesehatan' => 'klinik',
                'pembayaran', 'tagihan', 'riwayat-pembayaran' => 'keuangan',
                default => null,
            },
            default => null,
        };
    }

    private function assertModuleEnabled(int $tenantId, ?string $module, bool $throw = false): void
    {
        if ($module === null) {
            return;
        }

        $enabled = DB::table('tenant_modules')
            ->where('pesantren_id', $tenantId)
            ->where('module', $module)
            ->where('is_enabled', true)
            ->exists();
        if ($throw && ! $enabled) {
            throw new \RuntimeException("Modul {$module} actor operasi antrean sudah dinonaktifkan.");
        }
        abort_unless($enabled, 403, "Modul {$module} tidak aktif untuk tenant ini.");
    }

    private function assertSubscription(int $tenantId): void
    {
        abort_unless($this->activeSubscriptionQuery($tenantId)->exists(), 402, 'Subscription tenant tidak aktif atau sudah berakhir.');
    }

    private function activeSubscriptionQuery(int $tenantId)
    {
        return DB::table('subscriptions')
            ->where('pesantren_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('plans')
                    ->whereColumn('plans.id', 'subscriptions.plan_id')
                    ->where('plans.status', 'active');
            })
            ->latest('id');
    }

    private function enforced(): bool
    {
        return (bool) config('pesantrenku.entitlements.enforced', app()->environment('production'));
    }
}
