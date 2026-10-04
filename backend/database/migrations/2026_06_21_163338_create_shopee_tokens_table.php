<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('shopee_stores')->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->dateTime('access_token_expires_at');
            $table->dateTime('refresh_token_expires_at');
            $table->dateTime('auth_expires_at');
            $table->dateTime('last_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_tokens');
    }
};
