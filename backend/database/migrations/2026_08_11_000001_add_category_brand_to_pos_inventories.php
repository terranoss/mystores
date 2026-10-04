<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->unsignedInteger('category_id')->nullable()->after('total_stock');
            $table->string('category_name')->nullable()->after('category_id');
            $table->unsignedInteger('sub_category_id')->nullable()->after('category_name');
            $table->string('sub_category_name')->nullable()->after('sub_category_id');
            $table->unsignedInteger('brand_id')->nullable()->after('sub_category_name');
            $table->string('brand_name')->nullable()->after('brand_id');

            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'category_id']);
            $table->dropIndex(['workspace_id', 'brand_id']);
            $table->dropColumn([
                'category_id',
                'category_name',
                'sub_category_id',
                'sub_category_name',
                'brand_id',
                'brand_name',
            ]);
        });
    }
};
