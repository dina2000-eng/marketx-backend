<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_order_items', function (Blueprint $table) {

    $table->id();


    $table->foreignId('vendor_order_id')
        ->constrained()
        ->cascadeOnDelete();


    $table->foreignId('order_item_id')
        ->constrained()
        ->cascadeOnDelete();


    $table->foreignId('product_variant_id')
        ->constrained()
        ->cascadeOnDelete();


    $table->integer('quantity');


    $table->decimal(
        'price',
        10,
        2
    );


    $table->decimal(
        'total',
        10,
        2
    );


    $table->timestamps();

});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_order_items');
    }
};
