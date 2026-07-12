<?php

namespace App\Services;

use App\Models\User;

class TotpService
{
    public function verify(User $user, string $code): bool
    {
        if (! $user->mfa_enabled || ! $user->mfa_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), 30);
        foreach ([-1, 0, 1] as $offset) {
            if (hash_equals($this->code($user->mfa_secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(User $user): string
    {
        return $this->code((string) $user->mfa_secret, intdiv(time(), 30));
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
