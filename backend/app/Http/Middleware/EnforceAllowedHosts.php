<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAllowedHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pesantrenku.security.enforce_allowed_hosts', app()->environment('production'))) {
            return $next($request);
        }

        $allowedHosts = collect(config('pesantrenku.security.allowed_hosts', []))
            ->filter(fn ($host) => is_string($host) && trim($host) !== '')
            ->map(fn (string $host) => strtolower(trim($host)))
            ->unique();

        abort_unless(
            $allowedHosts->contains(strtolower($request->getHost())),
            421,
            'Host tidak diizinkan.',
        );

        return $next($request);
    }
}
