<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->char('currency', 3);

            $table->decimal('pending_balance', 12, 2)
                ->default(0);

            $table->decimal('available_balance', 12, 2)
                ->default(0);

            $table->decimal('total_earned', 12, 2)
                ->default(0);

            $table->decimal('total_withdrawn', 12, 2)
                ->default(0);

            $table->timestamps();

            $table->unique([
                'store_id',
                'currency',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_wallets');
    }
};