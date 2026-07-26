<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user()?->load('roles');
        $tenantContext = app(TenantContext::class);
        $memberships = $user ? $tenantContext->memberships($request) : collect();
        $activePesantren = null;
        $academicYear = null;
        $websocketOrigin = parse_url((string) config('pesantrenku.notifications.websocket_origin')) ?: [];
        $websocketScheme = match ($websocketOrigin['scheme'] ?? null) {
            'wss' => 'https',
            'ws' => 'http',
            default => $websocketOrigin['scheme'] ?? config('broadcasting.connections.reverb.options.scheme'),
        };

        if ($user && ($user->hasRole('super_admin') || $memberships->isNotEmpty())) {
            $activePesantren = $tenantContext->activePesantren($request);
            if ($activePesantren) {
                $academicYear = DB::table('academic_years')
                    ->where('pesantren_id', $activePesantren->id)
                    ->orderByDesc('is_active')
                    ->orderByDesc('start_date')
                    ->value('name');
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $user?->role_names ?? [],
                'memberships' => $memberships,
                'active_pesantren' => $activePesantren,
                'active_role' => $user ? $tenantContext->activeRole($request) : null,
            ],
            'app' => [
                'name' => 'Pesantrenku',
                'environment' => app()->environment(),
                'demo_enabled' => app()->environment(['local', 'demo']),
                'demo_accounts' => app()->environment(['local', 'demo']) ? [
                    ['email' => 'superadmin@pesantenku.test', 'role' => 'Super Admin'],
                    ['email' => 'owner@pesantenku.test', 'role' => 'Owner Pesantren'],
                    ['email' => 'keuangan@pesantenku.test', 'role' => 'Keuangan'],
                    ['email' => 'akademik@pesantenku.test', 'role' => 'Akademik'],
                    ['email' => 'tu@pesantenku.test', 'role' => 'Tata Usaha'],
                    ['email' => 'santri@pesantenku.test', 'role' => 'Santri'],
                    ['email' => 'wali@pesantenku.test', 'role' => 'Wali Santri'],
                    ['email' => 'guru@pesantenku.test', 'role' => 'Guru'],
                    ['email' => 'musyrif@pesantenku.test', 'role' => 'Musyrif'],
                    ['email' => 'klinik@pesantenku.test', 'role' => 'Klinik'],
                ] : [],
                'academic_year' => $academicYear,
                'notifications' => [
                    'sse_enabled' => config('pesantrenku.notifications.sse_enabled'),
                    'websocket_enabled' => config('pesantrenku.notifications.websocket_enabled'),
                    'polling_seconds' => config('pesantrenku.notifications.polling_seconds'),
                    'websocket' => [
                        'key' => config('broadcasting.connections.reverb.key'),
                        'host' => $websocketOrigin['host'] ?? config('broadcasting.connections.reverb.options.host'),
                        'port' => $websocketOrigin['port'] ?? ($websocketScheme === 'https' ? 443 : 80),
                        'scheme' => $websocketScheme,
                    ],
                ],
            ],
        ];
    }
}
