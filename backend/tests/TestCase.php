<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected const API_DEVICE_ID = 'phpunit-device-0001';

    protected function postApiLogin(array $credentials): TestResponse
    {
        return $this->withHeader('X-Device-Id', self::API_DEVICE_ID)->postJson(
            '/api/v1/auth/login',
            array_merge([
                'device_id' => self::API_DEVICE_ID,
                'device_name' => 'PHPUnit device',
            ], $credentials),
        );
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard)->withSession([
            'authz_version' => (int) ($user->authz_version ?? 1),
        ]);
    }

    protected function actingAsWithStepUp(
        User $user,
        string $purpose = 'membership-administration',
        $guard = null,
    ) {
        return $this->actingAs($user, $guard)->withSession([
            'step_up_valid_until' => now()->addMinutes(5)->timestamp,
            'step_up_authz_version' => (int) ($user->authz_version ?? 1),
            'step_up_purpose' => $purpose,
        ]);
    }

    public function withToken($token, $type = 'Bearer')
    {
        return parent::withToken($token, $type)->withHeader('X-Device-Id', self::API_DEVICE_ID);
    }

    protected function registerQrScanner(User $scanner, int $tenantId): array
    {
        config(['pesantrenku.qr.allowed_networks' => ['127.0.0.1/32']]);

        return $this->withToken($this->qrAccessToken($scanner))
            ->withHeaders($this->qrScannerHeaders($tenantId))
            ->postJson('/api/v1/qr/scanner/register', ['device_name' => 'PHPUnit scanner'])
            ->assertCreated()
            ->json();
    }

    protected function requestQrChallenge(User $scanner, int $tenantId, string $code): array
    {
        return $this->withToken($this->qrAccessToken($scanner))
            ->withHeaders($this->qrScannerHeaders($tenantId))
            ->postJson('/api/v1/qr/scan/challenge', ['code' => $code])
            ->assertCreated()
            ->json();
    }

    protected function submitQrScan(User $scanner, int $tenantId, string $code, array $registration, array $challenge): TestResponse
    {
        $proof = hash_hmac('sha256', $challenge['proof_message'], $registration['scanner_secret']);

        return $this->withToken($this->qrAccessToken($scanner))
            ->withHeaders($this->qrScannerHeaders($tenantId))
            ->postJson('/api/v1/qr/scan', [
                'code' => $code,
                'challenge_id' => $challenge['challenge_id'],
                'nonce' => $challenge['nonce'],
                'proof' => $proof,
            ]);
    }

    protected function qrScannerHeaders(int $tenantId): array
    {
        return [
            'X-Device-Id' => self::API_DEVICE_ID,
            'X-Device-Name' => 'PHPUnit scanner',
            'X-Pesantren-Id' => (string) $tenantId,
        ];
    }

    protected function qrAccessToken(User $scanner): string
    {
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $token = $scanner->createToken('PHPUnit scanner', ['mobile:access']);
        $token->accessToken->forceFill([
            'authz_version' => (int) ($scanner->authz_version ?? 1),
            'device_id' => self::API_DEVICE_ID,
            'device_name' => 'PHPUnit scanner',
        ])->save();

        return $token->plainTextToken;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Local npm dev may leave public/hot behind; tests must use built assets deterministically.
        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-vite-hot'));
    }
}
