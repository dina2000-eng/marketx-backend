<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('password'); // password_hash بالـSQL schema، Laravel بيسميها password
            $table->enum('role', ['customer', 'vendor_owner', 'vendor_staff', 'tenant_admin', 'platform_admin'])
                ->default('customer');
            $table->string('avatar_url')->nullable();
            $table->string('preferred_language', 10)->default('ar');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->enum('status', ['active', 'blocked', 'pending'])->default('pending');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
