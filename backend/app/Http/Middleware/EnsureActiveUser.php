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
        if (! $user) {
            return $next($request);
        }

        if ($user->status !== 'active') {
            return $this->invalidateAuthentication($request, 'Akun sudah tidak aktif.', 403);
        }

        $currentVersion = (int) ($user->authz_version ?? 1);
        if ($request->is('api/*')) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'getAttribute')) {
                $tokenVersion = (int) ($token->getAttribute('authz_version') ?? 0);
                if ($tokenVersion <= 0 || $tokenVersion !== $currentVersion) {
                    return $this->invalidateAuthentication($request, 'Otorisasi berubah. Silakan masuk kembali.', 401);
                }

                if ((bool) config('pesantrenku.auth.require_mobile_device_binding', true)) {
                    $deviceId = trim((string) $request->header('X-Device-Id'));
                    $tokenDevice = (string) ($token->getAttribute('device_id') ?? '');
                    if ($deviceId === '' || $tokenDevice === '' || ! hash_equals($tokenDevice, $deviceId)) {
                        return $this->invalidateAuthentication($request, 'Token tidak cocok dengan perangkat ini.', 401);
                    }
                }

                if (! $token->can('mobile:access')) {
                    return $this->invalidateAuthentication($request, 'Token tidak memiliki ability mobile yang diperlukan.', 403);
                }
            } elseif ($request->hasSession()) {
                // Sanctum may authenticate a same-origin API request through the
                // web session. That path must obey the same authorization
                // generation contract instead of bypassing token checks.
                $sessionVersion = (int) $request->session()->get('authz_version', 0);
                if ($sessionVersion <= 0 || $sessionVersion !== $currentVersion) {
                    return $this->invalidateAuthentication($request, 'Otorisasi berubah. Silakan masuk kembali.', 401);
                }
            } else {
                return $this->invalidateAuthentication($request, 'Kredensial API tidak memiliki binding otorisasi.', 401);
            }
        } elseif ($request->hasSession()) {
            $sessionVersion = (int) $request->session()->get('authz_version', 0);
            if ($sessionVersion <= 0 || $sessionVersion !== $currentVersion) {
                return $this->invalidateAuthentication($request, 'Otorisasi berubah. Silakan masuk kembali.', 401);
            }
        }

        return $next($request);
    }

    private function invalidateAuthentication(Request $request, string $message, int $status): Response
    {
        if ($request->is('api/*')) {
            $accessToken = $request->user()?->currentAccessToken();
            if ($accessToken && method_exists($accessToken, 'getAttribute') && method_exists($accessToken, 'delete')) {
                $accessToken->delete();
            } else {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
            abort($status, $message);
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/login')->withErrors(['login' => $message]);
    }
}
