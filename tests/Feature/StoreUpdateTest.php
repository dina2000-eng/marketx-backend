<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_rejected_store_resubmits_it_for_review(): void
    {
        $tenant = Tenant::create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);

        $vendor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'vendor_owner',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'owner_id' => $vendor->id,
            'name' => 'Rejected Store',
            'slug' => 'rejected-store',
            'country_code' => 'PS',
            'status' => 'rejected',
            'rejection_reason' => 'Incomplete information',
        ]);

        /** @var User $vendor */
        $response = $this->actingAs($vendor, 'sanctum')
            ->putJson('/api/stores/my-store', [
                'name' => 'Updated Store',
                'description' => 'Updated description',
                'country_code' => 'PS',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Updated Store',
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
    }
}