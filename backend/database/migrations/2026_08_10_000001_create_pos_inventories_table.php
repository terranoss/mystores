<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->unsignedInteger('pos_product_id')->comment('Product ID from POS Melur response');
            $table->string('sku')->comment('Used for marketplace SKU matching');
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0)->comment('Direct sell_price_inc_tax from POS Melur');
            $table->integer('total_stock')->default(0)->comment('Sum of qty_available across all locations');
            $table->timestamp('synced_at')->nullable()->comment('Last fetched from pos.melur.com');
            $table->timestamps();

            // Unique per workspace+sku for upsert logic
            $table->unique(['workspace_id', 'sku']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_inventories');
    }
};
