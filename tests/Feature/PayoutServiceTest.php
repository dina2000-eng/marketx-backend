<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Marketplace\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_paid_increases_total_withdrawn_only_once(): void
    {
        [$store, $wallet] = $this->createStoreWithWallet();

        $service = app(PayoutService::class);

        $payout = $service->request(
            $store,
            25,
            'ILS',
            'bank'
        );

        $this->assertSame('75.00', $wallet->fresh()->available_balance);

        $service->markPaid($payout, 'TX-123');
        $service->markPaid($payout, 'TX-123');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'paid',
            'reference' => 'TX-123',
        ]);

        $this->assertSame(
            '25.00',
            $wallet->fresh()->total_withdrawn
        );

        $this->assertSame(
            '75.00',
            $wallet->fresh()->available_balance
        );
    }

    public function test_failed_payout_returns_funds_only_once(): void
    {
        [$store, $wallet] = $this->createStoreWithWallet();

        $service = app(PayoutService::class);

        $payout = $service->request(
            $store,
            25,
            'ILS',
            'bank'
        );

        $service->markFailed($payout);
        $service->markFailed($payout);

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'failed',
        ]);

        $this->assertSame(
            '100.00',
            $wallet->fresh()->available_balance
        );
    }

    public function test_processing_payout_cannot_be_cancelled(): void
    {
        [$store, $wallet] = $this->createStoreWithWallet();

        $service = app(PayoutService::class);

        $payout = $service->request($store, 25, 'ILS', 'bank');
        $service->markProcessing($payout);

        try {
            $service->cancel($payout);
            $this->fail('A processing payout must not be cancellable.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Only pending payouts can be cancelled.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'processing',
        ]);

        $this->assertSame(
            '75.00',
            $wallet->fresh()->available_balance
        );
    }

    public function test_pending_payout_can_be_cancelled_and_funds_return_only_once(): void
    {
        [$store, $wallet] = $this->createStoreWithWallet();

        $service = app(PayoutService::class);

        $payout = $service->request($store, 25, 'ILS', 'bank');
        $service->cancel($payout);
        $service->cancel($payout);

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'cancelled',
        ]);

        $this->assertSame(
            '100.00',
            $wallet->fresh()->available_balance
        );
    }

    public function test_paid_payout_cannot_be_reversed(): void
    {
        [$store, $wallet] = $this->createStoreWithWallet();

        $service = app(PayoutService::class);

        $payout = $service->request(
            $store,
            25,
            'ILS',
            'bank'
        );

        $service->markPaid($payout);

        $this->assertSame(
            '25.00',
            $wallet->fresh()->total_withdrawn
        );

        $this->expectException(RuntimeException::class);

        $service->markFailed($payout);
    }

    private function createStoreWithWallet(): array
    {
        $tenant = Tenant::create([
            'name' => 'Vendo',
            'slug' => 'vendo',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'vendor_owner',
            'status' => 'active',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'status' => 'active',
        ]);

        $wallet = SellerWallet::create([
            'store_id' => $store->id,
            'currency' => 'ILS',
            'available_balance' => 100,
            'total_withdrawn' => 0,
        ]);

        return [$store, $wallet];
    }
}