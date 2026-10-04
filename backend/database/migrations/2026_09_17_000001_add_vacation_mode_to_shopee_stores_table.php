<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->boolean('vacation_mode')->default(false)->after('is_active');
            $table->timestamp('vacation_mode_synced_at')->nullable()->after('vacation_mode');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropColumn(['vacation_mode', 'vacation_mode_synced_at']);
        });
    }
};
