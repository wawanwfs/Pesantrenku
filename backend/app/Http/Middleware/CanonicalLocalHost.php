<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalLocalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local') || ! $request->isMethodSafe()) {
            return $next($request);
        }

        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $localHosts = ['127.0.0.1', 'localhost'];
        $currentHost = $request->getHost();

        if (! in_array($currentHost, $localHosts, true) || ! in_array($canonicalHost, $localHosts, true) || $currentHost === $canonicalHost) {
            return $next($request);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: $request->getScheme();
        $configuredPort = parse_url((string) config('app.url'), PHP_URL_PORT);
        $port = $configuredPort ?: $request->getPort();
        $portSegment = in_array((int) $port, [80, 443], true) ? '' : ':'.$port;

        return redirect()->away($scheme.'://'.$canonicalHost.$portSegment.$request->getRequestUri());
    }
}
