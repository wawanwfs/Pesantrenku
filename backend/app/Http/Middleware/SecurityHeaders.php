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

        return $this->apply($request, $response);
    }

    public function apply(Request $request, Response $response): Response
    {
        if (! config('pesantrenku.security.headers_enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        if ($request->user() || $request->is('login', 'logout', 'auth/*', 'api/*', 'web-api/*', 'web-actions/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $websocketOrigin = trim((string) config('pesantrenku.notifications.websocket_origin'));
            $viteSources = $this->viteDevServerSources();
            $connectSources = array_filter(["'self'", $websocketOrigin, ...$viteSources]);
            $assetSources = array_filter(["'self'", $viteSources[0] ?? null]);

            $scriptSources = $assetSources;
            $styleSources = $assetSources;
            if (app()->environment('local')) {
                $scriptSources[] = "'unsafe-inline'";
                $styleSources[] = "'unsafe-inline'";
            }
            $productionDirectives = app()->environment('production') ? '; upgrade-insecure-requests' : '';

            $response->headers->set('Content-Security-Policy', sprintf(
                "default-src 'self'; base-uri 'self'; object-src 'none'; connect-src %s; img-src 'self' data: blob:; style-src %s https://fonts.bunny.net; script-src %s; font-src 'self' data: https://fonts.bunny.net; form-action 'self'; frame-ancestors 'self'%s",
                implode(' ', $connectSources),
                implode(' ', array_unique($styleSources)),
                implode(' ', array_unique($scriptSources)),
                $productionDirectives,
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
