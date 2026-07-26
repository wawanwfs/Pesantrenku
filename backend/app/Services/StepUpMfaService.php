<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StepUpMfaService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly MfaRecoveryService $recovery,
    ) {}

    public function assert(Request $request, string $purpose = 'access-administration'): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($request->hasSession()) {
            $validUntil = (int) $request->session()->get('step_up_valid_until', 0);
            $version = (int) $request->session()->get('step_up_authz_version', 0);
            $sessionPurpose = (string) $request->session()->get('step_up_purpose', '');
            if (
                $validUntil >= now()->timestamp
                && $version === (int) ($user->authz_version ?? 1)
                && $sessionPurpose === $purpose
            ) {
                return;
            }
        }

        abort_unless($user->mfa_enabled && filled($user->mfa_secret), 403, 'MFA wajib diaktifkan sebelum mengelola akses.');

        $factor = trim($request->string('mfa_code')->toString());
        $valid = preg_match('/^\d{6}$/', $factor)
            ? $this->totp->verifyAndConsume($user, $factor, $purpose)
            : $this->recovery->consume($user, $factor);

        if (! $valid) {
            throw ValidationException::withMessages(['mfa_code' => 'Kode MFA atau recovery tidak valid.']);
        }

        if ($request->hasSession()) {
            $request->session()->put('step_up_valid_until', now()->addMinutes(5)->timestamp);
            $request->session()->put('step_up_authz_version', (int) ($user->authz_version ?? 1));
            $request->session()->put('step_up_purpose', $purpose);
        }
    }
}
