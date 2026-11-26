<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_orders(): void
    {
        [$admin, $tenant, $customer, $store] = $this->createOrderContext();

        $order = $this->createOrder($tenant, $customer);
        VendorOrder::forceCreate([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'total_amount' => 200,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->get(route('admin.orders'));

        $response->assertOk()
            ->assertSee('الطلب #'.$order->id)
            ->assertSee($customer->name)
            ->assertSee($tenant->name);
    }

    public function test_platform_admin_can_update_order_status(): void
    {
        [$admin, $tenant, $customer, $store] = $this->createOrderContext();

        $order = $this->createOrder($tenant, $customer);

        $vendorOrder = VendorOrder::forceCreate([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'total_amount' => 200,
        ]);

        $response = $this->actingAs($admin, 'web')
            ->post(route('admin.orders.status', $order), [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('vendor_orders', [
            'id' => $vendorOrder->id,
            'status' => 'confirmed',
        ]);
    }

    private function createOrderContext(): array
    {
        $tenant = Tenant::forceCreate([
            'name' => 'Order Web Tenant',
            'slug' => 'order-web-tenant',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'default_language' => 'ar',
            'timezone' => 'Asia/Amman',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'platform_admin',
            'status' => 'active',
        ]);

        $customer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'vendor_owner',
            'status' => 'active',
        ]);

        $store = Store::forceCreate([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => 'Order Web Store',
            'slug' => 'order-web-store',
            'status' => 'active',
        ]);

        return [$admin, $tenant, $customer, $store];
    }

    private function createOrder(Tenant $tenant, User $customer): Order
    {
        $order = Order::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'status' => 'pending',
            'total_amount' => 200,
            'currency' => 'ILS',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_address' => 'Test Address',
            'country_code' => 'PS',
        ]);

        Payment::forceCreate([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'method' => 'cod',
            'status' => 'unpaid',
            'amount' => 200,
            'currency' => 'ILS',
        ]);

        return $order;
    }
}
