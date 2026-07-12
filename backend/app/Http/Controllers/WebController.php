<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly TenantContext $tenantContext,
    ) {}

    public function dashboard(Request $request, string $panel = 'super-admin')
    {
        $this->authorizePanel($request, $panel);

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
