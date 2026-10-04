<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_tokens', function (Blueprint $table) {
            $table->datetime('last_refresh_attempt_at')->nullable()->after('last_refreshed_at');
            $table->datetime('last_refresh_failed_at')->nullable()->after('last_refresh_attempt_at');
            $table->string('last_refresh_error', 255)->nullable()->after('last_refresh_failed_at');
            $table->integer('refresh_failure_count')->default(0)->after('last_refresh_error');
        });

        Schema::table('shopee_oauth_states', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained('shopee_stores')->nullOnDelete()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_oauth_states', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::table('shopee_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'last_refresh_attempt_at',
                'last_refresh_failed_at',
                'last_refresh_error',
                'refresh_failure_count',
            ]);
        });
    }
};
