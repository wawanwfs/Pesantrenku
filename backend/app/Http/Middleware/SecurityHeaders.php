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
            $viteSources = $this->viteDevServerSources();
            $connectSources = array_filter(["'self'", $websocketOrigin, ...$viteSources]);
            $assetSources = array_filter(["'self'", $viteSources[0] ?? null]);

            $response->headers->set('Content-Security-Policy', sprintf(
                "default-src 'self'; connect-src %s; img-src 'self' data: blob:; style-src %s 'unsafe-inline' https://fonts.bunny.net; script-src %s 'unsafe-inline'; font-src 'self' data: https://fonts.bunny.net; frame-ancestors 'self'",
                implode(' ', $connectSources),
                implode(' ', $assetSources),
                implode(' ', $assetSources),
            ));
        }

        return $response;
    }

    /**
     * Allow only the active Vite development origin recorded by Laravel's hot file.
     * Production never reads or trusts this local development endpoint.
     *
     * @return list<string>
     */
    private function viteDevServerSources(): array
    {
        if (! app()->environment('local')) {
            return [];
        }

        $hotFile = public_path('hot');
        if (! is_file($hotFile)) {
            return [];
        }

        $url = trim((string) file_get_contents($hotFile));
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return [];
        }

        $host = trim($host, '[]');
        $socketScheme = $scheme === 'https' ? 'wss' : 'ws';

        // Chromium rejects IPv6 literals in CSP host sources. This fallback is
        // local-only and Vite is configured to use IPv4 on its next restart.
        if (str_contains($host, ':')) {
            return ["{$scheme}:", "{$socketScheme}:"];
        }

        $portSuffix = is_int($port) ? ":{$port}" : '';
        $origin = "{$scheme}://{$host}{$portSuffix}";

        return [$origin, "{$socketScheme}://{$host}{$portSuffix}"];
    }
}
