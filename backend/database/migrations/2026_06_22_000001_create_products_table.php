<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('shopee_stores')->cascadeOnDelete();
            $table->bigInteger('item_id');
            $table->string('item_name')->nullable();
            $table->integer('category_id')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('has_variants')->default(false);
            $table->json('raw_response')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
