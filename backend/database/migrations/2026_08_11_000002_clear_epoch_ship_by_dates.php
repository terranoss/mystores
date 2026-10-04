<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clear epoch placeholders saved when marketplace APIs returned ship_by_date = 0.
        DB::table('orders')
            ->whereNotNull('ship_by_date')
            ->where('ship_by_date', '<', '1971-01-01 00:00:00')
            ->update(['ship_by_date' => null]);
    }

    public function down(): void
    {
        // Irreversible data cleanup.
    }
};
