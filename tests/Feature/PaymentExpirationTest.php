<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Payments\PaymentService;
use Mockery;

class PaymentExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pending_online_payment_is_cancelled(): void
    {
        $user = User::factory()->create();

        $order = Order::forceCreate([
    'user_id' => $user->id,
    'tenant_id' => 1,
    'status' => 'pending',
    'total_amount' => 100,
    'currency' => 'ILS',
    'payment_method' => 'online',
    'payment_status' => 'pending',
    'shipping_address' => 'Test Address',
    'country_code' => 'PS',
]);

        $payment = Payment::forceCreate([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'online',
            'gateway' => 'fake',
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'ILS',
            'idempotency_key' => fake()->uuid(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('payments:expire')
            ->assertSuccessful();

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            'cancelled',
            $payment->status
        );

        $this->assertSame(
            'cancelled',
            $order->status
        );

        $this->assertSame(
            'cancelled',
            $order->payment_status
        );
    }

    public function test_non_expired_online_payment_stays_pending(): void
    {
        $user = User::factory()->create();

        $order = Order::forceCreate([
            'user_id' => $user->id,
            'tenant_id' => 1,
            'status' => 'pending',
            'total_amount' => 100,
            'currency' => 'ILS',
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'shipping_address' => 'Test Address',
            'country_code' => 'PS',
        ]);

        $payment = Payment::forceCreate([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'online',
            'gateway' => 'fake',
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'ILS',
            'idempotency_key' => fake()->uuid(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->artisan('payments:expire')
            ->assertSuccessful();

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            'pending',
            $payment->status
        );

        $this->assertSame(
            'pending',
            $order->status
        );

        $this->assertSame(
            'pending',
            $order->payment_status
        );
    }
    public function test_expired_payment_releases_reserved_inventory(): void
{

    $tenant = \App\Models\Tenant::forceCreate([
    'name' => 'Test Tenant',
    'slug' => 'test-tenant',
    'country_code' => 'PS',
    'default_currency' => 'ILS',
    'default_language' => 'ar',
    'timezone' => 'Asia/Amman',
    'status' => 'active',
]);

$user = User::factory()->create([
    'tenant_id' => $tenant->id,
]);

$order = Order::forceCreate([
    'user_id' => $user->id,
    'tenant_id' => $tenant->id,
    'status' => 'pending',
    'total_amount' => 100,
    'currency' => 'ILS',
    'payment_method' => 'online',
    'payment_status' => 'pending',
    'shipping_address' => 'Test Address',
    'country_code' => 'PS',
]);

   $store = \App\Models\Store::forceCreate([
    'tenant_id' => $tenant->id,
    'owner_id' => $user->id,
    'name' => 'Test Store',
    'slug' => 'test-store',
    'status' => 'active',
]);

   $product = \App\Models\Product::forceCreate([
    'tenant_id' => $tenant->id,
    'store_id' => $store->id,
    'name' => 'Test Product',
    'slug' => 'test-product',
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
    'reserved_quantity' => 2,
    'low_stock_threshold' => 1,
]);

   \App\Models\OrderItem::forceCreate([
    'order_id' => $order->id,
    'product_id' => $product->id,
    'store_id' => $store->id,
    'product_variant_id' => $variant->id,
    'product_name' => $product->name,
    'variant_name' => $variant->name,
    'quantity' => 2,
    'price' => 100,
    'total' => 200,
]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'pending',
        'amount' => 100,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('payments:expire')
        ->assertSuccessful();

    $inventory->refresh();
    $payment->refresh();
    $order->refresh();

    $this->assertSame(
        0,
        (int) $inventory->reserved_quantity
    );

    $this->assertSame(
        5,
        (int) $inventory->quantity
    );

    $this->assertSame(
        'cancelled',
        $payment->status
    );

    $this->assertSame(
        'cancelled',
        $order->status
    );
}

public function test_mark_paid_converts_reserved_inventory_into_sale(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Paid Test Tenant',
        'slug' => 'paid-test-tenant',
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
        'name' => 'Paid Test Store',
        'slug' => 'paid-test-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Paid Test Product',
        'slug' => 'paid-test-product',
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
        'reserved_quantity' => 2,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'pending',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $earningService
        ->shouldReceive('fundOrder')
        ->once()
        ->with(Mockery::type(Order::class));

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->markPaid(
        $payment,
        'test-transaction-123'
    );

    $payment->refresh();
    $order->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'paid',
        $payment->status
    );

    $this->assertSame(
        'test-transaction-123',
        $payment->transaction_id
    );

    $this->assertNotNull(
        $payment->paid_at
    );

    $this->assertSame(
        'paid',
        $order->payment_status
    );

    $this->assertSame(
        'pending',
        $order->status
    );

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
}

public function test_mark_failed_releases_reserved_inventory_without_reducing_stock(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Failed Test Tenant',
        'slug' => 'failed-test-tenant',
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
        'name' => 'Failed Test Store',
        'slug' => 'failed-test-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Failed Test Product',
        'slug' => 'failed-test-product',
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
        'reserved_quantity' => 2,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'pending',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->markFailed($payment);

    $payment->refresh();
    $order->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'failed',
        $payment->status
    );

    $this->assertNotNull(
        $payment->failed_at
    );

    $this->assertSame(
        'failed',
        $order->payment_status
    );

    $this->assertSame(
        'pending',
        $order->status
    );

    $this->assertSame(
        0,
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

    $this->assertDatabaseMissing(
        'inventory_transactions',
        [
            'inventory_id' => $inventory->id,
            'type' => 'sale',
        ]
    );
}

public function test_refund_paid_payment_restores_inventory(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Refund Test Tenant',
        'slug' => 'refund-test-tenant',
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
        'name' => 'Refund Test Store',
        'slug' => 'refund-test-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Refund Test Product',
        'slug' => 'refund-test-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 3,
    ]);

    $inventory = \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 3,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'paid',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'paid',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'transaction_id' => 'original-transaction-123',
        'paid_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->refundPayment(
        $payment,
        'refund-transaction-123'
    );

    $payment->refresh();
    $order->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'refunded',
        $payment->status
    );

    $this->assertSame(
    'original-transaction-123',
    $payment->transaction_id
);

    $this->assertNotNull(
        $payment->refunded_at
    );

    $this->assertSame(
        'refunded',
        $order->payment_status
    );

    $this->assertSame(
        'cancelled',
        $order->status
    );

    $this->assertSame(
        0,
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

    $this->assertDatabaseHas(
        'inventory_transactions',
        [
            'inventory_id' => $inventory->id,
            'type' => 'return',
            'quantity' => 2,
        ]
    );
}

public function test_mark_paid_is_idempotent_and_does_not_sell_inventory_twice(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Paid Idempotency Tenant',
        'slug' => 'paid-idempotency-tenant',
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
        'name' => 'Paid Idempotency Store',
        'slug' => 'paid-idempotency-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Paid Idempotency Product',
        'slug' => 'paid-idempotency-product',
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
        'reserved_quantity' => 2,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'pending',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $earningService
        ->shouldReceive('fundOrder')
        ->once()
        ->with(Mockery::type(Order::class));

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->markPaid($payment);
    $paymentService->markPaid($payment);

    $payment->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'paid',
        $payment->status
    );

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

    $this->assertSame(
        1,
        \App\Models\InventoryTransaction::query()
            ->where('inventory_id', $inventory->id)
            ->where('type', 'sale')
            ->count()
    );
}

public function test_mark_failed_is_idempotent(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Failed Idempotency Tenant',
        'slug' => 'failed-idempotency-tenant',
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
        'name' => 'Failed Idempotency Store',
        'slug' => 'failed-idempotency-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Failed Idempotency Product',
        'slug' => 'failed-idempotency-product',
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
        'reserved_quantity' => 2,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'pending',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'pending',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->markFailed($payment);
    $paymentService->markFailed($payment);

    $payment->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'failed',
        $payment->status
    );

    $this->assertSame(
        0,
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
}

public function test_refund_is_idempotent_and_does_not_restore_inventory_twice(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Refund Idempotency Tenant',
        'slug' => 'refund-idempotency-tenant',
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
        'name' => 'Refund Idempotency Store',
        'slug' => 'refund-idempotency-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Refund Idempotency Product',
        'slug' => 'refund-idempotency-product',
        'base_price' => 100,
        'currency' => 'ILS',
        'status' => 'approved',
        'has_variants' => true,
    ]);

    $variant = \App\Models\ProductVariant::forceCreate([
        'product_id' => $product->id,
        'name' => 'Default',
        'price' => 100,
        'stock' => 3,
    ]);

    $inventory = \App\Models\Inventory::forceCreate([
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'quantity' => 3,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 1,
    ]);

    $order = Order::forceCreate([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'total_amount' => 200,
        'currency' => 'ILS',
        'payment_method' => 'online',
        'payment_status' => 'paid',
        'shipping_address' => 'Test Address',
        'country_code' => 'PS',
    ]);

    \App\Models\OrderItem::forceCreate([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'store_id' => $store->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->name,
        'variant_name' => $variant->name,
        'quantity' => 2,
        'price' => 100,
        'total' => 200,
    ]);

    $payment = Payment::forceCreate([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online',
        'gateway' => 'fake',
        'status' => 'paid',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'paid_at' => now()->subMinute(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $earningService = Mockery::mock(
        VendorEarningService::class
    );

    $paymentService = new PaymentService(
        $earningService
    );

    $paymentService->refundPayment($payment);
    $paymentService->refundPayment($payment);

    $payment->refresh();
    $order->refresh();
    $inventory->refresh();
    $variant->refresh();

    $this->assertSame(
        'refunded',
        $payment->status
    );

    $this->assertSame(
        'refunded',
        $order->payment_status
    );

    $this->assertSame(
        'cancelled',
        $order->status
    );

    $this->assertSame(
        5,
        (int) $inventory->quantity
    );

    $this->assertSame(
        5,
        (int) $variant->stock
    );

    $this->assertSame(
        1,
        \App\Models\InventoryTransaction::query()
            ->where('inventory_id', $inventory->id)
            ->where('type', 'return')
            ->count()
    );
}
}