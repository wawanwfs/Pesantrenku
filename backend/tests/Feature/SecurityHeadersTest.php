<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceAllowedHosts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_csp_allows_the_active_vite_development_server(): void
    {
        $hotFile = public_path('hot');
        $original = is_file($hotFile) ? file_get_contents($hotFile) : null;

        try {
            app()->detectEnvironment(fn () => 'local');
            file_put_contents($hotFile, 'http://[::1]:5173');

            $policy = $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringContainsString("script-src 'self' http:", $policy);
            $this->assertStringContainsString("style-src 'self' http:", $policy);
            $this->assertStringContainsString("'unsafe-inline'", $policy);
            $this->assertStringContainsString('connect-src', $policy);
            $this->assertStringContainsString('ws:', $policy);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
            if ($original === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $original);
            }
        }
    }

    public function test_production_csp_does_not_allow_the_vite_development_server(): void
    {
        $hotFile = public_path('hot');
        $original = is_file($hotFile) ? file_get_contents($hotFile) : null;

        try {
            app()->detectEnvironment(fn () => 'production');
            file_put_contents($hotFile, 'http://[::1]:5173');

            $policy = $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringNotContainsString('http://[::1]:5173', $policy);
            $this->assertStringNotContainsString('ws://[::1]:5173', $policy);
            $this->assertStringNotContainsString("'unsafe-inline'", $policy);
            $this->assertStringContainsString("style-src 'self' https://fonts.bunny.net", $policy);
            $this->assertStringContainsString('upgrade-insecure-requests', $policy);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
            if ($original === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $original);
            }
        }
    }

    public function test_permissions_policy_disables_unused_sensitive_devices(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_allowed_host_enforcement_rejects_untrusted_hosts(): void
    {
        config([
            'pesantrenku.security.enforce_allowed_hosts' => true,
            'pesantrenku.security.allowed_hosts' => ['trusted.test'],
        ]);

        $middleware = app(EnforceAllowedHosts::class);

        try {
            $middleware->handle(
                Request::create('https://evil.test/health'),
                fn () => response('ok'),
            );
            $this->fail('Host yang tidak dipercaya seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(421, $exception->getStatusCode());
        }

        $response = $middleware->handle(
            Request::create('https://trusted.test/health'),
            fn () => response('ok'),
        );
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_authenticated_responses_are_not_cached(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@pesantenku.test')->firstOrFail();

        $response = $this->actingAs($owner)
            ->getJson('/web-api/programs')
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
