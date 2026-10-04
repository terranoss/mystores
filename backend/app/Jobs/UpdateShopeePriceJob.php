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

class UpdateShopeePriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected User $user,
        protected int $productId,
        protected array $priceList,
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
        foreach ($this->priceList as $entry) {
            $modelId = $entry['model_id'] ?? null;
            $newPrice = $entry['original_price'] ?? 0;
            $oldPrice = 0;
            $variantId = null;
            $sku = $product->item_sku;

            if ($modelId) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('model_id', $modelId)
                    ->first();
                if ($variant) {
                    $oldPrice = $variant->price;
                    $variantId = $variant->id;
                    $sku = $variant->model_sku;
                }
            } else {
                $oldPrice = $product->price;
            }

            $log = \App\Models\InventoryLog::create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'sku' => $sku,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'source' => $this->source,
                'status' => 'pending',
            ]);

            $logIds[] = $log->id;
        }

        try {
            $productService->updatePrice($this->user, $this->productId, $this->priceList);

            // Update logs to success
            \App\Models\InventoryLog::whereIn('id', $logIds)->update([
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            Log::error("UpdateShopeePriceJob failed: " . $e->getMessage());

            // Update logs to failed
            \App\Models\InventoryLog::whereIn('id', $logIds)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
