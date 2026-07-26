<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user(), 401);
        abort_unless(
            $this->tenantContext->hasActiveRole($request, $roles, in_array('super_admin', $roles, true)),
            403,
            'Role tenant aktif tidak memiliki akses ke modul ini.'
        );

        return $next($request);
    }
}
