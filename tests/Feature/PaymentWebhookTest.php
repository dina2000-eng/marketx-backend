<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Marketplace\VendorEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_paid_webhook_marks_payment_as_paid(): void
    {
        $tenant = \App\Models\Tenant::forceCreate([
            'name' => 'Webhook Tenant',
            'slug' => 'webhook-tenant',
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
            'name' => 'Webhook Store',
            'slug' => 'webhook-store',
            'status' => 'active',
        ]);

        $product = \App\Models\Product::forceCreate([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Webhook Product',
            'slug' => 'webhook-product',
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
            'gateway_reference' => 'FAKE-WEBHOOK-001',
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

        $this->app->instance(
            VendorEarningService::class,
            $earningService
        );

        $response = $this->postJson(
            '/api/payments/webhooks/fake',
            [
                'status' => 'paid',
                'gateway_reference' => 'FAKE-WEBHOOK-001',
                'transaction_id' => 'WEBHOOK-TEST-001',
            ]
        );

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $payment->refresh();
        $order->refresh();
        $inventory->refresh();
        $variant->refresh();

        $this->assertSame(
            'paid',
            $payment->status
        );

        $this->assertSame(
            'WEBHOOK-TEST-001',
            $payment->transaction_id
        );

        $this->assertSame(
            'paid',
            $order->payment_status
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

    public function test_fake_failed_webhook_marks_payment_as_failed_and_releases_inventory(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Failed Webhook Tenant',
        'slug' => 'failed-webhook-tenant',
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
        'name' => 'Failed Webhook Store',
        'slug' => 'failed-webhook-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Failed Webhook Product',
        'slug' => 'failed-webhook-product',
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
        'gateway_reference' => 'FAKE-WEBHOOK-FAILED-001',
        'status' => 'pending',
        'amount' => 200,
        'currency' => 'ILS',
        'idempotency_key' => fake()->uuid(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->postJson(
        '/api/payments/webhooks/fake',
        [
            'status' => 'failed',
            'gateway_reference' => 'FAKE-WEBHOOK-FAILED-001',
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'received' => true,
        ]);

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

public function test_duplicate_paid_webhook_does_not_sell_inventory_twice(): void
{
    $tenant = \App\Models\Tenant::forceCreate([
        'name' => 'Duplicate Webhook Tenant',
        'slug' => 'duplicate-webhook-tenant',
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
        'name' => 'Duplicate Webhook Store',
        'slug' => 'duplicate-webhook-store',
        'status' => 'active',
    ]);

    $product = \App\Models\Product::forceCreate([
        'tenant_id' => $tenant->id,
        'store_id' => $store->id,
        'name' => 'Duplicate Webhook Product',
        'slug' => 'duplicate-webhook-product',
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
        'gateway_reference' => 'FAKE-DUPLICATE-001',
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

    $this->app->instance(
        VendorEarningService::class,
        $earningService
    );

    $payload = [
        'status' => 'paid',
        'gateway_reference' => 'FAKE-DUPLICATE-001',
        'transaction_id' => 'DUPLICATE-TX-001',
    ];

    $this->postJson(
        '/api/payments/webhooks/fake',
        $payload
    )->assertOk();

    $this->postJson(
        '/api/payments/webhooks/fake',
        $payload
    )->assertOk();

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

public function test_unknown_payment_gateway_is_rejected(): void
{
    $response = $this->postJson(
        '/api/payments/webhooks/unknown-gateway',
        [
            'status' => 'paid',
            'gateway_reference' => 'UNKNOWN-001',
            'transaction_id' => 'UNKNOWN-TX-001',
        ]
    );

    $response->assertStatus(400);
}
}