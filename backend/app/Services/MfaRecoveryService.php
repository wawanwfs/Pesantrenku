<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MfaRecoveryService
{
    public function regenerate(User $user, int $count = 10): array
    {
        $codes = collect(range(1, max(1, min($count, 20))))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();

        DB::transaction(function () use ($user, $codes) {
            DB::table('mfa_recovery_codes')->where('user_id', $user->id)->delete();
            DB::table('mfa_recovery_codes')->insert(collect($codes)->map(fn (string $code) => [
                'user_id' => $user->id,
                'code_hash' => $this->hash($code),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }, 3);

        return $codes;
    }

    public function consume(User $user, string $code): bool
    {
        $normalized = $this->normalize($code);
        if (! preg_match('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $normalized)) {
            return false;
        }

        return DB::table('mfa_recovery_codes')
            ->where('user_id', $user->id)
            ->where('code_hash', $this->hash($normalized))
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'updated_at' => now()]) === 1;
    }

    private function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), (string) config('app.key'));
    }
}
