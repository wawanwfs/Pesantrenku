<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MfaRecoveryService;
use App\Services\TotpService;
use App\Services\UserAccessRevoker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MfaController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly MfaRecoveryService $recovery,
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    public function startEnrollment(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'current_mfa' => ['nullable', 'string', 'max:64'],
        ]);
        $user = $request->user()->loadMissing('roles');
        abort_unless(Hash::check($validated['password'], $user->password), 422, 'Password saat ini tidak valid.');

        if ($user->mfa_enabled) {
            abort_unless($this->verifyFactor($user, (string) ($validated['current_mfa'] ?? ''), 'rotate'), 422, 'Kode MFA atau recovery tidak valid.');
        }

        $secret = $this->totp->generateSecret();
        Cache::put($this->pendingKey($user), Crypt::encryptString($secret), now()->addMinutes(10));

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($user, $secret),
            'expires_in_seconds' => 600,
        ]);
    }

    public function confirmEnrollment(Request $request)
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user()->loadMissing('roles');
        $encrypted = Cache::pull($this->pendingKey($user));
        abort_unless(is_string($encrypted), 410, 'Sesi enrollment MFA sudah kedaluwarsa.');
        $secret = Crypt::decryptString($encrypted);
        abort_unless($this->totp->verifySecret($secret, $validated['code']), 422, 'Kode MFA tidak valid.');

        $codes = DB::transaction(function () use ($user, $secret) {
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled' => true,
                'remember_token' => null,
            ])->save();
            $codes = $this->recovery->regenerate($user);
            $this->accessRevoker->revoke($user);
            $this->audit($user, 'MFA diaktifkan atau dirotasi');

            return $codes;
        }, 3);

        $this->terminateCurrentAuthentication($request);

        return response()->json([
            'message' => 'MFA aktif. Simpan recovery code di tempat aman; kode hanya ditampilkan sekali.',
            'recovery_codes' => $codes,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'mfa' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user()->loadMissing('roles');
        abort_unless(Hash::check($validated['password'], $user->password), 422, 'Password saat ini tidak valid.');
        abort_unless($this->verifyFactor($user, $validated['mfa'], 'recovery-regenerate'), 422, 'Kode MFA atau recovery tidak valid.');

        $codes = DB::transaction(function () use ($user) {
            $codes = $this->recovery->regenerate($user);
            $this->accessRevoker->revoke($user);
            $this->audit($user, 'Recovery code MFA dibuat ulang');

            return $codes;
        }, 3);

        $this->terminateCurrentAuthentication($request);

        return response()->json([
            'message' => 'Recovery code lama dicabut. Simpan kode baru; kode hanya ditampilkan sekali.',
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'mfa' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user()->loadMissing('roles');
        abort_if($this->isPrivileged($user), 403, 'MFA tidak dapat dinonaktifkan untuk akun privileged.');
        abort_unless(Hash::check($validated['password'], $user->password), 422, 'Password saat ini tidak valid.');
        abort_unless($this->verifyFactor($user, $validated['mfa'], 'disable'), 422, 'Kode MFA atau recovery tidak valid.');

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'mfa_secret' => null,
                'mfa_enabled' => false,
                'remember_token' => null,
            ])->save();
            DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
            $this->accessRevoker->revoke($user);
            $this->audit($user, 'MFA dinonaktifkan');
        }, 3);

        $this->terminateCurrentAuthentication($request);

        return response()->json(['message' => 'MFA dinonaktifkan; seluruh session dan token API dicabut.']);
    }

    private function terminateCurrentAuthentication(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function isPrivileged(User $user): bool
    {
        $roles = config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);
        if ($user->hasRole($roles)) {
            return true;
        }

        return DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->whereIn('roles.name', $roles)
            ->exists();
    }

    private function verifyFactor(User $user, string $factor, string $purpose): bool
    {
        return preg_match('/^\d{6}$/', $factor)
            ? $this->totp->verifyAndConsume($user, $factor, $purpose)
            : $this->recovery->consume($user, $factor);
    }

    private function pendingKey(User $user): string
    {
        return "pesantenku:mfa-enrollment:{$user->id}";
    }

    private function audit(User $user, string $action): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => null,
            'user_id' => $user->id,
            'action' => $action,
            'module' => 'auth',
            'ip_address' => request()->ip(),
            'metadata' => json_encode(['security_change' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
