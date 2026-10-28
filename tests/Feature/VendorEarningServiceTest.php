<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\User;
use App\Models\VendorEarning;
use App\Models\VendorOrder;
use App\Services\Marketplace\VendorEarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorEarningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createVendorOrder(): VendorOrder
    {
        config([
            'marketplace.default_commission_pct' => 10,
        ]);

        $tenant = \App\Models\Tenant::forceCreate([
            'name' => 'Earning Tenant',
            'slug' => 'earning-tenant-' . uniqid(),
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
            'name' => 'Earning Store',
            'slug' => 'earning-store-' . uniqid(),
            'status' => 'active',
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

        return VendorOrder::forceCreate([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'total_amount' => 200,
        ]);
    }

    public function test_vendor_earning_is_created_as_pending(): void
    {
        $vendorOrder = $this->createVendorOrder();

        $service = app(VendorEarningService::class);

        $earning = $service->createForVendorOrder(
            $vendorOrder
        );

        $this->assertSame(
            'pending',
            $earning->status
        );

        $this->assertSame(
            200.0,
            (float) $earning->gross_amount
        );

        $this->assertSame(
            10.0,
            (float) $earning->commission_pct
        );

        $this->assertSame(
            20.0,
            (float) $earning->commission_amount
        );

        $this->assertSame(
            180.0,
            (float) $earning->net_amount
        );

        $this->assertSame(
            'ILS',
            $earning->currency
        );
    }

    public function test_funding_vendor_order_moves_earning_to_pending_wallet_balance(): void
    {
        $vendorOrder = $this->createVendorOrder();

        $service = app(VendorEarningService::class);

        $service->createForVendorOrder(
            $vendorOrder
        );

        $earning = $service->fundVendorOrder(
            $vendorOrder
        );

        $this->assertSame(
            'funded',
            $earning->status
        );

        $wallet = SellerWallet::query()
            ->where(
                'store_id',
                $vendorOrder->store_id
            )
            ->where(
                'currency',
                'ILS'
            )
            ->firstOrFail();

        $this->assertSame(
            180.0,
            (float) $wallet->pending_balance
        );

        $this->assertSame(
            0.0,
            (float) $wallet->available_balance
        );

        $this->assertSame(
            0.0,
            (float) $wallet->total_earned
        );
    }

    public function test_refund_reverses_funded_earning_and_removes_pending_balance(): void
    {
        $vendorOrder = $this->createVendorOrder();

        $service = app(VendorEarningService::class);

        $service->createForVendorOrder(
            $vendorOrder
        );

        $service->fundVendorOrder(
            $vendorOrder
        );

        $earning = $service->refundVendorOrder(
            $vendorOrder
        );

        $this->assertSame(
            'reversed',
            $earning->status
        );

        $wallet = SellerWallet::query()
            ->where(
                'store_id',
                $vendorOrder->store_id
            )
            ->where(
                'currency',
                'ILS'
            )
            ->firstOrFail();

        $this->assertSame(
            0.0,
            (float) $wallet->pending_balance
        );

        $this->assertSame(
            0.0,
            (float) $wallet->available_balance
        );

        $this->assertSame(
            0.0,
            (float) $wallet->total_earned
        );

        $this->assertDatabaseHas(
            'vendor_earnings',
            [
                'vendor_order_id' =>
                    $vendorOrder->id,

                'status' =>
                    'reversed',
            ]
        );
    }
}