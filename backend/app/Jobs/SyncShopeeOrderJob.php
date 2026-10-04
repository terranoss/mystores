<?php

namespace App\Jobs;

use App\Models\ShopeeStore;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopeeOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $storeId,
        protected string $orderSn
    ) {}

    public function handle(OrderService $orderService): void
    {
        $store = ShopeeStore::with('token')->find($this->storeId);
        if (!$store || !$store->token || !$store->is_active) {
            Log::warning('SyncShopeeOrderJob skipped: store missing or inactive', [
                'store_id' => $this->storeId,
                'order_sn' => $this->orderSn,
            ]);
            return;
        }

        try {
            Log::info('SyncShopeeOrderJob starting', [
                'store_id' => $store->id,
                'order_sn' => $this->orderSn,
            ]);
            $orderService->syncOrderBySn($store, $this->orderSn);
            Log::info('SyncShopeeOrderJob completed', [
                'store_id' => $store->id,
                'order_sn' => $this->orderSn,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncShopeeOrderJob failed', [
                'store_id' => $store->id,
                'order_sn' => $this->orderSn,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
