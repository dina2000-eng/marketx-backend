<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class AuthTenantStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $status = 'active'): Tenant
    {
        return Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => $status,
        ]);
    }

    public function test_active_tenant_can_register(): void
    {
        $tenant = $this->createTenant('active');

        $response = $this->postJson('/api/auth/register', [
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
        ]);
    }

    public function test_inactive_tenants_are_blocked_from_auth_endpoints(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $endpoints = [
            '/api/auth/register',
            '/api/auth/login',
            '/api/auth/forgot-password',
            '/api/auth/verify-reset-code',
            '/api/auth/reset-password',
        ];

        foreach (['pending', 'suspended', 'cancelled'] as $status) {
            $tenant = $this->createTenant($status);

            foreach ($endpoints as $endpoint) {
                $response = $this->postJson($endpoint, [
                    'tenant_id' => $tenant->id,
                ]);

                $response->assertForbidden();
            }
        }
    }

    public function test_deleted_tenant_is_blocked(): void
    {
        $tenant = $this->createTenant('active');
        $tenant->delete();

        $response = $this->postJson('/api/auth/login', [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertForbidden();
    }

    public function test_inactive_tenant_is_blocked_when_resolved_by_slug(): void
    {
        $tenant = $this->createTenant('suspended');

        $response = $this->postJson('/api/auth/login', [
            'tenant_slug' => $tenant->slug,
        ]);

        $response->assertForbidden();
    }
}
