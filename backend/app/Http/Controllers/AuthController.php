<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\MfaRecoveryService;
use App\Services\TenantContext;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TotpService $totp,
        private readonly MfaRecoveryService $mfaRecovery,
    ) {}

    public function loginPage(Request $request)
    {
        if ($request->user()) {
            $role = $this->tenantContext->activeRole($request);
            abort_unless($role, 403, 'Akun tidak memiliki membership tenant aktif.');

            return redirect('/dashboard/'.$this->homeRole($role));
        }

        return Inertia::render('Login');
    }

    public function login(LoginRequest $request)
    {
        $login = $request->string('login')->toString();
        $user = User::with('roles')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->auditAuthentication($request, $user, 'LOGIN_FAILED', [
                'reason' => 'invalid_credentials',
            ]);
            throw ValidationException::withMessages(['login' => 'Email/username/telepon atau password salah.']);
        }

        $memberships = $this->membershipData($user);
        $activePesantren = $memberships->first();
        $activeRole = $user->hasRole('super_admin') ? 'super_admin' : $activePesantren?->role;
        if (! $activeRole) {
            $this->auditAuthentication($request, $user, 'LOGIN_FAILED', [
                'reason' => 'no_active_membership',
            ]);
            abort(403, 'Akun tidak memiliki membership tenant aktif.');
        }

        $mfaRequired = $this->requiresMfa($user, $memberships);
        if ($mfaRequired) {
            if (! $user->mfa_enabled || ! filled($user->mfa_secret)) {
                $this->auditAuthentication($request, $user, 'LOGIN_FAILED', [
                    'reason' => 'privileged_mfa_not_ready',
                    'active_role' => $activeRole,
                ]);
                abort(403, 'Akun privileged belum memiliki MFA aktif. Jalankan prosedur bootstrap atau recovery administrator.');
            }

            $factor = trim($request->string('mfa_code')->toString());
            if ($factor === '' && $request->is('api/*')) {
                return response()->json([
                    'message' => 'Kode MFA atau recovery diperlukan untuk akun privileged.',
                    'mfa_required' => true,
                    'errors' => ['mfa_code' => ['Masukkan kode TOTP atau recovery.']],
                ], 422);
            }
            $validFactor = preg_match('/^\d{6}$/', $factor)
                ? $this->totp->verifyAndConsume($user, $factor, 'login')
                : $this->mfaRecovery->consume($user, $factor);
            if (! $validFactor) {
                $this->auditAuthentication($request, $user, 'LOGIN_FAILED', [
                    'reason' => 'invalid_mfa',
                    'active_role' => $activeRole,
                ]);
                throw ValidationException::withMessages(['mfa_code' => 'Kode MFA atau recovery tidak valid.']);
            }
        }

        $this->auditAuthentication($request, $user, 'LOGIN', [
            'active_role' => $activeRole,
            'mfa' => $mfaRequired,
        ]);

        if ($request->is('api/*')) {
            $deviceId = $request->string('device_id')->toString();
            $deviceName = trim($request->string('device_name')->toString()) ?: 'Perangkat mobile';
            $user->tokens()->where('device_id', $deviceId)->delete();
            $minutes = $mfaRequired
                ? (int) config('pesantrenku.auth.privileged_mobile_token_minutes', 120)
                : (int) config('pesantrenku.auth.mobile_token_minutes', 1440);
            $expiresAt = now()->addMinutes(max(15, $minutes));
            $issued = $user->createToken('mobile:'.$deviceId, ['mobile:access'], $expiresAt);
            $issued->accessToken->forceFill([
                'authz_version' => (int) ($user->authz_version ?? 1),
                'device_id' => $deviceId,
                'device_name' => $deviceName,
            ])->save();

            return response()->json([
                'token' => $issued->plainTextToken,
                'token_expires_at' => $expiresAt->toIso8601String(),
                'user' => $user,
                'roles' => $user->role_names,
                'memberships' => $memberships,
                'active_pesantren' => $activePesantren,
                'active_role' => $activeRole,
                'home' => '/dashboard/'.$this->homeRole($activeRole),
            ]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('authz_version', (int) ($user->authz_version ?? 1));

        if ($activePesantren) {
            $request->session()->put('active_pesantren_id', $activePesantren->id);
        }

        $request->session()->forget('url.intended');

        return redirect('/dashboard/'.$this->homeRole($activeRole));
    }

    public function me(Request $request)
    {
        $activeRole = $this->tenantContext->activeRole($request);
        abort_unless($activeRole, 403, 'Akun tidak memiliki membership tenant aktif.');

        return response()->json([
            'user' => $request->user()->load('roles'),
            'roles' => $request->user()->role_names,
            'memberships' => $this->tenantContext->memberships($request),
            'active_pesantren' => $this->tenantContext->activePesantren($request),
            'active_role' => $activeRole,
            'home' => '/dashboard/'.$this->homeRole($activeRole),
        ]);
    }

    public function sessionStatus(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');

        if (! $user) {
            return response()->json(['authenticated' => false]);
        }

        $activeRole = $this->tenantContext->activeRole($request);
        if (! $activeRole) {
            return response()->json(['authenticated' => false], 403);
        }

        return response()->json([
            'authenticated' => true,
            'home' => '/dashboard/'.$this->homeRole($activeRole),
            'active_role' => $activeRole,
        ]);
    }

    public function switchTenant(Request $request)
    {
        $validated = $request->validate([
            'pesantren_id' => ['required', 'integer'],
        ]);

        $active = $this->tenantContext->setActive($request, (int) $validated['pesantren_id']);

        if ($request->is('api/*')) {
            return response()->json(['active_pesantren' => $active, 'active_role' => $this->tenantContext->activeRole($request), 'home' => '/dashboard/'.$this->homeRole((string) $this->tenantContext->activeRole($request))]);
        }

        return back();
    }

    public function logout(Request $request)
    {
        if ($request->is('api/*')) {
            $request->user()?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Logout berhasil.']);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function requiresMfa(User $user, $memberships): bool
    {
        if (! config('pesantrenku.auth.enforce_privileged_mfa', app()->environment('production'))) {
            return false;
        }

        $privileged = config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);

        if ($user->hasRole($privileged) || $memberships->pluck('role')->intersect($privileged)->isNotEmpty()) {
            return true;
        }

        return DB::table('pesantren_user')
            ->join('tenant_roles', function ($join) {
                $join->on('pesantren_user.tenant_role_id', '=', 'tenant_roles.id')
                    ->on('pesantren_user.pesantren_id', '=', 'tenant_roles.pesantren_id');
            })
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('tenant_roles.status', 'active')
            ->get(['tenant_roles.permissions'])
            ->contains(function ($row) {
                $permissions = json_decode((string) $row->permissions, true) ?: [];

                return ! empty($permissions['write']) || ! empty($permissions['actions']);
            });
    }

    private function auditAuthentication(Request $request, ?User $user, string $action, array $metadata = []): void
    {
        $login = strtolower(trim($request->string('login')->toString()));
        DB::table('audit_logs')->insert([
            'pesantren_id' => null,
            'user_id' => $user?->id,
            'action' => $action,
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'metadata' => json_encode(array_merge([
                'channel' => $request->is('api/*') ? 'api' : 'web',
                'login_hash' => hash_hmac('sha256', $login, (string) config('app.key')),
            ], $metadata), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function homeRole(string $role): string
    {
        return str_replace('_', '-', match ($role) {
            'owner_pesantren' => 'owner',
            'admin_keuangan' => 'keuangan',
            'admin_akademik' => 'akademik',
            'admin_tu' => 'tu',
            default => $role,
        });
    }

    private function membershipData(User $user)
    {
        return DB::table('pesantren_user')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('pesantrens.status', 'active')
            ->whereNull('pesantrens.deleted_at')
            ->select([
                'pesantrens.id',
                'pesantrens.name',
                'pesantrens.city',
                'roles.name as role',
                'roles.label as role_label',
            ])
            ->orderBy('pesantren_user.id')
            ->get();
    }
}
