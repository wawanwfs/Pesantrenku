<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Illuminate\Http\Request;
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
        $websocketOrigin = parse_url((string) config('pesantrenku.notifications.websocket_origin')) ?: [];
        $websocketScheme = match ($websocketOrigin['scheme'] ?? null) {
            'wss' => 'https',
            'ws' => 'http',
            default => $websocketOrigin['scheme'] ?? config('broadcasting.connections.reverb.options.scheme'),
        };

        if ($user && ($user->hasRole('super_admin') || $memberships->isNotEmpty())) {
            $activePesantren = $tenantContext->activePesantren($request);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $user?->role_names ?? [],
                'memberships' => $memberships,
                'active_pesantren' => $activePesantren,
            ],
            'app' => [
                'name' => 'Pesantenku',
                'academic_year' => '2024/2025',
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
