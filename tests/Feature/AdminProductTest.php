<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_reject_product_that_was_already_reviewed(): void
    {
        $tenant = Tenant::create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'tenant_admin',
        ]);

        $vendor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'vendor_owner',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $vendor->id,
            'name' => 'Company A Store',
            'slug' => 'company-a-store',
            'status' => 'active',
        ]);

        $product = Product::forceCreate([
    'tenant_id' => $tenant->id,
    'store_id' => $store->id,
    'name' => 'Approved Product',
    'slug' => 'approved-product-test',
    'base_price' => 100,
    'currency' => 'ILS',
    'status' => 'approved',
    'has_variants' => false,
]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/reject", [
                'rejection_reason' => 'Test rejection',
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'approved',
            'rejection_reason' => null,
        ]);
    }
}