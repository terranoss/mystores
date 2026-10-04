<?php

namespace App\Console\Commands;

use App\Models\ShopeeStore;
use App\Services\LazadaOrderService;
use Illuminate\Console\Command;

class SyncLazadaOrders extends Command
{
    protected $signature = 'lazada:sync-orders
                            {--store= : Sync only a specific Lazada store by ID}
                            {--order= : Re-fetch a specific Lazada order id (order_sn) for matching store(s)}
                            {--stale : Also re-fetch actionable orders older than the watermark}
                            {--older-than=2 : Days; with --stale, re-sync actionable orders this old or older}';

    protected $description = 'Sync orders from Lazada for all active Lazada stores';

    public function handle(LazadaOrderService $lazadaOrders): int
    {
        $specificStoreId = $this->option('store');
        $specificOrderId = $this->option('order');
        $stale = (bool) $this->option('stale');
        $olderThan = max(1, (int) $this->option('older-than'));

        $stores = ShopeeStore::with('token')
            ->where('is_active', true)
            ->where('platform', 'lazada')
            ->when($specificStoreId, fn ($q) => $q->where('id', $specificStoreId))
            ->get();

        if ($stores->isEmpty()) {
            $this->warn($specificStoreId ? 'Lazada store not found or inactive.' : 'No active Lazada stores found.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($stores as $store) {
            if (!$store->token) {
                $this->warn("  Skipping Store #{$store->id}: no token.");
                continue;
            }

            if ($specificOrderId) {
                $this->info("Re-fetching Lazada order {$specificOrderId} for Store #{$store->id} ({$store->shop_name})...");
                try {
                    $order = $lazadaOrders->syncOrderById($store, (string) $specificOrderId);
                    $synced++;
                    $this->line("  ✓ Order {$order->order_sn} status={$order->order_status}");
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ✗ Store #{$store->id} order {$specificOrderId} failed: {$e->getMessage()}");
                }
                continue;
            }

            $this->info("Syncing Lazada orders for Store #{$store->id} ({$store->shop_name})...");

            try {
                $lazadaOrders->syncForStore($store);
                $synced++;
                $this->line("  ✓ Store #{$store->id} synced.");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Store #{$store->id} failed: {$e->getMessage()}");
                continue;
            }

            if (!$stale) {
                continue;
            }

            $this->info("  Re-syncing stale actionable orders (older than {$olderThan} day(s))...");
            try {
                $result = $lazadaOrders->resyncStaleActionableOrders($store, $olderThan);
                $this->line("  ✓ Stale: attempted={$result['attempted']} updated={$result['updated']} failed={$result['failed']}");
                if ($result['failed'] > 0) {
                    $failed++;
                    foreach (array_slice($result['errors'], 0, 5) as $error) {
                        $this->warn("    - {$error}");
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Stale resync failed: {$e->getMessage()}");
            }
        }

        $this->info("Done: {$synced} stores/orders synced, {$failed} errors.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
