<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->status === 'active') {
            return $next($request);
        }

        if ($request->is('api/*')) {
            $user->currentAccessToken()?->delete();

            abort(403, 'Akun sudah tidak aktif.');
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/login')->withErrors(['login' => 'Akun sudah tidak aktif.']);
    }
}
