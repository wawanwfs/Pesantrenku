<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\CanonicalLocalHost;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\RequestObservability;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            RequestObservability::class,
            CanonicalLocalHost::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(append: [RequestObservability::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'idempotency' => IdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
