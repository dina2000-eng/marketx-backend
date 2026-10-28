<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);

            $table->char('currency', 3);

            $table->string('method', 50);

            $table->string('status', 30)
                ->default('pending');

            $table->string('reference', 191)
                ->nullable();

            $table->json('destination_details')
                ->nullable();

            $table->timestamp('requested_at')
                ->nullable();

            $table->timestamp('processed_at')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamp('failed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'store_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};