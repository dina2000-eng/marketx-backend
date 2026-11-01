<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProductIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_cannot_view_another_vendors_product_images(): void
    {
        [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

        $response = $this->actingAs($vendorA, 'sanctum')
            ->getJson("/api/vendor/products/{$productB->id}/images");

        $response->assertForbidden();
    }

    public function test_vendor_cannot_view_another_vendors_product_variants(): void
    {
        [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

        $response = $this->actingAs($vendorA, 'sanctum')
            ->getJson("/api/vendor/products/{$productB->id}/variants");

        $response->assertForbidden();
    }

    private function createTwoVendors(): array
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
    'description' => 'Isolation test',
    'base_price' => 20,
    'currency' => 'ILS',
    'has_variants' => false,
    'status' => 'draft',
]);

        return [$vendorA, $vendorB, $productB];
    }


public function test_vendor_cannot_update_another_vendors_product_variant(): void
{
    [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

    $variantB = \App\Models\ProductVariant::create([
        'product_id' => $productB->id,
        'name' => 'Blue',
        'sku' => 'SKU-' . uniqid(),
        'price' => 25,
        'stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->actingAs($vendorA, 'sanctum')
        ->putJson("/api/vendor/variants/{$variantB->id}", [
            'name' => 'Changed by attacker',
            'price' => 1,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('product_variants', [
        'id' => $variantB->id,
        'name' => 'Blue',
    ]);
}

public function test_vendor_cannot_delete_another_vendors_product_variant(): void
{
    [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

    $variantB = \App\Models\ProductVariant::create([
        'product_id' => $productB->id,
        'name' => 'Blue',
        'sku' => 'SKU-' . uniqid(),
        'price' => 25,
        'stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->actingAs($vendorA, 'sanctum')
        ->deleteJson("/api/vendor/variants/{$variantB->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('product_variants', [
        'id' => $variantB->id,
    ]);
}


public function test_vendor_cannot_assign_another_stores_attribute_values_to_variant(): void
{
    [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

    $tenantA = \App\Models\Tenant::where('id', $vendorA->tenant_id)->first();

    $storeA = \App\Models\Store::create([
        'tenant_id' => $tenantA->id,
        'owner_id' => $vendorA->id,
        'name' => 'Vendor A Store',
        'slug' => 'vendor-a-store-' . uniqid(),
        'status' => 'active',
    ]);

    $attributeA = \App\Models\ProductAttribute::create([
        'store_id' => $storeA->id,
        'name' => 'Color',
    ]);

    $valueA = \App\Models\AttributeValue::create([
        'product_attribute_id' => $attributeA->id,
        'value' => 'Red',
    ]);

    $variantB = \App\Models\ProductVariant::create([
        'product_id' => $productB->id,
        'name' => 'Blue',
        'sku' => 'SKU-' . uniqid(),
        'price' => 25,
        'stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->actingAs($vendorB, 'sanctum')
        ->putJson("/api/vendor/variants/{$variantB->id}", [
            'attribute_values' => [$valueA->id],
        ]);

    $this->assertNotSame(
        200,
        $response->status(),
        'Expected cross-store attribute value assignment to be rejected.'
    );

    $this->assertDatabaseMissing('attribute_value_variant', [
        'product_variant_id' => $variantB->id,
        'attribute_value_id' => $valueA->id,
    ]);
}

public function test_vendor_cannot_update_another_vendors_product(): void
{
    [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

    $response = $this->actingAs($vendorA, 'sanctum')
        ->putJson("/api/vendor/products/{$productB->id}", [
            'name' => 'Hacked Product',
            'base_price' => 1,
        ]);

    $response->assertForbidden();

    $this->assertDatabaseHas('products', [
        'id' => $productB->id,
        'name' => 'Vendor B Product',
        'base_price' => 20,
    ]);
}

public function test_vendor_cannot_delete_another_vendors_product(): void
{
    [$vendorA, $vendorB, $productB] = $this->createTwoVendors();

    $response = $this->actingAs($vendorA, 'sanctum')
        ->deleteJson("/api/vendor/products/{$productB->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('products', [
        'id' => $productB->id,
    ]);
}
}