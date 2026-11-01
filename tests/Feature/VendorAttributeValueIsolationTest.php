<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\AttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAttributeValueIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_view_own_attribute_values(): void
    {
        [$vendorA, $attributeA] = $this->createTwoVendors();

        AttributeValue::create([
            'product_attribute_id' => $attributeA->id,
            'value' => 'Red',
        ]);

        $this->actingAs($vendorA, 'sanctum')
            ->getJson("/api/vendor/attributes/{$attributeA->id}/values")
            ->assertOk()
            ->assertJsonFragment(['value' => 'Red']);
    }

    public function test_vendor_can_create_own_attribute_value(): void
    {
        [$vendorA, $attributeA] = $this->createTwoVendors();

        $this->actingAs($vendorA, 'sanctum')
            ->postJson("/api/vendor/attributes/{$attributeA->id}/values", [
                'value' => 'Blue',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('attribute_values', [
            'product_attribute_id' => $attributeA->id,
            'value' => 'Blue',
        ]);
    }

    public function test_vendor_cannot_view_another_stores_attribute_values(): void
    {
        [$vendorA, $attributeA, $attributeB] = $this->createTwoVendors();

        $this->actingAs($vendorA, 'sanctum')
            ->getJson("/api/vendor/attributes/{$attributeB->id}/values")
            ->assertForbidden();
    }

    public function test_vendor_cannot_create_value_for_another_stores_attribute(): void
    {
        [$vendorA, $attributeA, $attributeB] = $this->createTwoVendors();

        $this->actingAs($vendorA, 'sanctum')
            ->postJson("/api/vendor/attributes/{$attributeB->id}/values", [
                'value' => 'Hacked Value',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attribute_values', [
            'product_attribute_id' => $attributeB->id,
            'value' => 'Hacked Value',
        ]);
    }

    public function test_vendor_cannot_delete_another_stores_attribute_value(): void
    {
        [$vendorA, $attributeA, $attributeB] = $this->createTwoVendors();

        $valueB = AttributeValue::create([
            'product_attribute_id' => $attributeB->id,
            'value' => 'Green',
        ]);

        $this->actingAs($vendorA, 'sanctum')
            ->deleteJson("/api/vendor/attribute-values/{$valueB->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $valueB->id,
        ]);
    }

    public function test_vendor_can_delete_own_attribute_value(): void
    {
        [$vendorA, $attributeA] = $this->createTwoVendors();

        $value = AttributeValue::create([
            'product_attribute_id' => $attributeA->id,
            'value' => 'Yellow',
        ]);

        $this->actingAs($vendorA, 'sanctum')
            ->deleteJson("/api/vendor/attribute-values/{$value->id}")
            ->assertOk();

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->id,
        ]);
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

        $storeA = Store::create([
            'tenant_id' => $tenantA->id,
            'owner_id' => $vendorA->id,
            'name' => 'Vendor A Store',
            'slug' => 'vendor-a-' . uniqid(),
            'status' => 'active',
        ]);

        $storeB = Store::create([
            'tenant_id' => $tenantB->id,
            'owner_id' => $vendorB->id,
            'name' => 'Vendor B Store',
            'slug' => 'vendor-b-' . uniqid(),
            'status' => 'active',
        ]);

        $attributeA = ProductAttribute::create([
            'store_id' => $storeA->id,
            'name' => 'Color',
        ]);

        $attributeB = ProductAttribute::create([
            'store_id' => $storeB->id,
            'name' => 'Color',
        ]);

        return [$vendorA, $attributeA, $attributeB];
    }

    public function test_vendor_without_store_cannot_access_attribute_values(): void
{
    $tenant = Tenant::create([
        'name' => 'Tenant No Store',
        'slug' => 'tenant-no-store-' . uniqid(),
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'status' => 'active',
    ]);

    $vendor = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'vendor_owner',
    ]);

    [$vendorA, $attributeA] = $this->createTwoVendors();
/** @var \App\Models\User $vendor */
    $this->actingAs($vendor, 'sanctum')
        ->getJson("/api/vendor/attributes/{$attributeA->id}/values")
        ->assertForbidden();
}
}