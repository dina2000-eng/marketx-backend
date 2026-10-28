<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table) {
                $table
                    ->char(
                        'country_code',
                        2
                    )
                    ->nullable()
                    ->after(
                        'shipping_address'
                    );

                $table->index(
                    'country_code'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'orders',
            function (Blueprint $table) {
                $table->dropIndex([
                    'country_code',
                ]);

                $table->dropColumn(
                    'country_code'
                );
            }
        );
    }
};