<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('method', 50);

            $table->string('gateway', 100)
                ->nullable();

            $table->string('status', 50)
                ->default('pending');

            $table->decimal('amount', 12, 2);

            $table->char('currency', 3);

            $table->string('transaction_id', 191)
                ->nullable();

            $table->string('gateway_reference', 191)
                ->nullable();

            $table->string('idempotency_key', 191)
                ->nullable()
                ->unique();

            $table->json('metadata')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamp('failed_at')
                ->nullable();

            $table->timestamp('refunded_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'order_id',
                'status',
            ]);

            $table->index('transaction_id');
            $table->index('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};