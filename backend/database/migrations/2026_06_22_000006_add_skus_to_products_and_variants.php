<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('item_sku')->nullable()->after('item_name');
            $table->index('item_sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('model_sku')->nullable()->after('name');
            $table->index('model_sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['item_sku']);
            $table->dropColumn('item_sku');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['model_sku']);
            $table->dropColumn('model_sku');
        });
    }
};
