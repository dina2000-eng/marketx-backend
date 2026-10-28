<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('cart_items', function (Blueprint $table) {

        $table->dropUnique([
            'cart_id',
            'product_id'
        ]);


        $table->unique([
            'cart_id',
            'product_variant_id'
        ]);

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
