<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\InventoryLog;
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

    public function handle(ProductService $productService): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            return;
        }

        // Create log entries for tracking
        $logIds = [];
        foreach ($this->stockList as $entry) {
            $modelId = $entry['model_id'] ?? null;
            $newStock = $entry['seller_stock'][0]['stock'] ?? 0;
            $oldStock = 0;
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
            } else {
                $oldStock = $product->stock;
            }

            $log = \App\Models\InventoryLog::create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'sku' => $sku,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'source' => $this->source,
                'status' => 'pending',
            ]);

            $logIds[] = $log->id;
        }

        try {
            $productService->updateStock($this->user, $this->productId, $this->stockList);

            // Update logs to success
            \App\Models\InventoryLog::whereIn('id', $logIds)->update([
                'status' => 'success',
            ]);

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

            // Update logs to failed
            \App\Models\InventoryLog::whereIn('id', $logIds)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Push was accepted optimistically; restore dirty flag so UI shows * again.
            $product->update(['needs_push' => true]);

            throw $e;
        }
    }
}
