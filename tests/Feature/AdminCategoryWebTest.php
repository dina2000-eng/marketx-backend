<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_categories(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $store = $this->createStore($tenant);

        $category = $this->createCategory($tenant, $store);

        $this->actingAs($admin, 'web')
            ->get(route('admin.categories'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee($store->name);
    }

    public function test_platform_admin_can_toggle_category_status(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $category = $this->createCategory($tenant);

        $this->actingAs($admin, 'web')
            ->post(route('admin.categories.toggle-status', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_deleting_category_preserves_products_and_children(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $store = $this->createStore($tenant);

        $category = $this->createCategory($tenant, $store);

        $child = $this->createCategory(
            $tenant,
            $store,
            $category->id,
            'Child Category'
        );

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'base_price' => 100,
            'currency' => 'ILS',
            'has_variants' => false,
            'status' => 'draft',
        ]);

        $this->actingAs($admin, 'web')
            ->delete(route('admin.categories.delete', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $child->id,
            'parent_id' => null,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => null,
        ]);
    }

    private function createTenant(): Tenant
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

    private function createStore(Tenant $tenant): Store
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
            'status' => 'active',
        ]);
    }

    private function createCategory(
        Tenant $tenant,
        ?Store $store = null,
        ?int $parentId = null,
        string $name = 'Test Category'
    ): Category {
        return Category::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }
}
