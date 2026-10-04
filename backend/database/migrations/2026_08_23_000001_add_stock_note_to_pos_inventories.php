<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->string('stock_note', 200)->nullable()->after('photo_path');
        });

        $this->backfillFromActivityLogs();
    }

    public function down(): void
    {
        Schema::table('pos_inventories', function (Blueprint $table) {
            $table->dropColumn('stock_note');
        });
    }

    private function backfillFromActivityLogs(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $items = DB::table('pos_inventories')
            ->select(['id', 'workspace_id', 'sku'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get();

        foreach ($items as $item) {
            $query = DB::table('workspace_activity_logs')
                ->where('action', 'erp.update_stock')
                ->where('workspace_id', $item->workspace_id)
                ->orderByDesc('id');

            if ($driver === 'pgsql') {
                $query->whereRaw("meta->>'sku' = ?", [$item->sku])
                    ->whereRaw("meta->>'note' IS NOT NULL")
                    ->whereRaw("meta->>'note' != ''");
            } else {
                $query->where('meta->sku', $item->sku)
                    ->whereNotNull('meta->note')
                    ->where('meta->note', '!=', '');
            }

            $log = $query->first(['meta']);
            if ($log === null) {
                continue;
            }

            $meta = is_string($log->meta) ? json_decode($log->meta, true) : (array) $log->meta;
            $note = isset($meta['note']) ? trim((string) $meta['note']) : '';
            if ($note === '' || mb_strlen($note) > 200) {
                continue;
            }

            DB::table('pos_inventories')
                ->where('id', $item->id)
                ->update(['stock_note' => $note]);
        }
    }
};
