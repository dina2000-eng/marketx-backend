<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('domain')->nullable();
            $table->char('country_code', 2);
            $table->char('default_currency', 3);
            $table->string('default_language', 10)->default('ar');
            $table->string('timezone', 64)->default('Asia/Amman');
            $table->string('logo_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])->default('pending');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
