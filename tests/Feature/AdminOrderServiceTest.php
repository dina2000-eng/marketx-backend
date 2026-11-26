<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorEarning;
use App\Models\VendorOrder;
use App\Models\VendorOrderItem;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Orders\AdminOrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_cod_order_restores_inventory_and_reverses_earning(): void
    {
        $tenant = Tenant::forceCreate([
            'name' => 'Order Test Tenant',
            'slug' => 'order-test-tenant',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'default_language' => 'ar',
            'timezone' => 'Asia/Amman',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $customer = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $store = Store::forceCreate([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => 'Order Test Store',
            'slug' => 'order-test-store',
            'status' => 'active',
        ]);

        $product = Product::forceCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Order Test Product',
            'slug' => 'order-test-product',
            'base_price' => 100,
            'currency' => 'ILS',
            'status' => 'approved',
            'has_variants' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'name' => 'Default',
            'price' => 100,
            'stock' => 3,
        ]);

        $inventory = Inventory::forceCreate([
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

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

        $orderItem = OrderItem::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 100,
        ]);

        $vendorOrder = VendorOrder::forceCreate([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'total_amount' => 200,
        ]);

        VendorOrderItem::forceCreate([
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 100,
            'total' => 200,
        ]);

        $earningService = app(VendorEarningService::class);

        $earningService->createForVendorOrder($vendorOrder);

        $service = app(AdminOrderService::class);

        $updatedOrder = $service->updateStatus(
            $order,
            'cancelled',
            app(PaymentService::class),
            $earningService
        );

        $this->assertSame('cancelled', $updatedOrder->status);

        $this->assertSame(
            'cancelled',
            $vendorOrder->fresh()->status
        );

        $this->assertSame(
            'reversed',
            VendorEarning::query()
                ->where('vendor_order_id', $vendorOrder->id)
                ->firstOrFail()
                ->status
        );

        $this->assertSame(5, (int) $inventory->fresh()->quantity);
        $this->assertSame(5, (int) $variant->fresh()->stock);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_id' => $inventory->id,
            'type' => 'return',
            'quantity' => 2,
            'note' => 'Admin cancelled order #'.$order->id,
        ]);
    }
}
