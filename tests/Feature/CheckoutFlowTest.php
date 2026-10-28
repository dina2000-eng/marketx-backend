<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Marketplace\VendorEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_checkout_creates_order_reserves_inventory_and_initializes_fake_gateway(): void
    {
        $tenant = \App\Models\Tenant::forceCreate([
            'name' => 'Checkout Tenant',
            'slug' => 'checkout-tenant',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'default_language' => 'ar',
            'timezone' => 'Asia/Amman',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $store = \App\Models\Store::forceCreate([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => 'Checkout Store',
            'slug' => 'checkout-store',
            'status' => 'active',
        ]);

        $product = \App\Models\Product::forceCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Checkout Product',
            'slug' => 'checkout-product',
            'base_price' => 100,
            'currency' => 'ILS',
            'status' => 'approved',
            'has_variants' => true,
        ]);

        $variant = \App\Models\ProductVariant::forceCreate([
            'product_id' => $product->id,
            'name' => 'Default',
            'price' => 100,
            'stock' => 5,
        ]);

        $inventory = \App\Models\Inventory::forceCreate([
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 1,
        ]);

        $cart = Cart::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        \App\Models\CartItem::forceCreate([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 100,
        ]);

        /*
         * Checkout normally creates seller earnings
         * after creating the VendorOrder.
         *
         * We mock only that separate accounting
         * service so this test focuses on the
         * complete checkout/payment flow.
         */
        $earningService = Mockery::mock(
            VendorEarningService::class
        );

        $earningService
            ->shouldReceive('createForVendorOrder')
            ->once();

        $this->app->instance(
            VendorEarningService::class,
            $earningService
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/checkout',
            [
                'shipping_address' => 'Gaza Test Address',
                'country_code' => 'PS',
                'payment_method' => 'online',
            ]
        );

        $response
            ->assertCreated()
            ->assertJson([
                'requires_payment' => true,
            ]);

        /*
         * Parent Order.
         */
        $order = Order::query()->firstOrFail();

        $this->assertSame(
            $tenant->id,
            (int) $order->tenant_id
        );

        $this->assertSame(
            $user->id,
            (int) $order->user_id
        );

        $this->assertSame(
            'pending',
            $order->status
        );

        $this->assertSame(
            'online',
            $order->payment_method
        );

        $this->assertSame(
            'pending',
            $order->payment_status
        );

        $this->assertSame(
            'ILS',
            $order->currency
        );

        $this->assertSame(
            200.0,
            (float) $order->total_amount
        );

        /*
         * Order Item.
         */
        $this->assertDatabaseHas(
            'order_items',
            [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'store_id' => $store->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ]
        );

        /*
         * Online checkout reserves inventory only.
         * Physical quantity must remain unchanged.
         */
        $inventory->refresh();
        $variant->refresh();

        $this->assertSame(
            2,
            (int) $inventory->reserved_quantity
        );

        $this->assertSame(
            5,
            (int) $inventory->quantity
        );

        $this->assertSame(
            5,
            (int) $variant->stock
        );

        /*
         * Payment.
         */
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->firstOrFail();

        $this->assertSame(
            'online',
            $payment->method
        );

        $this->assertSame(
            'fake',
            $payment->gateway
        );

        $this->assertSame(
            'pending',
            $payment->status
        );

        $this->assertSame(
            200.0,
            (float) $payment->amount
        );

        $this->assertSame(
            'ILS',
            $payment->currency
        );

        $this->assertNotNull(
            $payment->gateway_reference
        );

        $this->assertNotNull(
            $payment->expires_at
        );

        /*
         * Vendor split was created.
         */
        $this->assertDatabaseHas(
            'vendor_orders',
            [
                'order_id' => $order->id,
                'store_id' => $store->id,
                'status' => 'pending',
            ]
        );

        /*
         * Successful checkout clears cart.
         */
        $this->assertDatabaseMissing(
            'cart_items',
            [
                'cart_id' => $cart->id,
            ]
        );

        /*
         * Reserving stock must NOT register
         * a physical sale yet.
         */
        $this->assertDatabaseMissing(
            'inventory_transactions',
            [
                'inventory_id' => $inventory->id,
                'type' => 'sale',
            ]
        );

        /*
         * Response should expose created records.
         */
        $response
            ->assertJsonPath(
                'order.id',
                $order->id
            )
            ->assertJsonPath(
                'payment.id',
                $payment->id
            );
    }

    public function test_cod_checkout_creates_order_and_deducts_inventory_immediately(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'COD Checkout Tenant',
        'slug' => 'cod-checkout-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'COD Checkout Store',
        'slug' => 'cod-checkout-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'COD Checkout Product',
        'slug' => 'cod-checkout-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 5,
    ]);

    $inventory = \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
        'price' => 100,
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $earningService
        ->shouldReceive('createForVendorOrder')
        ->once();

    $this->app->instance(
        VendorEarningService::class,
        $earningService
    );

    Sanctum::actingAs($user);

    $response = $this->postJson(
        '/api/checkout',
        [
            'shipping_address' => 'Gaza COD Test Address',
            'country_code' => 'PS',
            'payment_method' => 'cod',
        ]
    );

    $response
        ->assertCreated()
        ->assertJson([
            'requires_payment' => false,
        ]);

    $order = Order::query()->firstOrFail();

    $this->assertSame(
        'pending',
        $order->status
    );

    $this->assertSame(
        'cod',
        $order->payment_method
    );

    $this->assertSame(
        'unpaid',
        $order->payment_status
    );

    $this->assertSame(
        200.0,
        (float) $order->total_amount
    );

    $payment = Payment::query()
        ->where('order_id', $order->id)
        ->firstOrFail();

    $this->assertSame(
        'cod',
        $payment->method
    );

    $this->assertSame(
        'unpaid',
        $payment->status
    );

    $this->assertNull(
        $payment->gateway
    );

    $this->assertNull(
        $payment->expires_at
    );

    /*
     * COD sells immediately.
     */
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        0,
        (int) $inventory->reserved_quantity
    );

    $this->assertSame(
        3,
        (int) $inventory->quantity
    );

    $this->assertSame(
        3,
        (int) $variant->stock
    );

    $this->assertDatabaseHas(
        'inventory_transactions',
        [
            'inventory_id' => $inventory->id,
            'type' => 'sale',
            'quantity' => -2,
        ]
    );

    $this->assertDatabaseHas(
        'order_items',
        [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]
    );

    $this->assertDatabaseHas(
        'vendor_orders',
        [
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => 'pending',
        ]
    );

    /*
     * Cart must be empty after checkout.
     */
    $this->assertDatabaseMissing(
        'cart_items',
        [
            'cart_id' => $cart->id,
        ]
    );
}

public function test_checkout_rejects_insufficient_stock(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Low Stock Tenant',
        'slug' => 'low-stock-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Low Stock Store',
        'slug' => 'low-stock-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Low Stock Product',
        'slug' => 'low-stock-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 1,
    ]);

    $inventory = \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
        'price' => 100,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $inventory->refresh();

    $this->assertSame(1, (int) $inventory->quantity);
    $this->assertSame(0, (int) $inventory->reserved_quantity);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_checkout_rejects_inactive_store(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Inactive Store Tenant',
        'slug' => 'inactive-store-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Inactive Store',
        'slug' => 'inactive-store',
        'status' => 'suspended',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Inactive Store Product',
        'slug' => 'inactive-store-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 5,
    ]);

    \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_checkout_rejects_unapproved_product(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Unapproved Product Tenant',
        'slug' => 'unapproved-product-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Approved Store',
        'slug' => 'approved-store-test',
        'status' => 'active',
    ]);

   $product = \App\Models\Product::forceCreate([
    'tenant_id' => $tenant->id,
    'store_id' => $store->id,
    'name' => 'Rejected Product',
    'slug' => 'rejected-product-test',
    'base_price' => 100,
    'currency' => 'ILS',
    'status' => 'rejected',
    'has_variants' => true,
]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 5,
    ]);

    \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_checkout_rejects_when_active_cart_does_not_exist(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'No Cart Tenant',
        'slug' => 'no-cart-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_checkout_rejects_inactive_variant(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Inactive Variant Tenant',
        'slug' => 'inactive-variant-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Inactive Variant Store',
        'slug' => 'inactive-variant-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Inactive Variant Product',
        'slug' => 'inactive-variant-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Inactive',
        'price' => 100,
        'stock' => 5,
        'is_active' => false,
    ]);

    \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_checkout_rejects_multiple_currencies(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Currency Tenant',
        'slug' => 'currency-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Currency Store',
        'slug' => 'currency-store',
        'status' => 'active',
    ]);

    $product1 = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'ILS Product',
        'slug' => 'ils-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant1 = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product1->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 5,
    ]);

    \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant1->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $product2 = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'USD Product',
        'slug' => 'usd-product',
        'base_price' => 20,
        'currency' => 'USD',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant2 = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product2->id,
        'name' => 'Default',
        'price' => 20,
        'stock' => 5,
    ]);

    \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant2->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product1->id,
        'variant_id' => $variant1->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product2->id,
        'variant_id' => $variant2->id,
        'quantity' => 1,
        'price' => 20,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('payments', 0);
}

public function test_online_checkout_recreates_missing_inventory_and_reserves_stock(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Missing Inventory Tenant',
        'slug' => 'missing-inventory-tenant',
        'country_code' => 'PS',
        'default_currency' => 'ILS',
        'default_language' => 'ar',
        'timezone' => 'Asia/Amman',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $store = \App\Models\Store::forceCreate([
        'tenant_id' => $tenant->id,
        'owner_id' => $user->id,
        'name' => 'Missing Inventory Store',
        'slug' => 'missing-inventory-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Missing Inventory Product',
        'slug' => 'missing-inventory-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 5,
        'is_active' => true,
    ]);

    /*
     * Ensure inventory is really missing.
     */
    \App\Models\Inventory::query()
        ->where('product_variant_id', $variant->id)
        ->delete();

    $this->assertDatabaseMissing('inventories', [
        'product_variant_id' => $variant->id,
    ]);

    $cart = Cart::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    \App\Models\CartItem::forceCreate([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 100,
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $earningService
        ->shouldReceive('createForVendorOrder')
        ->once();

    $this->app->instance(
        VendorEarningService::class,
        $earningService
    );

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout', [
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
        'payment_method' => 'online',
    ]);

    $response
        ->assertCreated()
        ->assertJson([
            'requires_payment' => true,
        ]);

    /*
     * Checkout should recreate Inventory
     * from legacy variant stock.
     */
    $inventory = \App\Models\Inventory::query()
        ->where('product_variant_id', $variant->id)
        ->where('store_id', $store->id)
        ->firstOrFail();

    $this->assertSame(
        5,
        (int) $inventory->quantity
    );

    $this->assertSame(
        1,
        (int) $inventory->reserved_quantity
    );

    $this->assertSame(
        5,
        (int) $inventory->low_stock_threshold
    );

    /*
     * Physical stock is not reduced
     * until payment succeeds.
     */
    $variant->refresh();

    $this->assertSame(
        5,
        (int) $variant->stock
    );

    $this->assertDatabaseCount(
        'orders',
        1
    );

    $this->assertDatabaseCount(
        'payments',
        1
    );

    $this->assertDatabaseCount(
        'vendor_orders',
        1
    );

    $this->assertDatabaseMissing(
        'cart_items',
        [
            'cart_id' => $cart->id,
        ]
    );

    $this->assertDatabaseMissing(
        'inventory_transactions',
        [
            'inventory_id' => $inventory->id,
            'type' => 'sale',
        ]
    );
}
}