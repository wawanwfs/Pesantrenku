<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TotpService
{
    public function verify(User $user, string $code): bool
    {
        return $user->mfa_enabled
            && filled($user->mfa_secret)
            && $this->matchingCounter((string) $user->mfa_secret, $code) !== null;
    }

    public function verifyAndConsume(User $user, string $code, string $purpose = 'default'): bool
    {
        if (! $user->mfa_enabled || ! $user->mfa_secret) {
            return false;
        }

        $counter = $this->matchingCounter((string) $user->mfa_secret, $code);
        if ($counter === null) {
            return false;
        }

        return Cache::add(
            "pesantenku:mfa-replay:{$user->id}:{$counter}",
            true,
            now()->addSeconds(120),
        );
    }

    public function verifySecret(string $secret, string $code): bool
    {
        return $this->matchingCounter($secret, $code) !== null;
    }

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes(max(16, min($bytes, 64))));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'Pesantrenku'));
        $account = rawurlencode((string) ($user->email ?: $user->username ?: $user->id));

        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function currentCode(User $user): string
    {
        return $this->code((string) $user->mfa_secret, intdiv(time(), 30));
    }

    private function matchingCounter(string $secret, string $code): ?int
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $counter = intdiv(time(), 30);
        foreach ([-1, 0, 1] as $offset) {
            $candidate = $counter + $offset;
            if (hash_equals($this->code($secret, $candidate), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    private function code(string $secret, int $counter): string
    {
        $binary = $this->base32Decode($secret);
        $counterBytes = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counterBytes, $binary, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value))) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
