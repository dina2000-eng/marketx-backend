<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStoreIdToCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {

            $table->foreignId('store_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('stores')
                ->nullOnDelete();

        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {

            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');

        });
    }
}