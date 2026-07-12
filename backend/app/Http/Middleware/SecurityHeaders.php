<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('pesantrenku.security.headers_enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $websocketOrigin = trim((string) config('pesantrenku.notifications.websocket_origin'));
            $connectSources = trim("'self' {$websocketOrigin}");
            $response->headers->set('Content-Security-Policy', "default-src 'self'; connect-src {$connectSources}; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; script-src 'self' 'unsafe-inline'; font-src 'self' data: https://fonts.bunny.net; frame-ancestors 'self'");
        }

        return $response;
    }
}
