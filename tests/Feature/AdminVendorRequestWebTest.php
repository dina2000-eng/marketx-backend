<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
}
