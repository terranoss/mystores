<?php

namespace App\Console\Commands;

use App\Models\ShopeeStore;
use App\Services\OrderService;
use Illuminate\Console\Command;

class SyncShopeeOrders extends Command
{
    protected $signature = 'shopee:sync-orders
                            {--store= : Sync only a specific store by ID}
                            {--fix-quantities : After sync, backfill quantities from stored raw_response}';

    protected $description = 'Sync orders from Shopee for all active stores';

    public function handle(OrderService $orderService): int
    {
        $specificStoreId = $this->option('store');

        $stores = ShopeeStore::with('token')
            ->where('is_active', true)
            ->where('platform', 'shopee')
            ->when($specificStoreId, fn ($q) => $q->where('id', $specificStoreId))
            ->get();

        if ($stores->isEmpty()) {
            $this->warn($specificStoreId ? 'Store not found or inactive.' : 'No active Shopee stores found.');
            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($stores as $store) {
            if (!$store->token) {
                $this->warn("  Skipping Store #{$store->id}: no token.");
                continue;
            }

            $this->info("Syncing orders for Store #{$store->id} ({$store->shop_name}...)");

            try {
                $orderService->syncForStore($store);
                $synced++;
                $this->line("  ✓ Store #{$store->id} synced.");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Store #{$store->id} failed: {$e->getMessage()}");
            }
        }

        if ($this->option('fix-quantities')) {
            $this->info('Backfilling Shopee quantities from raw_response...');
            $result = $orderService->backfillQuantitiesFromRaw();
            $this->line("  Quantities: updated={$result['updated']} skipped={$result['skipped']}");
        }

        $this->info("Done: {$synced} stores synced, {$failed} errors.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
