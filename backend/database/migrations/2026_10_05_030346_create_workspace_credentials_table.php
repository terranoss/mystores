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
        Schema::create('workspace_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // 'shopee' | 'lazada'
            
            // Shopee fields
            $table->string('partner_id')->nullable();
            $table->text('partner_key')->nullable(); // encrypted
            $table->text('push_partner_key')->nullable(); // encrypted
            
            // Lazada fields
            $table->string('app_key')->nullable();
            $table->text('app_secret')->nullable(); // encrypted
            
            // Common
            $table->string('redirect_url')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->unique(['workspace_id', 'platform']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_credentials');
    }
};
