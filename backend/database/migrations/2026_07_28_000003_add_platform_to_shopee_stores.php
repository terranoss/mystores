<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add platform column to shopee_stores.
 * Uses hasColumn guard — safe to run even if column already exists
 * (migration was applied on the live DB before the file was lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shopee_stores', 'platform')) {
            return;
        }

        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->string('platform', 20)->default('shopee')->after('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};
