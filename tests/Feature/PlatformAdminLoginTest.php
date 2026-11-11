<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_login_without_tenant(): void
    {
        User::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
            'password' => 'SecurePassword123',
            'role' => 'platform_admin',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/platform-login', [
            'login' => 'admin@example.com',
            'password' => 'SecurePassword123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'platform_admin')
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_non_platform_admin_cannot_use_platform_login(): void
{
    User::create([
        'tenant_id' => null,
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => 'SecurePassword123',
        'role' => 'customer',
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/auth/platform-login', [
        'login' => 'customer@example.com',
        'password' => 'SecurePassword123',
    ]);

    $response->assertUnauthorized();
}
}