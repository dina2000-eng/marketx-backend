<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreTest extends TestCase
{
    use RefreshDatabase;

    private function createOtherTenantStore(array $storeOverrides = []): array
    {
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        /** @var User $adminA */
        $adminA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'tenant_admin',
        ]);

        /** @var User $vendorB */
        $vendorB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'role' => 'vendor_owner',
        ]);

        $storeB = Store::create(array_merge([
            'tenant_id' => $tenantB->id,
            'owner_id' => $vendorB->id,
            'name' => 'Company B Store',
            'slug' => 'company-b-store',
            'status' => 'pending',
        ], $storeOverrides));

        return [$adminA, $storeB];
    }

    public function test_tenant_admin_cannot_approve_another_tenants_store(): void
    {
        [$adminA, $storeB] = $this->createOtherTenantStore();

        $response = $this->actingAs($adminA, 'sanctum')
            ->postJson("/api/admin/stores/{$storeB->id}/approve");

        $response->assertForbidden();

        $this->assertDatabaseHas('stores', [
            'id' => $storeB->id,
            'tenant_id' => $storeB->tenant_id,
            'status' => 'pending',
        ]);
    }

    public function test_tenant_admin_cannot_reject_another_tenants_store(): void
    {
        [$adminA, $storeB] = $this->createOtherTenantStore();

        $response = $this->actingAs($adminA, 'sanctum')
            ->postJson("/api/admin/stores/{$storeB->id}/reject", [
                'rejection_reason' => 'Test rejection',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('stores', [
            'id' => $storeB->id,
            'status' => 'pending',
        ]);
    }

    public function test_tenant_admin_cannot_suspend_another_tenants_store(): void
    {
        [$adminA, $storeB] = $this->createOtherTenantStore([
            'status' => 'active',
        ]);

        $response = $this->actingAs($adminA, 'sanctum')
            ->postJson("/api/admin/stores/{$storeB->id}/suspend");

        $response->assertForbidden();

        $this->assertDatabaseHas('stores', [
            'id' => $storeB->id,
            'status' => 'active',
        ]);
    }

    public function test_tenant_admin_sees_only_own_tenant_stores(): void
{
    [$adminA, $storeB] = $this->createOtherTenantStore();

    $storeA = Store::create([
        'tenant_id' => $adminA->tenant_id,
        'owner_id' => $adminA->id,
        'name' => 'Company A Store',
        'slug' => 'company-a-store',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($adminA, 'sanctum')
        ->getJson('/api/admin/stores');

    $response->assertOk();

    $storeIds = collect($response->json('stores'))
        ->pluck('id')
        ->all();

    $this->assertContains($storeA->id, $storeIds);
    $this->assertNotContains($storeB->id, $storeIds);
}


public function test_tenant_admin_cannot_update_another_tenants_order(): void
{
    $tenantA = \App\Models\Tenant::forceCreate([
        'name' => 'Tenant A',
        'slug' => 'tenant-a-' . uniqid(),
        'status' => 'active',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
    ]);

    $tenantB = \App\Models\Tenant::forceCreate([
        'name' => 'Tenant B',
        'slug' => 'tenant-b-' . uniqid(),
        'status' => 'active',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',    
    ]);

    /** @var User $admin */
    $admin = \App\Models\User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'tenant_admin',
    
    ]);

    $customer = \App\Models\User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => 'customer',
    
    ]);

    $order = \App\Models\Order::forceCreate([
        'tenant_id' => $tenantB->id,
        'user_id' => $customer->id,
        'status' => 'pending',
        'total_amount' => 100,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => json_encode([
            'address' => 'Test address',
        ]),
        'country_code' => 'PS',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'tenant_id' => $tenantB->id,
        'status' => 'pending',
    ]);
}

public function test_tenant_admin_sees_only_own_tenant_orders(): void
{
    $tenantA = Tenant::forceCreate([
        'name' => 'Tenant A',
        'slug' => 'tenant-a-' . uniqid(),
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'status' => 'active',
    ]);

    $tenantB = Tenant::forceCreate([
        'name' => 'Tenant B',
        'slug' => 'tenant-b-' . uniqid(),
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'status' => 'active',
    ]);

    /** @var User $admin */
    $admin = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'tenant_admin',
    ]);

    /** @var User $customerA */
    $customerA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => 'customer',
    ]);

    /** @var User $customerB */
    $customerB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => 'customer',
    ]);

    $orderA = Order::forceCreate([
        'tenant_id' => $tenantA->id,
        'user_id' => $customerA->id,
        'status' => 'pending',
        'total_amount' => 100,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => json_encode(['address' => 'Address A']),
        'country_code' => 'PS',
    ]);

    $orderB = Order::forceCreate([
        'tenant_id' => $tenantB->id,
        'user_id' => $customerB->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => json_encode(['address' => 'Address B']),
        'country_code' => 'PS',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/orders');

    $response->assertOk();

    $orders = $response->json('orders');

$this->assertContains(
    $orderA->id,
    array_column($orders, 'id')
);

$this->assertNotContains(
    $orderB->id,
    array_column($orders, 'id')
);
}
}