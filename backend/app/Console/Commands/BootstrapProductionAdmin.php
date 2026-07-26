<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MfaRecoveryService;
use App\Services\UserAccessRevoker;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapProductionAdmin extends Command
{
    protected $signature = 'pesantenku:bootstrap-admin
        {email : Email super admin pertama atau akun privileged existing yang akan diprovisikan MFA}
        {--name=Production Administrator : Nama akun baru}
        {--password-file= : File secret berisi password acak minimal 16 karakter; hanya wajib untuk akun baru}
        {--mfa-secret-file= : File secret berisi Base32 TOTP secret}
        {--if-missing : Buat akun bila belum ada, atau provision MFA akun existing bila belum siap}';

    protected $description = 'One-time super-admin bootstrap or MFA provisioning for an existing privileged account using mounted secret files';

    public function handle(MfaRecoveryService $recovery, UserAccessRevoker $accessRevoker): int
    {
        $seconds = max(30, (int) config('pesantrenku.auth.bootstrap_lock_seconds', 180));

        try {
            return Cache::lock('pesantenku:bootstrap-production-admin', $seconds)
                ->block(5, fn () => $this->bootstrap($recovery, $accessRevoker));
        } catch (LockTimeoutException) {
            $this->error('Bootstrap lain sedang berjalan. Provisioning bersamaan ditolak.');

            return self::FAILURE;
        }
    }

    private function bootstrap(MfaRecoveryService $recovery, UserAccessRevoker $accessRevoker): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $mfaFile = $this->option('mfa-secret-file') ?: config('pesantrenku.auth.bootstrap_mfa_secret_file');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! $mfaFile) {
            $this->error('Email valid dan file MFA secret wajib tersedia.');

            return self::FAILURE;
        }

        try {
            $mfaSecret = strtoupper(preg_replace('/\s+/', '', $this->readSecret($mfaFile)));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if (! preg_match('/^[A-Z2-7]{16,128}$/', $mfaSecret)) {
            $this->error('TOTP secret tidak memenuhi format Base32 yang diwajibkan.');

            return self::FAILURE;
        }

        $existing = User::query()->with('roles')->where('email', $email)->first();
        if ($existing) {
            if (! $this->isPrivileged($existing)) {
                $this->error('Akun existing bukan akun privileged yang dapat diprovisikan oleh command ini.');

                return self::FAILURE;
            }
            if (! $this->option('if-missing')) {
                $this->error('Akun privileged sudah tersedia. Gunakan --if-missing untuk provision MFA secara idempoten.');

                return self::FAILURE;
            }

            [$changed, $codes] = DB::transaction(function () use ($existing, $mfaSecret, $recovery, $accessRevoker) {
                $user = User::query()->lockForUpdate()->findOrFail($existing->id);
                $hasRecovery = DB::table('mfa_recovery_codes')
                    ->where('user_id', $user->id)
                    ->whereNull('used_at')
                    ->exists();
                if ($user->mfa_enabled && filled($user->mfa_secret) && $hasRecovery) {
                    return [false, []];
                }

                $user->forceFill([
                    'mfa_secret' => $mfaSecret,
                    'mfa_enabled' => true,
                    'remember_token' => null,
                ])->save();
                $codes = $recovery->regenerate($user);
                $accessRevoker->revoke($user);
                $this->audit($user->id, 'Production privileged account MFA provisioned', [
                    'existing_account' => true,
                    'roles' => $this->privilegedRoleNames($user),
                ]);

                return [true, $codes];
            }, 3);

            if (! $changed) {
                $this->info('Akun privileged sudah memiliki MFA dan recovery code aktif; tidak ada perubahan.');

                return self::SUCCESS;
            }

            $this->printRecoveryCodes($codes);
            $this->info('MFA akun privileged existing berhasil diprovisikan; seluruh session dan token lama dicabut.');

            return self::SUCCESS;
        }

        $hasAnySuperAdmin = DB::table('users')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'super_admin')
            ->exists();
        if ($hasAnySuperAdmin) {
            if ($this->option('if-missing')) {
                $this->info('Super admin lain sudah tersedia; bootstrap akun baru dilewati.');

                return self::SUCCESS;
            }
            $this->error('Bootstrap ditolak karena super admin lain sudah tersedia.');

            return self::FAILURE;
        }

        $passwordFile = $this->option('password-file') ?: config('pesantrenku.auth.bootstrap_password_file');
        if (! $passwordFile) {
            $this->error('File password secret wajib tersedia untuk membuat akun super admin baru.');

            return self::FAILURE;
        }
        try {
            $password = $this->readSecret($passwordFile);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        if (strlen($password) < 16) {
            $this->error('Password bootstrap minimal 16 karakter.');

            return self::FAILURE;
        }

        [$user, $codes] = DB::transaction(function () use ($email, $password, $mfaSecret, $recovery) {
            $now = now();
            $roleId = DB::table('roles')->where('name', 'super_admin')->value('id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'super_admin',
                    'label' => 'Super Admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $user = User::query()->create([
                'name' => trim((string) $this->option('name')),
                'email' => $email,
                'username' => 'bootstrap-'.Str::lower(Str::random(12)),
                'status' => 'active',
                'password' => Hash::make($password),
                'mfa_secret' => $mfaSecret,
                'mfa_enabled' => true,
            ]);
            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $codes = $recovery->regenerate($user);
            $this->audit($user->id, 'Production super admin bootstrapped', ['one_time_bootstrap' => true]);

            return [$user, $codes];
        }, 3);

        $this->printRecoveryCodes($codes);
        $this->info("Super admin production {$user->email} berhasil dibuat dengan MFA aktif.");

        return self::SUCCESS;
    }

    private function printRecoveryCodes(array $codes): void
    {
        $this->warn('Simpan recovery codes berikut di secret manager. Kode tidak akan ditampilkan lagi:');
        foreach ($codes as $code) {
            $this->line($code);
        }
    }

    private function isPrivileged(User $user): bool
    {
        return $this->privilegedRoleNames($user) !== [];
    }

    private function privilegedRoleNames(User $user): array
    {
        $privileged = config('pesantrenku.auth.privileged_roles', ['super_admin', 'owner_pesantren']);
        $global = $user->roles->pluck('name')->intersect($privileged);
        $tenant = DB::table('pesantren_user')
            ->join('roles', 'roles.id', '=', 'pesantren_user.role_id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->whereIn('roles.name', $privileged)
            ->pluck('roles.name');

        return $global->merge($tenant)->unique()->values()->all();
    }

    private function audit(int $userId, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'pesantren_id' => null,
            'user_id' => $userId,
            'action' => $action,
            'module' => 'auth',
            'ip_address' => null,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function readSecret(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("File secret tidak dapat dibaca: {$path}");
        }

        return trim((string) file_get_contents($path));
    }
}
