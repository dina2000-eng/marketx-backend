<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-'.uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);
    }

    public function test_user_can_update_name_without_losing_email_or_phone(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'old@example.com',
            'phone' => '0599000000',
        ]);

        /** @var User $user */
        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Updated Name',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'old@example.com',
            'phone' => '0599000000',
        ]);
    }

    public function test_user_can_update_email_without_losing_phone(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'old@example.com',
            'phone' => '0599000000',
        ]);

        /** @var User $user */
        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => $user->name,
                'email' => 'new@example.com',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'phone' => '0599000000',
        ]);
    }

    public function test_user_cannot_use_another_users_email_in_same_tenant(): void
    {
        $tenant = $this->createTenant();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'taken@example.com',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
        ]);

        /** @var User $user */
        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'user@example.com',
        ]);
    }

    public function test_user_cannot_use_another_users_phone_in_same_tenant(): void
    {
        $tenant = $this->createTenant();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '0599111111',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '0599222222',
        ]);

        /** @var User $user */
        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => $user->name,
                'phone' => '0599111111',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '0599222222',
        ]);
    }

    public function test_users_can_use_same_email_in_different_tenants(): void
    {
        $tenant1 = $this->createTenant();
        $tenant2 = $this->createTenant();

        $otherUser = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'email' => 'other@example.com',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
        ]);

        /** @var User $user */
        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => 'Updated Name',
                'email' => 'other@example.com',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'other@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'email' => 'other@example.com',
        ]);
    }

    public function test_user_cannot_use_existing_email_with_uppercase_letters(): void
    {
        $tenant = $this->createTenant();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'taken@example.com',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
        ]);

        $this->actingAs($user)
            ->putJson('/api/auth/profile', [
                'name' => $user->name,
                'email' => 'TAKEN@EXAMPLE.COM',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'user@example.com',
        ]);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->putJson('/api/auth/profile', [
            'name' => 'Updated Name',
        ])->assertUnauthorized();
    }
}
