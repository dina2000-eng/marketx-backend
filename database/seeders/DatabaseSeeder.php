<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VendorRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Platform Admin
        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@marketx.test',
            'password' => Hash::make('password'),
            'role' => 'platform_admin',
            'tenant_id' => null,
        ]);

        // Main Tenant
        $tenant = Tenant::create([
            'name' => 'MarketX Gaza',
            'slug' => 'marketx-gaza',
            'country_code' => 'PS',
            'default_currency' => 'USD',
            'default_language' => 'ar',
            'timezone' => 'Asia/Gaza',
            'contact_email' => 'admin@marketx.test',
            'status' => 'active',
        ]);

        // Vendor applicant
        $vendor = User::factory()->create([
            'name' => 'Test Vendor',
            'email' => 'vendor@marketx.test',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'tenant_id' => $tenant->id,
        ]);

        // Vendor Application
        VendorRequest::create([
            'tenant_id' => $tenant->id,
            'user_id' => $vendor->id,
            'business_name' => 'Test Gaza Store',
            'phone' => '0590000000',
            'email' => 'vendor@marketx.test',
            'status' => 'pending',
        ]);

        // Regular customer
        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@marketx.test',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'tenant_id' => $tenant->id,
        ]);
    }
}