<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-'.uniqid(),
            'country_code' => 'PS',
            'default_currency' => 'ILS',
            'status' => 'active',
        ]);
    }

    public function test_password_reset_consumes_token_and_revokes_sessions(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $user->createToken('existing-session');

        $resetToken = 'test-reset-token';

        $resetId = DB::table('password_reset_codes')->insertGetId([
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
            'reset_token_hash' => Hash::make($resetToken),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk();

        $this->assertTrue(
            Hash::check('NewPassword123', $user->fresh()->password)
        );

        $this->assertDatabaseMissing('password_reset_codes', [
            'id' => $resetId,
        ]);

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_password_reset_token_cannot_be_reused(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $resetToken = 'test-reset-token';

        DB::table('password_reset_codes')->insert([
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
            'reset_token_hash' => Hash::make($resetToken),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'reset_token' => $resetToken,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ];

        $this->postJson('/api/auth/reset-password', $payload)
            ->assertOk();

        $this->postJson('/api/auth/reset-password', $payload)
            ->assertStatus(422);

        $this->assertTrue(
            Hash::check('NewPassword123', $user->fresh()->password)
        );
    }

    public function test_forgot_password_does_not_send_another_code_within_60_seconds(): void
    {
        Mail::fake();

        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'email' => $user->email,
        ];

        $this->postJson('/api/auth/forgot-password', $payload)
            ->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);

        $this->postJson('/api/auth/forgot-password', $payload)
            ->assertOk();

        $this->assertDatabaseCount('password_reset_codes', 1);
    }

    public function test_otp_rejects_after_five_incorrect_attempts(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $resetId = DB::table('password_reset_codes')->insertGetId([
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'reset_token_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code' => '000000',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify-reset-code', $payload)
                ->assertStatus(422);
        }

        $this->assertDatabaseHas('password_reset_codes', [
            'id' => $resetId,
            'attempts' => 5,
        ]);

        $this->postJson('/api/auth/verify-reset-code', $payload)
            ->assertStatus(429);
    }

    public function test_otp_cannot_be_verified_twice(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        DB::table('password_reset_codes')->insert([
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'reset_token_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'code' => '123456',
        ];

        $this->postJson('/api/auth/verify-reset-code', $payload)
            ->assertOk()
            ->assertJsonStructure(['reset_token']);

        $this->postJson('/api/auth/verify-reset-code', $payload)
            ->assertStatus(422);
    }

    public function test_change_password_revokes_all_sessions(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'OldPassword123',
            'status' => 'active',
        ]);

        $user->createToken('session-one');
        $user->createToken('session-two');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/auth/change-password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertOk();

        $this->assertTrue(
            Hash::check('NewPassword123', $user->fresh()->password)
        );

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_logout_revokes_only_current_session(): void
    {
        $tenant = $this->createTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);

        $tokenOne = $user->createToken('session-one');
        $tokenTwo = $user->createToken('session-two');

        $this->withToken($tokenOne->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenOne->accessToken->id,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenTwo->accessToken->id,
        ]);
    }
}
