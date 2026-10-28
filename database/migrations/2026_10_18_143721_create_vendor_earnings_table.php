<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_earnings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_order_id')
                ->constrained('vendor_orders')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->decimal('gross_amount', 12, 2);

            $table->decimal('commission_pct', 5, 2);

            $table->decimal('commission_amount', 12, 2);

            $table->decimal('net_amount', 12, 2);

            $table->char('currency', 3);

            $table->string('status', 30)
                ->default('pending');

            $table->timestamp('available_at')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();

            $table->unique('vendor_order_id');

            $table->index([
                'store_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_earnings');
    }
};