<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;

class AdminVendorRequestWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_approve_vendor_request_and_create_store(): void
    {
        $tenant = Tenant::create([
            'name' => 'Vendo',
            'slug' => 'vendo',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'platform_admin',
            'status' => 'active',
        ]);

        $vendor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $vendorRequest = VendorRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $vendor->id,
            'business_name' => 'Electronics',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.vendor-requests.approve', $vendorRequest));

        $response->assertRedirect();

        $this->assertDatabaseHas('vendor_requests', [
            'id' => $vendorRequest->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $vendor->id,
            'role' => 'vendor_owner',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->id,
            'owner_id' => $vendor->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => 'active',
        ]);
    }

    public function test_platform_admin_can_reject_vendor_request(): void
    {
        $tenant = Tenant::create([
            'name' => 'Vendo',
            'slug' => 'vendo',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'platform_admin',
            'status' => 'active',
        ]);

        $vendor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $vendorRequest = VendorRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $vendor->id,
            'business_name' => 'Test Business',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.vendor-requests.reject', $vendorRequest), [
                'rejection_reason' => 'بيانات النشاط غير مكتملة',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vendor_requests', [
            'id' => $vendorRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'بيانات النشاط غير مكتملة',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertNotNull($vendorRequest->fresh()->reviewed_at);
    }

    public function test_platform_admin_can_view_stores(): void
{
    $tenant = $this->createStoreTenant();
    $admin = $this->createPlatformAdmin();
    $store = $this->createTestStore($tenant, 'active');

    $response = $this->actingAs($admin, 'web')
        ->get(route('admin.stores'));

    $response->assertOk()
        ->assertSee($store->name)
        ->assertSee($store->owner->name);
}

public function test_platform_admin_can_approve_pending_store(): void
{
    $tenant = $this->createStoreTenant();
    $admin = $this->createPlatformAdmin();
    $store = $this->createTestStore($tenant, 'pending');

    $response = $this->actingAs($admin, 'web')
        ->post(route('admin.stores.approve', $store));

    $response->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'id' => $store->id,
        'status' => 'active',
        'rejection_reason' => null,
    ]);
}

public function test_platform_admin_can_reject_pending_store(): void
{
    $tenant = $this->createStoreTenant();
    $admin = $this->createPlatformAdmin();
    $store = $this->createTestStore($tenant, 'pending');

    $response = $this->actingAs($admin, 'web')
        ->post(route('admin.stores.reject', $store), [
            'rejection_reason' => 'بيانات المتجر غير مكتملة',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'id' => $store->id,
        'status' => 'rejected',
        'rejection_reason' => 'بيانات المتجر غير مكتملة',
    ]);
}

public function test_platform_admin_can_suspend_active_store(): void
{
    $tenant = $this->createStoreTenant();
    $admin = $this->createPlatformAdmin();
    $store = $this->createTestStore($tenant, 'active');

    $response = $this->actingAs($admin, 'web')
        ->post(route('admin.stores.suspend', $store));

    $response->assertRedirect();

    $this->assertDatabaseHas('stores', [
        'id' => $store->id,
        'status' => 'suspended',
    ]);
}

private function createStoreTenant(): Tenant
{
    return Tenant::create([
        'name' => 'Vendo',
        'slug' => 'vendo',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'status' => 'active',
    ]);
}

private function createPlatformAdmin(): User
{
    return User::factory()->create([
        'tenant_id' => null,
        'role' => 'platform_admin',
        'status' => 'active',
    ]);
}

private function createTestStore(Tenant $tenant, string $status): Store
{
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'vendor_owner',
        'status' => 'active',
    ]);

    return Store::create([
        'tenant_id' => $tenant->id,
        'owner_id' => $owner->id,
        'name' => 'Test Store',
        'slug' => 'test-store',
        'status' => $status,
    ]);
}
}
