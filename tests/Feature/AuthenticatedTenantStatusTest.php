<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedTenantStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);
    }

    public function test_existing_token_is_rejected_after_tenant_is_suspended(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
        ]);

        $token = $user->createToken('security-test')->plainTextToken;

        // Token works while tenant is active.
        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertSuccessful();

        // Suspend tenant without deleting the existing token.
        $tenant->update(['status' => 'suspended']);

        // The same token must now be rejected.
        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertForbidden();
    }

    public function test_existing_token_is_rejected_after_tenant_is_deleted(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
        ]);

        $token = $user->createToken('security-test')->plainTextToken;

        $tenant->delete();

        $this->withToken($token)
            ->getJson('/api/cart')
            ->assertForbidden();
    }
}
