<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $company = $this->makeCompany();
        $this->makeUser($company, Role::ADMINISTRADOR, ['email' => 'admin@empresa-uno.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@empresa-uno.test',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $company = $this->makeCompany();
        $this->makeUser($company, Role::ADMINISTRADOR, ['email' => 'admin@empresa-uno.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@empresa-uno.test',
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ]);

        $response->assertStatus(422);
    }

    public function test_an_inactive_user_cannot_login(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser($company, Role::ADMINISTRADOR, ['email' => 'admin@empresa-uno.test']);
        $user->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@empresa-uno.test',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_requests_are_rejected_with_json_401(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(401)->assertJson(['message' => 'No autenticado.']);
    }
}
