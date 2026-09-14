<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_orders', function (Blueprint $table) {

            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
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


            $table->timestamps();


            $table->index([
                'store_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_orders');
    }
};