<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn(
            'order_items',
            'product_variant_id'
        )) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('product_variant_id')
                    ->nullable()
                    ->constrained('product_variants')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn(
            'vendor_order_items',
            'product_variant_id'
        )) {
            Schema::table(
                'vendor_order_items',
                function (Blueprint $table) {
                    $table->foreignId('product_variant_id')
                        ->nullable()
                        ->constrained('product_variants')
                        ->nullOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(
            'vendor_order_items',
            'product_variant_id'
        )) {
            Schema::table(
                'vendor_order_items',
                function (Blueprint $table) {
                    $table->dropForeign([
                        'product_variant_id'
                    ]);

                    $table->dropColumn(
                        'product_variant_id'
                    );
                }
            );
        }

        if (Schema::hasColumn(
            'order_items',
            'product_variant_id'
        )) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign([
                    'product_variant_id'
                ]);

                $table->dropColumn(
                    'product_variant_id'
                );
            });
        }
    }
};