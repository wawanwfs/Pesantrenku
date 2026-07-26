<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\EntitlementService;
use App\Services\TenantContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly TenantContext $tenantContext,
        private readonly EntitlementService $entitlements,
    ) {}

    public function show(Request $request, string $role)
    {
        $required = match ($role) {
            'super-admin' => 'super_admin',
            'owner' => 'owner_pesantren',
            'keuangan' => 'admin_keuangan',
            'akademik' => 'admin_akademik',
            'tu' => 'admin_tu',
            'wali-murid' => 'wali_murid',
            'santri', 'guru', 'musyrif', 'klinik' => str_replace('-', '_', $role),
            default => null,
        };
        abort_if($required === null, 404);

        $user = $request->user()->loadMissing('roles');

        if ($required === 'super_admin') {
            abort_unless($user->hasRole('super_admin'), 403);
        } else {
            abort_if($user->hasRole('super_admin'), 403);
            $this->tenantContext->activePesantrenId($request, $required);
        }

        $this->entitlements->assertDashboard($request, $role);

        return response()->json([
            'data' => $this->dashboard->for($role, $request),
            'shared' => $this->dashboard->shared($request, $role),
        ]);
    }
}
