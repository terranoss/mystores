<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryWriter;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateShopeeStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $user,
        protected int $productId,
        protected array $stockList,
        protected string $source = 'user'
    ) {}

    public function handle(ProductService $productService, InventoryWriter $inventoryWriter): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            return;
        }

        // Open one pending row per stock entry before the marketplace call.
        $logs = [];
        foreach ($this->stockList as $entry) {
            $modelId = $entry['model_id'] ?? null;
            $newStock = $entry['seller_stock'][0]['stock'] ?? 0;
            $oldStock = $product->stock;
            $variantId = null;
            $sku = $product->item_sku;

            if ($modelId) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('model_id', $modelId)
                    ->first();
                if ($variant) {
                    $oldStock = $variant->stock;
                    $variantId = $variant->id;
                    $sku = $variant->model_sku;
                }
            }

            $logs[] = $inventoryWriter->begin(
                $product,
                $variantId,
                $sku,
                (float) $oldStock,
                (float) $newStock,
                null,
                null,
                $this->source,
            );
        }

        try {
            // The writer applies the local change; this job owns the audit rows.
            $productService->updateStock($this->user, $this->productId, $this->stockList, $this->source, false);

            foreach ($logs as $log) {
                $inventoryWriter->succeed($log);
            }

            // Trigger SKU-based cross-store sync if this is not a linked sync
            if ($this->source !== 'linked_sync') {
                foreach ($this->stockList as $entry) {
                    $modelId = $entry['model_id'] ?? null;
                    $newStock = $entry['seller_stock'][0]['stock'] ?? 0;
                    $sku = null;

                    if ($modelId) {
                        $variant = ProductVariant::where('product_id', $product->id)
                            ->where('model_id', $modelId)
                            ->first();
                        $sku = $variant?->model_sku;
                    } else {
                        $sku = $product->item_sku;
                    }

                    if ($sku) {
                        $productService->syncLinkedStores($this->user, $sku, $newStock, $product->id);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("UpdateShopeeStockJob failed: " . $e->getMessage());

            foreach ($logs as $log) {
                // Push was accepted optimistically; restore dirty flag so UI shows * again.
                $inventoryWriter->fail($log, $e->getMessage(), true);
            }

            throw $e;
        }
    }
}
