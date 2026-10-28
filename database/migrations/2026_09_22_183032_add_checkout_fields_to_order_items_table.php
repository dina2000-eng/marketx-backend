<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name')->nullable();
            });
        }

        if (! Schema::hasColumn('order_items', 'variant_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('variant_name')->nullable();
            });
        }

        if (! Schema::hasColumn('order_items', 'total')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('total', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        foreach ([
            'product_name',
            'variant_name',
            'total',
        ] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('order_items', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};