<?php

namespace App\Jobs;

use App\Models\ShopeeStore;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopeeStoreJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 90, 180];

    /**
     * @param  list<int>|null  $itemIds  Shopee item_ids; null = full store pull
     */
    public function __construct(
        protected int $storeId,
        protected ?array $itemIds = null
    ) {}

    public function uniqueId(): string
    {
        $scope = $this->itemIds === null || $this->itemIds === []
            ? 'full'
            : 'items:'.md5(implode(',', $this->itemIds));

        return "shopee-pull-store-{$this->storeId}-{$scope}";
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(ProductService $productService): void
    {
        $store = ShopeeStore::find($this->storeId);
        if (! $store) {
            return;
        }

        try {
            Log::info('SyncShopeeStoreJob starting', [
                'store_id' => $store->id,
                'item_count' => $this->itemIds === null ? 'all' : count($this->itemIds),
            ]);
            if ($this->itemIds === null || $this->itemIds === []) {
                $productService->syncForStore($store);
            } else {
                $productService->syncItemsForStore($store, $this->itemIds);
            }
            Log::info('SyncShopeeStoreJob completed', ['store_id' => $store->id]);
        } catch (\Exception $e) {
            Log::error('SyncShopeeStoreJob failed', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
