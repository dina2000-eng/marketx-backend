<?php

namespace Tests\Feature;

use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPayoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_payout_and_reserve_balance(): void
    {
        [$owner, $store, $wallet] = $this->createVendor(100);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/vendor/payouts', [
                'store_id' => $store->id,
                'amount' => 25,
                'currency' => 'ILS',
                'method' => 'bank',
                'destination_details' => ['account' => '12345'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('payout.status', 'pending')
            ->assertJsonPath('payout.amount', '25.00');

        $this->assertDatabaseHas('payouts', [
            'store_id' => $store->id,
            'amount' => 25,
            'status' => 'pending',
        ]);

        $this->assertSame(
            '75.00',
            $wallet->fresh()->available_balance
        );
    }

    public function test_vendor_cannot_create_payout_above_available_balance(): void
    {
        [$owner, $store, $wallet] = $this->createVendor(20);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/vendor/payouts', [
                'store_id' => $store->id,
                'amount' => 50,
                'currency' => 'ILS',
                'method' => 'bank',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame(
            '20.00',
            $wallet->fresh()->available_balance
        );

        $this->assertDatabaseMissing('payouts', [
            'store_id' => $store->id,
        ]);
    }

    private function createVendor(float $balance): array
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
            'available_balance' => $balance,
            'total_withdrawn' => 0,
        ]);

        return [$owner, $store, $wallet];
    }
}
