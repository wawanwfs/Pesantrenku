<?php

use App\Http\Middleware\CanonicalLocalHost;
use App\Http\Middleware\EnforceAllowedHosts;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\RequestObservability;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')))));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($trustedProxies): void {
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->web(append: [
            EnforceAllowedHosts::class,
            SecurityHeaders::class,
            RequestObservability::class,
            EnsureActiveUser::class,
            CanonicalLocalHost::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(append: [EnforceAllowedHosts::class, SecurityHeaders::class, RequestObservability::class, EnsureActiveUser::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'idempotency' => IdempotencyKey::class,
            'authz.current' => EnsureActiveUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'web-api/*', 'web-actions/*'),
        );
        $exceptions->respond(function (Response $response) {
            $request = request();
            $correlationId = $request->attributes->get('correlation_id');
            if (is_string($correlationId) && $correlationId !== '') {
                $response->headers->set('X-Correlation-ID', $correlationId);
            }

            return app(SecurityHeaders::class)->apply($request, $response);
        });
    })->create();
