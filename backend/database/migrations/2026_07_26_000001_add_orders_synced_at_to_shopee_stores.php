<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dateTime('orders_synced_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropColumn('orders_synced_at');
        });
    }
};
