<?php

namespace App\Console\Commands;

use App\Jobs\SyncShopeeStoreJob;
use App\Models\ShopeeStore;
use Illuminate\Console\Command;

class SyncShopeeProducts extends Command
{
    protected $signature = 'shopee:sync-products {--store= : Sync only a specific store by ID} {--sync : Run inline instead of queueing}';

    protected $description = 'Queue paced product pulls from Shopee (staggered per store)';

    public function handle(\App\Services\ProductService $productService): int
    {
        $specificStoreId = $this->option('store');
        $inline = (bool) $this->option('sync');
        $stores = ShopeeStore::with('token')->where('is_active', true)
            ->when($specificStoreId, fn ($q) => $q->where('id', $specificStoreId))
            ->orderBy('id')
            ->get();
        if ($stores->isEmpty()) {
            $this->warn($specificStoreId ? 'Store not found or inactive.' : 'No active stores found.');
            return self::SUCCESS;
        }
        $delaySeconds = 0;
        $staggerSeconds = 120;
        $queued = 0;
        foreach ($stores as $store) {
            if (! $store->token) {
                $this->warn("  Skipping Store #{$store->id}: no token.");
                continue;
            }
            if ($inline) {
                $this->info("Syncing Store #{$store->id} inline...");
                try {
                    $productService->syncForStore($store);
                    $this->line("  ✓ Store #{$store->id} synced.");
                } catch (\Exception $e) {
                    $this->error("  ✗ Store #{$store->id} failed: {$e->getMessage()}");
                }
                continue;
            }
            SyncShopeeStoreJob::dispatch($store->id)->delay(now()->addSeconds($delaySeconds));
            $this->info("Queued Store #{$store->id} ({$store->shop_name}) delay={$delaySeconds}s");
            $queued++;
            $delaySeconds += $staggerSeconds;
        }
        if (! $inline) {
            $this->info("Done: queued {$queued} store pull job(s).");
        }
        return self::SUCCESS;
    }
}
