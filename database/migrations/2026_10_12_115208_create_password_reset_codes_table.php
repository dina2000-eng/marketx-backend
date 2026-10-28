<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('email');

            $table->string('code_hash');

            $table->unsignedTinyInteger('attempts')
                ->default(0);

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')
                ->nullable();

            $table->string('reset_token_hash')
                ->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'email',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'password_reset_codes'
        );
    }
};