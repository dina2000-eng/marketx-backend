<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('attribute_value_variant', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('attribute_value_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->timestamps();


            $table->unique([
                'product_variant_id',
                'attribute_value_id'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('attribute_value_variant');
    }

};