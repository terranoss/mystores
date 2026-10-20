<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Owns applying a local stock or price change and recording it exactly once.
 *
 * The apply() path is for synchronous writes: it updates the local product or
 * variant and writes one completed audit row per changed dimension. Queued
 * pushes use begin() while the marketplace call is in flight and close that
 * row with succeed() or fail().
 */
class InventoryWriter
{
    /**
     * Apply a local stock/price change and record one success row per change.
     *
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     * @param  bool  $audit  false when the caller already owns open begin() rows
     */
    public function apply(
        Product $product,
        ?array $stockList,
        ?array $priceList,
        string $source = 'user',
        bool $audit = true,
    ): void {
        DB::transaction(function () use ($product, $stockList, $priceList, $source, $audit) {
            if ($stockList) {
                $this->applyStock($product, $stockList, $source, $audit);
            }

            if ($priceList) {
                $this->applyPrice($product, $priceList, $source, $audit);
            }

            $this->persistProduct($product, $source);
        });
    }

    public function begin(
        Product $product,
        ?int $variantId,
        ?string $sku,
        ?float $oldStock,
        ?float $newStock,
        ?float $oldPrice,
        ?float $newPrice,
        string $source,
    ): InventoryLog {
        return InventoryLog::create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'sku' => $sku,
            'old_stock' => $oldStock !== null ? (int) $oldStock : null,
            'new_stock' => $newStock !== null ? (int) $newStock : null,
            'old_price' => $oldPrice !== null ? (float) $oldPrice : null,
            'new_price' => $newPrice !== null ? (float) $newPrice : null,
            'source' => $source,
            'status' => 'pending',
        ]);
    }

    public function succeed(InventoryLog $log): void
    {
        $log->update([
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function fail(InventoryLog $log, string $message, bool $restoreDirty = false): void
    {
        $log->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        if ($restoreDirty && $log->product_id !== null) {
            // The push was accepted optimistically, so a failure must make the
            // item show as unsynchronised again.
            Product::whereKey($log->product_id)->update(['needs_push' => true]);
        }
    }

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>  $stockList
     */
    private function applyStock(Product $product, array $stockList, string $source, bool $audit): void
    {
        $hasModelIds = collect($stockList)->contains(fn ($entry) => isset($entry['model_id']));

        if ($product->has_variants && $hasModelIds) {
            foreach ($stockList as $entry) {
                if (! isset($entry['model_id'])) {
                    continue;
                }

                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('model_id', $entry['model_id'])
                    ->first();

                if (! $variant) {
                    continue;
                }

                $oldStock = (int) $variant->stock;
                $newStock = (int) ($entry['seller_stock'][0]['stock'] ?? 0);
                $variant->update(['stock' => $newStock]);

                $this->record($audit, $product, $variant->id, $variant->model_sku, $oldStock, $newStock, null, null, $source);
            }

            $product->stock = (int) ProductVariant::where('product_id', $product->id)->sum('stock');

            return;
        }

        if ($product->has_variants) {
            $newStock = (int) ($stockList[0]['seller_stock'][0]['stock'] ?? 0);
            $variants = ProductVariant::where('product_id', $product->id)->get();

            foreach ($variants as $variant) {
                $oldStock = (int) $variant->stock;
                $variant->update(['stock' => $newStock]);

                $this->record($audit, $product, $variant->id, $variant->model_sku, $oldStock, $newStock, null, null, $source);
            }

            $product->stock = $newStock * max(1, $variants->count());

            return;
        }

        $oldStock = (int) $product->stock;
        $newStock = (int) ($stockList[0]['seller_stock'][0]['stock'] ?? 0);
        $product->stock = $newStock;

        $this->record($audit, $product, null, $product->item_sku, $oldStock, $newStock, null, null, $source);
    }

    /**
     * @param  list<array{model_id?:int,original_price?:float|int|string}>  $priceList
     */
    private function applyPrice(Product $product, array $priceList, string $source, bool $audit): void
    {
        $hasModelIds = collect($priceList)->contains(fn ($entry) => isset($entry['model_id']));

        if ($product->has_variants && $hasModelIds) {
            foreach ($priceList as $entry) {
                if (! isset($entry['model_id'])) {
                    continue;
                }

                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('model_id', $entry['model_id'])
                    ->first();

                if (! $variant) {
                    continue;
                }

                $oldPrice = (float) $variant->price;
                $newPrice = (float) ($entry['original_price'] ?? 0);
                $variant->update(['price' => $newPrice]);

                $this->record($audit, $product, $variant->id, $variant->model_sku, null, null, $oldPrice, $newPrice, $source);
            }

            $minPrice = ProductVariant::where('product_id', $product->id)->min('price');
            $product->price = $minPrice !== null ? (float) $minPrice : $product->price;

            return;
        }

        if ($product->has_variants) {
            $newPrice = (float) ($priceList[0]['original_price'] ?? 0);
            $variants = ProductVariant::where('product_id', $product->id)->get();

            foreach ($variants as $variant) {
                $oldPrice = (float) $variant->price;
                $variant->update(['price' => $newPrice]);

                $this->record($audit, $product, $variant->id, $variant->model_sku, null, null, $oldPrice, $newPrice, $source);
            }

            $product->price = $newPrice;

            return;
        }

        $oldPrice = (float) $product->price;
        $newPrice = (float) ($priceList[0]['original_price'] ?? 0);
        $product->price = $newPrice;

        $this->record($audit, $product, null, $product->item_sku, null, null, $oldPrice, $newPrice, $source);
    }

    private function persistProduct(Product $product, string $source): void
    {
        if ($source === 'local_staged') {
            $product->needs_push = true;
        } elseif (in_array($source, ['user', 'linked_sync'], true)) {
            $product->synced_at = now();
            $product->needs_push = false;
        }

        $product->save();
    }

    private function record(
        bool $audit,
        Product $product,
        ?int $variantId,
        ?string $sku,
        ?int $oldStock,
        ?int $newStock,
        ?float $oldPrice,
        ?float $newPrice,
        string $source,
    ): void {
        if (! $audit) {
            return;
        }

        InventoryLog::create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'sku' => $sku,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'source' => $source,
            'status' => 'success',
        ]);
    }
}
