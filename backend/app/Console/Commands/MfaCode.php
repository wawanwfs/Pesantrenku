<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Console\Command;

class MfaCode extends Command
{
    protected $signature = 'pesantenku:mfa-code {email}';

    protected $description = 'Show a demo TOTP code in non-production environments';

    public function handle(TotpService $totp): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini dinonaktifkan di production.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $this->line($totp->currentCode($user));

        return self::SUCCESS;
    }
}
