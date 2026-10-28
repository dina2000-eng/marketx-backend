<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 50)
                ->nullable()
                ->after('currency');

            $table->string('payment_status', 50)
                ->default('unpaid')
                ->after('payment_method');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);

            $table->dropColumn([
                'payment_method',
                'payment_status',
            ]);
        });
    }
};