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
       Schema::create('addresses', function (Blueprint $table) {
    $table->id();

    $table->foreignId('tenant_id')
        ->nullable()
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('label', 50);
    $table->string('recipient_name', 150);
    $table->string('phone', 30);

    $table->string('city', 100);
    $table->string('area', 150)->nullable();
    $table->string('street', 255);
    $table->string('building', 100)->nullable();

    $table->text('notes')->nullable();

    $table->boolean('is_default')
        ->default(false);

    $table->timestamps();

    $table->index([
        'tenant_id',
        'user_id',
    ]);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
