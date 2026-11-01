<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorInventoryIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_cannot_view_another_stores_inventory_history(): void
    {
        [$vendorA, $inventoryB] = $this->createInventoryForAnotherVendor();

        $response = $this->actingAs($vendorA, 'sanctum')
            ->getJson(
                "/api/vendor/inventory/{$inventoryB->id}/transactions"
            );

        $response->assertForbidden();
    }

    public function test_vendor_cannot_modify_another_stores_inventory(): void
    {
        [$vendorA, $inventoryB] = $this->createInventoryForAnotherVendor();

        $originalQuantity = $inventoryB->quantity;

        $response = $this->actingAs($vendorA, 'sanctum')
            ->postJson('/api/vendor/inventory/transactions', [
                'inventory_id' => $inventoryB->id,
                'type' => 'purchase',
                'quantity' => 10,
                'note' => 'Isolation test',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('inventories', [
            'id' => $inventoryB->id,
            'quantity' => $originalQuantity,
        ]);

        $this->assertDatabaseMissing('inventory_transactions', [
            'inventory_id' => $inventoryB->id,
            'note' => 'Isolation test',
        ]);
    }

    private function createInventoryForAnotherVendor(): array
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'default_language' => 'ar',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'default_language' => 'ar',
            'status' => 'active',
        ]);

        $vendorA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'vendor_owner',
        ]);

        $vendorB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'role' => 'vendor_owner',
        ]);

        $storeB = Store::create([
            'tenant_id' => $tenantB->id,
            'owner_id' => $vendorB->id,
            'name' => 'Vendor B Store',
            'slug' => 'vendor-b-store-' . uniqid(),
            'status' => 'active',
        ]);

        $productB = Product::forceCreate([
            'tenant_id' => $tenantB->id,
            'store_id' => $storeB->id,
            'name' => 'Vendor B Product',
            'slug' => 'vendor-b-product-' . uniqid(),
            'description' => 'Inventory isolation test',
            'base_price' => 20,
            'currency' => 'ILS',
            'has_variants' => false,
            'status' => 'draft',
        ]);

        $variantB = ProductVariant::create([
            'product_id' => $productB->id,
            'name' => 'Default',
            'sku' => 'SKU-' . uniqid(),
            'price' => 20,
            'stock' => 5,
            'is_active' => true,
        ]);

        $inventoryB = Inventory::create([
            'store_id' => $storeB->id,
            'product_variant_id' => $variantB->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        return [$vendorA, $inventoryB];
    }
}