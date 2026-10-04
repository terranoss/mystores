<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->timestamp('stock_updated_at')->nullable()->after('photo_path');
            $table->foreignId('stock_updated_by')->nullable()->after('stock_updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->dropForeign(['stock_updated_by']);
            $table->dropColumn(['stock_updated_at', 'stock_updated_by']);
        });
    }
};
