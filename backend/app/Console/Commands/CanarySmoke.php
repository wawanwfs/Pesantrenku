<?php

namespace App\Console\Commands;

use App\Jobs\CanaryRoundTripJob;
use App\Models\User;
use App\Services\OperationStorage;
use App\Services\TotpService;
use App\Support\QueueNames;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CanarySmoke extends Command
{
    protected $signature = 'pesantenku:canary-smoke';

    protected $description = 'Run deterministic in-cluster canary security and infrastructure smoke tests';

    public function handle(OperationStorage $storage, TotpService $totp, BroadcastManager $broadcast): int
    {
        if ((string) config('pesantrenku.observability.metric_identity.track') !== 'canary') {
            $this->error('Canary smoke hanya boleh dijalankan pada revision track canary.');

            return self::FAILURE;
        }

        $base = rtrim((string) env('CANARY_SMOKE_BASE_URL', 'http://127.0.0.1:8000/api/v1'), '/');
        $host = (string) env('CANARY_HEALTH_HOST', 'pesantrenku.ai');
        $login = trim((string) env('CANARY_SMOKE_LOGIN'));
        $password = (string) env('CANARY_SMOKE_PASSWORD');
        $privilegedLogin = trim((string) env('CANARY_SMOKE_PRIVILEGED_LOGIN'));
        $privilegedPassword = (string) env('CANARY_SMOKE_PRIVILEGED_PASSWORD');
        $foreignTenantId = (int) env('CANARY_SMOKE_FOREIGN_TENANT_ID', 0);
        if ($login === '' || $password === '' || $privilegedLogin === '' || $privilegedPassword === '' || $foreignTenantId <= 0) {
            $this->error('CANARY_SMOKE_LOGIN/PASSWORD, privileged credentials, dan FOREIGN_TENANT_ID wajib tersedia melalui secret canary.');

            return self::FAILURE;
        }

        $requestId = Str::uuid()->toString();
        $normalDevice = 'canary-normal-'.$requestId;
        $privilegedDevice = 'canary-priv-'.$requestId;
        $normalToken = null;
        $privilegedToken = null;

        try {
            abort_unless((int) (DB::selectOne('select 1 as ok')->ok ?? 0) === 1, 500, 'Database roundtrip gagal.');

            $path = '.canary/'.$requestId.'.txt';
            abort_unless($storage->put($path, $requestId), 500, 'Storage put gagal.');
            abort_unless(hash_equals($requestId, (string) $storage->get($path)), 500, 'Storage read gagal.');
            abort_unless($storage->delete($path) && ! $storage->exists($path), 500, 'Storage verified delete gagal.');

            $marker = 'pesantenku:canary:queue:'.$requestId;
            Cache::forget($marker);
            CanaryRoundTripJob::dispatch($marker)->onQueue(QueueNames::DEFAULT);
            $deadline = microtime(true) + 30;
            while (! Cache::has($marker) && microtime(true) < $deadline) {
                usleep(250000);
            }
            abort_unless(Cache::pull($marker), 500, 'Queue worker roundtrip gagal.');

            $broadcast->driver()->broadcast(
                ['private-canary.smoke'],
                'canary.smoke',
                ['request_id' => $requestId, 'at' => now()->toIso8601String()],
            );

            $normal = $this->login($base, $host, $login, $password, $normalDevice);
            $normalToken = (string) $normal['token'];
            $activeTenant = (int) data_get($normal, 'active_pesantren.id', 0);
            abort_unless($activeTenant > 0 && $activeTenant !== $foreignTenantId, 500, 'Fixture tenant canary tidak valid.');

            $foreign = Http::withHeaders($this->headers($host, $normalDevice, $normalToken, $foreignTenantId))
                ->get($base.'/auth/me');
            abort_unless(in_array($foreign->status(), [403, 422], true), 500, 'Cross-tenant request tidak ditolak.');

            $idempotency = 'canary-'.$requestId;
            $mutationHeaders = $this->headers($host, $normalDevice, $normalToken, $activeTenant) + ['Idempotency-Key' => $idempotency];
            $first = Http::withHeaders($mutationHeaders)->post($base.'/notifications/read', ['ids' => []]);
            $second = Http::withHeaders($mutationHeaders)->post($base.'/notifications/read', ['ids' => []]);
            abort_unless($first->successful() && $second->successful() && $first->body() === $second->body(), 500, 'Mutasi idempoten tidak konsisten.');

            $privilegedUser = User::query()
                ->where('email', $privilegedLogin)
                ->orWhere('username', $privilegedLogin)
                ->orWhere('phone', $privilegedLogin)
                ->first();
            abort_unless($privilegedUser?->mfa_enabled && filled($privilegedUser->mfa_secret), 500, 'Fixture privileged canary belum memiliki MFA.');
            $withoutMfa = Http::withHeaders($this->headers($host, $privilegedDevice))
                ->post($base.'/auth/login', [
                    'login' => $privilegedLogin,
                    'password' => $privilegedPassword,
                    'device_id' => $privilegedDevice,
                    'device_name' => 'Kubernetes canary smoke',
                ]);
            abort_unless(
                $withoutMfa->status() === 422 && (bool) $withoutMfa->json('mfa_required'),
                500,
                'Login privileged tanpa MFA tidak ditolak secara eksplisit.',
            );
            $privileged = $this->login(
                $base,
                $host,
                $privilegedLogin,
                $privilegedPassword,
                $privilegedDevice,
                $totp->currentCode($privilegedUser),
            );
            $privilegedToken = (string) $privileged['token'];

            $this->logout($base, $host, $normalDevice, $normalToken, $activeTenant);
            $normalToken = null;
            $this->logout($base, $host, $privilegedDevice, $privilegedToken, (int) data_get($privileged, 'active_pesantren.id', 0));
            $privilegedToken = null;

            $this->info('Canary smoke lulus: DB, storage, queue, broadcast, login, MFA, tenant denial, dan idempotency.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($normalToken) {
                $this->logout($base, $host, $normalDevice, $normalToken, 0);
            }
            if ($privilegedToken) {
                $this->logout($base, $host, $privilegedDevice, $privilegedToken, 0);
            }
        }
    }

    private function login(string $base, string $host, string $login, string $password, string $device, ?string $mfa = null): array
    {
        $response = Http::withHeaders($this->headers($host, $device))
            ->post($base.'/auth/login', array_filter([
                'login' => $login,
                'password' => $password,
                'device_id' => $device,
                'device_name' => 'Kubernetes canary smoke',
                'mfa_code' => $mfa,
            ], static fn ($value) => $value !== null));
        abort_unless($response->successful() && filled($response->json('token')), 500, 'Login canary gagal: '.$response->status().' '.$response->body());

        return $response->json();
    }

    private function logout(string $base, string $host, string $device, string $token, int $tenantId): void
    {
        $headers = $this->headers($host, $device, $token, $tenantId > 0 ? $tenantId : null);
        $headers['Idempotency-Key'] = 'logout-'.Str::uuid();
        Http::withHeaders($headers)->post($base.'/auth/logout');
    }

    private function headers(string $host, string $device, ?string $token = null, ?int $tenantId = null): array
    {
        return array_filter([
            'Host' => $host,
            'Accept' => 'application/json',
            'X-Device-Id' => $device,
            'Authorization' => $token ? 'Bearer '.$token : null,
            'X-Pesantren-Id' => $tenantId ? (string) $tenantId : null,
        ]);
    }
}
