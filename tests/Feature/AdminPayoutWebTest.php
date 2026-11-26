<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\SellerWallet;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPayoutWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_payouts(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $store = $this->createStore($tenant);

        $payout = $this->createPayout($store);

        $this->actingAs($admin, 'web')
            ->get(route('admin.payouts'))
            ->assertOk()
            ->assertSee((string) $payout->id)
            ->assertSee($store->name)
            ->assertSee('pending');
    }

    public function test_platform_admin_can_mark_payout_as_paid(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $store = $this->createStore($tenant);

        $wallet = SellerWallet::create([
            'store_id' => $store->id,
            'currency' => 'ILS',
            'available_balance' => 75,
            'total_withdrawn' => 0,
        ]);

        $payout = $this->createPayout($store);

        $this->actingAs($admin, 'web')
            ->post(route('admin.payouts.status', $payout), [
                'status' => 'paid',
                'reference' => 'TX-123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'paid',
            'reference' => 'TX-123',
        ]);

        $this->assertSame(
            '25.00',
            $wallet->fresh()->total_withdrawn
        );
    }

    public function test_paid_payout_cannot_be_marked_as_failed(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createPlatformAdmin();
        $store = $this->createStore($tenant);

        $wallet = SellerWallet::create([
            'store_id' => $store->id,
            'currency' => 'ILS',
            'available_balance' => 75,
            'total_withdrawn' => 25,
        ]);

        $payout = $this->createPayout($store, 'paid');

        $this->actingAs($admin, 'web')
            ->from(route('admin.payouts'))
            ->post(route('admin.payouts.status', $payout), [
                'status' => 'failed',
            ])
            ->assertRedirect(route('admin.payouts'))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'paid',
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

    private function createPayout(
        Store $store,
        string $status = 'pending'
    ): Payout {
        return Payout::create([
            'store_id' => $store->id,
            'amount' => 25,
            'currency' => 'ILS',
            'method' => 'bank',
            'status' => $status,
            'destination_details' => ['account' => '12345'],
            'requested_at' => now(),
        ]);
    }

    public function test_tenant_admin_cannot_access_platform_payouts(): void
{
    $tenant = $this->createTenant();

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'tenant_admin',
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'web')
        ->get(route('admin.payouts'))
       ->assertRedirect(route('admin.login'))
      ->assertSessionHasErrors('login');
}
}