<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_helper_detects_assigned_role(): void
    {
        $role = Role::create(['name' => 'guru', 'label' => 'Guru']);
        $user = User::create([
            'name' => 'Guru Test',
            'email' => 'guru-test@example.test',
            'password' => 'password',
        ]);
        $user->roles()->attach($role);

        $this->assertTrue($user->fresh()->load('roles')->hasRole('guru'));
    }
}
