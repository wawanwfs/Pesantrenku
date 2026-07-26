<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\EntitlementService;
use App\Services\TenantContext;
use App\Support\DashboardModuleRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly TenantContext $tenantContext,
        private readonly EntitlementService $entitlements,
    ) {}

    public function dashboard(Request $request, string $panel = 'super-admin')
    {
        $this->authorizePanel($request, $panel);
        $this->entitlements->assertDashboard($request, $panel);

        return Inertia::render('App', [
            'screen' => 'dashboard',
            'panel' => $panel,
            'dashboard' => $this->dashboard->for($panel, $request),
            'shared' => $this->dashboard->shared($request, $panel),
        ]);
    }

    public function module(Request $request, string $panel, string $module)
    {
        $this->authorizePanel($request, $panel);
        abort_unless(DashboardModuleRegistry::allows($panel, $module), 404);
        if (DashboardModuleRegistry::isPlatformDataset($module)) {
            abort_unless($panel === 'super-admin' && $request->user()?->hasRole('super_admin'), 403);
        }
        $this->entitlements->assertModule($request, $panel, $module);

        return Inertia::render('App', [
            'screen' => 'module',
            'panel' => $panel,
            'module' => $module,
            'dashboard' => $this->dashboard->for($panel, $request),
            'shared' => $this->dashboard->shared($request, $panel, $module),
        ]);
    }

    private function authorizePanel(Request $request, string $panel): void
    {
        $required = match ($panel) {
            'owner' => 'owner_pesantren',
            'keuangan' => 'admin_keuangan',
            'akademik' => 'admin_akademik',
            'tu' => 'admin_tu',
            'santri' => 'santri',
            'wali-murid' => 'wali_murid',
            'guru' => 'guru',
            'musyrif' => 'musyrif',
            'klinik' => 'klinik',
            'super-admin' => 'super_admin',
            default => null,
        };

        if ($required === null) {
            abort(404);
        }

        $user = $request->user()->loadMissing('roles');

        if ($required === 'super_admin') {
            abort_unless($user->hasRole('super_admin'), 403);

            return;
        }

        abort_if($user->hasRole('super_admin'), 403, 'Super Admin hanya mengelola panel platform.');

        $this->tenantContext->activePesantrenId($request, $required);
    }
}
