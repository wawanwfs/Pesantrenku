<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
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
        } finally {
            app()->detectEnvironment(fn () => 'testing');
            if ($original === null) {
                @unlink($hotFile);
            } else {
                file_put_contents($hotFile, $original);
            }
        }
    }
}
