<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantData(string $suffix): Tenant
    {
        $tenant = Tenant::create([
            'name' => "Tenant {$suffix}",
            'slug' => "tenant-{$suffix}",
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'vendor_owner',
        ]);

        $category = Category::forceCreate([
            'tenant_id' => $tenant->id,
            'name' => "Category {$suffix}",
            'slug' => "category-{$suffix}",
        ]);

        $store = Store::forceCreate([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'status' => 'active',
        ]);

        Product::forceCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => "Pending Product {$suffix}",
            'slug' => "pending-product-{$suffix}",
            'base_price' => 100,
            'currency' => 'ILS',
            'status' => 'pending_review',
        ]);

        Product::forceCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => "Approved Product {$suffix}",
            'slug' => "approved-product-{$suffix}",
            'base_price' => 150,
            'currency' => 'ILS',
            'status' => 'approved',
        ]);

        VendorRequest::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'business_name' => "Business {$suffix}",
            'status' => 'pending',
        ]);

        Order::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'status' => 'pending',
            'total_amount' => 100,
            'currency' => 'ILS',
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'shipping_address' => json_encode(['city' => 'Nablus']),
            'country_code' => 'PS',
        ]);

        return $tenant;
    }

    public function test_platform_admin_sees_global_dashboard_statistics(): void
    {
        $this->createTenantData('one');
        $this->createTenantData('two');

        $admin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'platform_admin',
        ]);

        /** @var User $admin */
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertExactJson([
                'pending_products' => 2,
                'total_products' => 4,
                'total_stores' => 2,
                'pending_vendor_requests' => 2,
                'total_orders' => 2,
                'total_categories' => 2,
            ]);
    }

    public function test_tenant_admin_sees_only_own_dashboard_statistics(): void
    {
        $tenantOne = $this->createTenantData('one');
        $this->createTenantData('two');

        $admin = User::factory()->create([
            'tenant_id' => $tenantOne->id,
            'role' => 'tenant_admin',
        ]);

        /** @var User $admin */
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertExactJson([
                'pending_products' => 1,
                'total_products' => 2,
                'total_stores' => 1,
                'pending_vendor_requests' => 1,
                'total_orders' => 1,
                'total_categories' => 1,
            ]);
    }
}