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
 * variants and writes one completed row per changed product/variant, carrying
 * both dimensions when both are supplied. Queued pushes use begin() while the
 * marketplace call is in flight and close that row with succeed() or fail().
 */
class InventoryWriter
{
    private readonly WorkspaceAccess $workspaceAccess;

    public function __construct(?WorkspaceAccess $workspaceAccess = null)
    {
        $this->workspaceAccess = $workspaceAccess ?? app(WorkspaceAccess::class);
    }

    /**
     * Apply a local stock/price change and record one success row per target.
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
            $targets = $this->resolveTargets($product, $stockList, $priceList);

            foreach ($targets as $target) {
                $this->writeTarget($product, $target, $source, $audit);
            }

            $this->rollUpParent($product, $stockList, $priceList, $targets);
            $this->persistProduct($product, $source);
        });
    }

    /**
     * Record one completed row for a change the caller applied itself (a pull).
     */
    public function recordChange(
        Product $product,
        ?ProductVariant $variant,
        ?int $oldStock,
        ?int $newStock,
        ?float $oldPrice,
        ?float $newPrice,
        string $source,
    ): void {
        $this->writeRow(
            true,
            $product,
            $variant?->id,
            $variant?->model_sku ?? $product->item_sku,
            $oldStock,
            $newStock,
            $oldPrice,
            $newPrice,
            $source,
        );
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

        if (! $restoreDirty || $log->product_id === null) {
            return;
        }

        // The push was accepted optimistically, so a failure must make the
        // item show as unsynchronised again. Scope the write to the workspace
        // that owns the log's product instead of trusting a bare product id.
        $product = $log->product()->with('store')->first();
        if ($product === null) {
            return;
        }

        $query = Product::whereKey($product->id);
        $workspaceId = $product->store?->workspace_id;

        if ($workspaceId !== null) {
            $this->workspaceAccess->scopeProductsToWorkspace($query, (int) $workspaceId);
        } else {
            $query->whereHas('store', fn ($store) => $store->where('user_id', $product->store?->user_id));
        }

        $query->update(['needs_push' => true]);
    }

    /**
     * Resolve which products/variants a call mutates and the values to write.
     *
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     * @return list<array{variant:?ProductVariant,stock:?int,price:?float}>
     */
    private function resolveTargets(Product $product, ?array $stockList, ?array $priceList): array
    {
        $stockByModel = $this->valuesByModel($stockList, 'stock');
        $priceByModel = $this->valuesByModel($priceList, 'price');

        if ($stockByModel !== [] || $priceByModel !== []) {
            return $this->variantTargetsByModel($product, $stockList, $priceList, $stockByModel, $priceByModel);
        }

        $firstStock = $this->firstValue($stockList, 'stock');
        $firstPrice = $this->firstValue($priceList, 'price');

        if ($product->has_variants) {
            $variants = ProductVariant::where('product_id', $product->id)->get();

            if ($variants->isNotEmpty()) {
                return $variants->map(fn (ProductVariant $variant) => [
                    'variant' => $variant,
                    'stock' => $firstStock,
                    'price' => $firstPrice,
                ])->all();
            }
        }

        return [[
            'variant' => null,
            'stock' => $firstStock,
            'price' => $firstPrice,
        ]];
    }

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     * @param  array<int, int>  $stockByModel
     * @param  array<int, float>  $priceByModel
     * @return list<array{variant:?ProductVariant,stock:?int,price:?float}>
     */
    private function variantTargetsByModel(
        Product $product,
        ?array $stockList,
        ?array $priceList,
        array $stockByModel,
        array $priceByModel,
    ): array {
        $modelIds = array_values(array_unique(array_merge(
            array_keys($stockByModel),
            array_keys($priceByModel),
        )));

        $fallbackStock = $stockByModel === [] ? $this->firstValue($stockList, 'stock') : null;
        $fallbackPrice = $priceByModel === [] ? $this->firstValue($priceList, 'price') : null;

        $targets = [];
        foreach ($modelIds as $modelId) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('model_id', $modelId)
                ->first();

            if (! $variant) {
                continue;
            }

            $targets[] = [
                'variant' => $variant,
                'stock' => $stockByModel[$modelId] ?? $fallbackStock,
                'price' => $priceByModel[$modelId] ?? $fallbackPrice,
            ];
        }

        return $targets;
    }

    /**
     * @param  array{variant:?ProductVariant,stock:?int,price:?float}  $target
     */
    private function writeTarget(Product $product, array $target, string $source, bool $audit): void
    {
        /** @var ProductVariant|null $variant */
        $variant = $target['variant'];
        $subject = $variant ?? $product;

        $changes = [];
        $oldStock = null;
        $oldPrice = null;

        if ($target['stock'] !== null) {
            $oldStock = (int) $subject->stock;
            $changes['stock'] = $target['stock'];
        }

        if ($target['price'] !== null) {
            $oldPrice = (float) $subject->price;
            $changes['price'] = $target['price'];
        }

        if ($changes === []) {
            return;
        }

        $subject->update($changes);

        $this->writeRow(
            $audit,
            $product,
            $variant?->id,
            $variant?->model_sku ?? $product->item_sku,
            $oldStock,
            $target['stock'],
            $oldPrice,
            $target['price'],
            $source,
        );
    }

    /**
     * @param  list<array{variant:?ProductVariant,stock:?int,price:?float}>  $targets
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     */
    private function rollUpParent(Product $product, ?array $stockList, ?array $priceList, array $targets): void
    {
        $targetedVariants = collect($targets)->contains(fn (array $target) => $target['variant'] !== null);

        if (! $targetedVariants) {
            return;
        }

        if ($stockList !== null) {
            $product->stock = (int) ProductVariant::where('product_id', $product->id)->sum('stock');
        }

        if ($priceList !== null) {
            $minPrice = ProductVariant::where('product_id', $product->id)->min('price');
            if ($minPrice !== null) {
                $product->price = (float) $minPrice;
            }
        }
    }

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $list
     * @return array<int, int>
     */
    private function valuesByModel(?array $list, string $dimension): array
    {
        $values = [];

        foreach ($list ?? [] as $entry) {
            if (! isset($entry['model_id'])) {
                continue;
            }

            $value = $this->valueOf($entry, $dimension);
            if ($value !== null) {
                $values[(int) $entry['model_id']] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $list
     */
    private function firstValue(?array $list, string $dimension): int|float|null
    {
        $entry = $list[0] ?? null;

        return $entry === null ? null : $this->valueOf($entry, $dimension);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function valueOf(array $entry, string $dimension): int|float|null
    {
        if ($dimension === 'stock') {
            if (! array_key_exists('seller_stock', $entry)) {
                return null;
            }

            return (int) ($entry['seller_stock'][0]['stock'] ?? 0);
        }

        if (! array_key_exists('original_price', $entry)) {
            return null;
        }

        return (float) $entry['original_price'];
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

    private function writeRow(
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
