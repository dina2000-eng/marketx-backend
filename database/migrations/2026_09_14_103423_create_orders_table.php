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

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');

            $table->string('status')
                ->default('pending');

            $table->decimal(
                'total_amount',
                12,
                2
            )->default(0);

            $table->string(
                'currency',
                10
            );

            $table->text(
                'shipping_address'
            )->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};