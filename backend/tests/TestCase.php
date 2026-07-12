<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Local npm dev may leave public/hot behind; tests must use built assets deterministically.
        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-vite-hot'));
    }
}
