<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal(
                'amount',
                12,
                2
            );

            $table->char(
                'currency',
                3
            );

            $table->string(
                'status'
            )->default('pending');

            $table->string(
                'gateway'
            )->nullable();

            $table->string(
                'gateway_refund_reference'
            )->nullable();

            $table->string(
                'reason'
            )->nullable();

            $table->json(
                'metadata'
            )->nullable();

            $table->timestamp(
                'processed_at'
            )->nullable();

            $table->timestamp(
                'failed_at'
            )->nullable();

            $table->timestamps();

            $table->index([
                'payment_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'refunds'
        );
    }
};