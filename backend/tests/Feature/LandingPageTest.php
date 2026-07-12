<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_renders_the_public_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->where('auth.user', null));
    }

    public function test_authenticated_root_still_redirects_to_the_user_dashboard(): void
    {
        $this->seed();
        $user = User::where('email', 'keuangan@pesantenku.test')->firstOrFail();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/dashboard/keuangan');
    }
}
