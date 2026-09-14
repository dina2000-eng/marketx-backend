<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'completed',
                'cancelled'
            ])->default('pending');


            $table->decimal('total_amount', 12, 2)
                ->default(0);

            $table->char('currency', 3);


            $table->text('shipping_address')
                ->nullable();


            $table->timestamps();

            $table->index([
                'tenant_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};