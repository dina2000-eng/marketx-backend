<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vendor_orders')
            ->where('status', 'completed')
            ->update(['status' => 'delivered']);
    }

    public function down(): void
    {
        DB::table('vendor_orders')
            ->where('status', 'delivered')
            ->update(['status' => 'completed']);
    }
};
