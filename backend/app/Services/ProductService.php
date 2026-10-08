<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\User;

use App\Exceptions\StoreNotActiveException;
use App\Exceptions\ProductAuthorizationException;
use App\Helpers\ShopeeResponseHelper;
use EcomPHP\Shopee\Errors\AuthorizationException;
use EcomPHP\Shopee\Errors\ShopeeException;

class ProductService
{
    private readonly LazadaService $lazada;
    private readonly WorkspaceAccess $workspaceAccess;

    public function __construct(
        private readonly ShopeeService $shopee,
        ?LazadaService $lazada = null,
        ?WorkspaceAccess $workspaceAccess = null,
    ) {
        $this->lazada = $lazada ?? app(LazadaService::class);
        $this->workspaceAccess = $workspaceAccess ?? app(WorkspaceAccess::class);
    }

    /**
     * @return list<int>
     */
    private function workspaceIds(User $user): array
    {
        $ids = $this->workspaceAccess->workspaceIdsFor($user);
        if ($ids === []) {
            $ids = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }
        return $ids;
    }

    private function applyWorkspaceStoreScope($query, User $user, bool $activeOnly = false): void
    {
        $ids = $this->workspaceIds($user);
        $query->whereIn('workspace_id', $ids);
        if ($activeOnly) {
            $query->where('is_active', true);
        }
    }

    /**
     * Get products for a user's stores, with optional filters.
     */
    public function getProducts(User $user, ?int $storeId = null, ?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): mixed
    {
        $query = Product::whereHas('store', function ($q) use ($user) {
            $this->applyWorkspaceStoreScope($q, $user);
        })
            ->with('variants')
            ->when($storeId, fn ($q, $id) => $q->where('store_id', $id))
            ->when($search, fn ($q, $s) => $q->where('item_name', 'like', "%{$s}%"));

        // Sort by allowed columns
        if (in_array($sortBy, ['item_name', 'price', 'stock', 'synced_at', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->latest();
        }

        return $query->paginate(50);
    }

    /**
     * Get products grouped by SKU for cross-store consolidated view.
     * Products with SKUs are grouped together; products without SKUs appear as ungrouped items.
     */
    public function getProductsGroupedBySku(
        User $user,
        ?int $storeId = null,
        ?string $search = null,
        ?string $stockHealth = null,
        ?string $linkStatus = null,
        ?string $sortBy = null,
        string $sortDir = 'asc',
        ?string $syncState = null,
    ): array {
        $query = Product::with('variants', 'store')
            ->whereHas('store', function ($q) use ($user) {
                $this->applyWorkspaceStoreScope($q, $user);
            });

        if ($storeId !== null && $storeId > 0) {
            $query->where('store_id', $storeId);
        }

        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($sub) use ($search, $term) {
                $sub->where('item_name', 'like', $term)
                    ->orWhere('item_sku', 'like', $term)
                    ->orWhereHas('variants', fn ($vq) => $vq->where('model_sku', 'like', $term));
                if (ctype_digit($search)) {
                    $sub->orWhere('item_id', (int) $search);
                }
            });
        }

        if ($stockHealth !== null && $stockHealth !== '') {
            $this->applyStockHealthFilter($query, $stockHealth);
        }

        if ($syncState === 'unsynced') {
            $query->where('needs_push', true);
        }

        if ($linkStatus === 'linked' || $linkStatus === 'multi_store') {
            $query->whereNotNull('item_sku')->where('item_sku', '!=', '');
        } elseif ($linkStatus === 'unlinked') {
            $query->where(function ($q) {
                $q->whereNull('item_sku')->orWhere('item_sku', '');
            });
        } elseif ($linkStatus === 'variants') {
            $query->where('has_variants', true);
        }

        $allowedSort = ['item_name', 'price', 'stock', 'synced_at'];
        $dir = $sortDir === 'desc' ? 'desc' : 'asc';
        if (in_array($sortBy, $allowedSort, true)) {
            $query->orderBy($sortBy, $dir)->orderBy('id');
        } else {
            $query->orderBy('item_name')->orderBy('id');
        }

        $products = $query->get();

        $grouped = [];
        $ungrouped = [];

        foreach ($products as $product) {
            if ($product->item_sku) {
                $sku = $product->item_sku;
                if (!isset($grouped[$sku])) {
                    $grouped[$sku] = [
                        'sku' => $sku,
                        'products' => [],
                        'total_stock' => 0,
                    ];
                }
                $grouped[$sku]['products'][] = $product;
                $grouped[$sku]['total_stock'] += $product->stock;
            } else {
                $ungrouped[] = $product;
            }
        }

        if ($linkStatus === 'multi_store') {
            $grouped = array_filter(
                $grouped,
                fn ($group) => count($group['products']) >= 2
            );
            $ungrouped = [];
        } elseif ($linkStatus === 'linked') {
            $ungrouped = [];
        } elseif ($linkStatus === 'unlinked') {
            $grouped = [];
        }

        $groupedList = array_values($grouped);
        $this->sortGroupedProductRows($groupedList, $ungrouped, $sortBy, $dir);

        return [
            'grouped' => $groupedList,
            'ungrouped' => $ungrouped,
            'total' => count($groupedList) + count($ungrouped),
        ];
    }

    /**
     * Filter products by local stock health bands.
     */
    private function applyStockHealthFilter($query, string $stockHealth): void
    {
        if ($stockHealth === 'out') {
            $query->where('stock', '<=', 0);
            return;
        }

        if ($stockHealth === 'low') {
            $query->where('stock', '>', 0)->where('stock', '<=', 5);
            return;
        }

        if ($stockHealth === 'in') {
            $query->where('stock', '>', 5);
        }
    }

    /**
     * Sort grouped SKU rows and ungrouped products by the same key.
     *
     * @param  list<array{sku:string,products:list<Product>,total_stock:int}>  $groupedList
     * @param  list<Product>  $ungrouped
     */
    private function sortGroupedProductRows(array &$groupedList, array &$ungrouped, ?string $sortBy, string $dir): void
    {
        $factor = $dir === 'desc' ? -1 : 1;
        $key = in_array($sortBy, ['item_name', 'price', 'stock', 'synced_at'], true)
            ? $sortBy
            : 'item_name';

        usort($groupedList, function ($a, $b) use ($key, $factor) {
            return $this->compareGroupedSortValues($a, $b, $key) * $factor;
        });

        usort($ungrouped, function (Product $a, Product $b) use ($key, $factor) {
            return $this->compareProductSortValues($a, $b, $key) * $factor;
        });
    }

    private function compareGroupedSortValues(array $a, array $b, string $key): int
    {
        if ($key === 'stock') {
            $aMin = min(array_map(fn ($p) => (int) $p->stock, $a['products']) ?: [0]);
            $bMin = min(array_map(fn ($p) => (int) $p->stock, $b['products']) ?: [0]);
            return $aMin <=> $bMin;
        }
        if ($key === 'price') {
            $aMin = min(array_map(fn ($p) => (float) $p->price, $a['products']) ?: [0]);
            $bMin = min(array_map(fn ($p) => (float) $p->price, $b['products']) ?: [0]);
            return $aMin <=> $bMin;
        }
        if ($key === 'synced_at') {
            $aTs = max(array_map(fn ($p) => $p->synced_at?->timestamp ?? 0, $a['products']) ?: [0]);
            $bTs = max(array_map(fn ($p) => $p->synced_at?->timestamp ?? 0, $b['products']) ?: [0]);
            return $aTs <=> $bTs;
        }
        $aName = (string) ($a['products'][0]->item_name ?? $a['sku']);
        $bName = (string) ($b['products'][0]->item_name ?? $b['sku']);
        return strcasecmp($aName, $bName);
    }

    private function compareProductSortValues(Product $a, Product $b, string $key): int
    {
        if ($key === 'stock') {
            return (int) $a->stock <=> (int) $b->stock;
        }
        if ($key === 'price') {
            return (float) $a->price <=> (float) $b->price;
        }
        if ($key === 'synced_at') {
            return ($a->synced_at?->timestamp ?? 0) <=> ($b->synced_at?->timestamp ?? 0);
        }
        return strcasecmp((string) $a->item_name, (string) $b->item_name);
    }

    /**
     * Get a single product with variants, owner-checked.
     */
    public function getProduct(User $user, int $id): Product
    {
        $product = Product::with('variants', 'store')->findOrFail($id);

        if (!$this->workspaceAccess->canAccessStore($user, $product->store)) {
            throw new ProductAuthorizationException();
        }

        return $product;
    }

    /**
     * Update stock for a product (and optionally its variants) via the store marketplace API.
     */
    public function updateStock(User $user, int $id, array $stockList): Product
    {
        $product = $this->getProduct($user, $id);
        $store = $product->store;

        if (!$store->token || !$store->is_active) {
            throw new StoreNotActiveException();
        }

        if ($store->platform === 'lazada') {
            return $this->updateLazadaProduct($product, $stockList, null);
        }

        // Explicitly cast parameters to correct types to prevent Go unmarshaling errors in Shopee API
        $formattedStockList = array_map(function ($entry) {
            $formatted = [];
            if (isset($entry['model_id'])) {
                $formatted['model_id'] = (int) $entry['model_id'];
            }
            if (isset($entry['seller_stock'])) {
                $formatted['seller_stock'] = array_map(function ($stockEntry) {
                    return [
                        'stock' => (int) $stockEntry['stock'],
                    ];
                }, $entry['seller_stock']);
            }
            return $formatted;
        }, $stockList);

        $payload = [
            'item_id' => (int) $product->item_id,
            'stock_list' => $formattedStockList,
        ];

        $response = $this->callWithTokenRetry($store, function ($client) use ($payload) {
            return $client->Product->call('POST', 'product/update_stock', [
                \GuzzleHttp\RequestOptions::JSON => $payload
            ]);
        });

        if (!empty($response['error'])) {
            throw new \Exception("Shopee API Error: " . ($response['message'] ?? $response['error']));
        }

        // Update local DB after successful API call
        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $stockList) {
            if ($product->has_variants) {
                foreach ($stockList as $entry) {
                    if (isset($entry['model_id'])) {
                        ProductVariant::where('product_id', $product->id)
                            ->where('model_id', $entry['model_id'])
                            ->update([
                                'stock' => $entry['seller_stock'][0]['stock'] ?? 0,
                            ]);
                    }
                }
                $product->update([
                    'synced_at' => now(),
                    'needs_push' => false,
                ]);
            } else {
                $product->update([
                    'stock' => $stockList[0]['seller_stock'][0]['stock'] ?? 0,
                    'synced_at' => now(),
                    'needs_push' => false,
                ]);
            }
        });

        return $product->fresh()->load('variants');
    }

    /**
     * Update price for a product (and optionally its variants) via the store marketplace API.
     */
    public function updatePrice(User $user, int $id, array $priceList): Product
    {
        $product = $this->getProduct($user, $id);
        $store = $product->store;

        if (!$store->token || !$store->is_active) {
            throw new StoreNotActiveException();
        }

        if ($store->platform === 'lazada') {
            return $this->updateLazadaProduct($product, null, $priceList);
        }

        // Explicitly cast parameters to correct types to prevent Go unmarshaling errors in Shopee API
        $formattedPriceList = array_map(function ($entry) {
            $formatted = [];
            if (isset($entry['model_id'])) {
                $formatted['model_id'] = (int) $entry['model_id'];
            }
            if (isset($entry['original_price'])) {
                $formatted['original_price'] = (float) $entry['original_price'];
            }
            return $formatted;
        }, $priceList);

        $payload = [
            'item_id' => (int) $product->item_id,
            'price_list' => $formattedPriceList,
        ];

        $response = $this->callWithTokenRetry($store, function ($client) use ($payload) {
            return $client->Product->call('POST', 'product/update_price', [
                \GuzzleHttp\RequestOptions::JSON => $payload
            ]);
        });

        if (!empty($response['error'])) {
            throw new \Exception("Shopee API Error: " . ($response['message'] ?? $response['error']));
        }

        // Update local DB after successful API call
        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $priceList) {
            if ($product->has_variants) {
                foreach ($priceList as $entry) {
                    if (isset($entry['model_id'])) {
                        ProductVariant::where('product_id', $product->id)
                            ->where('model_id', $entry['model_id'])
                            ->update([
                                'price' => $entry['original_price'] ?? 0,
                            ]);
                    }
                }
                $product->update([
                    'synced_at' => now(),
                    'needs_push' => false,
                ]);
            } else {
                $product->update([
                    'price' => $priceList[0]['original_price'] ?? 0,
                    'synced_at' => now(),
                    'needs_push' => false,
                ]);
            }
        });

        return $product->fresh()->load('variants');
    }

    /**
     * Update product/variant stock and price via the store marketplace API,
     * or locally only when $mode is "local" (marks needs_push).
     */
    public function updateStockAndPrice(
        User $user,
        int $id,
        ?array $stockList = null,
        ?array $priceList = null,
        string $mode = 'push',
    ): Product {
        if ($mode === 'local') {
            return $this->applyLocalStockAndPrice($user, $id, $stockList, $priceList);
        }

        $product = $this->getProduct($user, $id);

        if ($product->store->platform === 'lazada') {
            return $this->updateLazadaProduct($product, $stockList, $priceList);
        }

        if ($stockList) {
            $product = $this->updateStock($user, $id, $stockList);
        }

        if ($priceList) {
            try {
                $product = $this->updatePrice($user, $id, $priceList);
            } catch (\Exception $e) {
                if ($stockList) {
                    throw new \Exception("Stock was updated successfully, but price update failed: " . $e->getMessage());
                }
                throw $e;
            }
        }

        return $product;
    }

    /**
     * Persist stock/price locally and mark the product as needing marketplace push.
     *
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     */
    public function applyLocalStockAndPrice(
        User $user,
        int $id,
        ?array $stockList = null,
        ?array $priceList = null,
    ): Product {
        $product = $this->getProduct($user, $id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $stockList, $priceList) {
            if ($stockList) {
                $hasModelIds = collect($stockList)->contains(fn ($entry) => isset($entry['model_id']));
                if ($product->has_variants && $hasModelIds) {
                    foreach ($stockList as $entry) {
                        if (!isset($entry['model_id'])) {
                            continue;
                        }
                        $variant = ProductVariant::where('product_id', $product->id)
                            ->where('model_id', $entry['model_id'])
                            ->first();

                        $newStock = (int) ($entry['seller_stock'][0]['stock'] ?? 0);
                        if ($variant) {
                            $oldStock = $variant->stock;
                            $variant->update(['stock' => $newStock]);
                            $this->recordInventoryLog(
                                $product,
                                $variant->id,
                                $variant->model_sku,
                                $oldStock,
                                $newStock,
                                null,
                                null,
                                'local_staged',
                                'success'
                            );
                        }
                    }
                    $sum = (int) ProductVariant::where('product_id', $product->id)->sum('stock');
                    $product->update(['stock' => $sum, 'needs_push' => true]);
                } elseif ($product->has_variants) {
                    $newStock = (int) ($stockList[0]['seller_stock'][0]['stock'] ?? 0);
                    $variants = ProductVariant::where('product_id', $product->id)->get();
                    foreach ($variants as $variant) {
                        $oldStock = $variant->stock;
                        $variant->update(['stock' => $newStock]);
                        $this->recordInventoryLog(
                            $product,
                            $variant->id,
                            $variant->model_sku,
                            $oldStock,
                            $newStock,
                            null,
                            null,
                            'local_staged',
                            'success'
                        );
                    }
                    $count = max(1, $variants->count());
                    $product->update(['stock' => $newStock * $count, 'needs_push' => true]);
                } else {
                    $oldStock = $product->stock;
                    $newStock = (int) ($stockList[0]['seller_stock'][0]['stock'] ?? 0);
                    $product->update([
                        'stock' => $newStock,
                        'needs_push' => true,
                    ]);
                    $this->recordInventoryLog(
                        $product,
                        null,
                        $product->item_sku,
                        $oldStock,
                        $newStock,
                        null,
                        null,
                        'local_staged',
                        'success'
                    );
                }
            }

            if ($priceList) {
                $hasModelIds = collect($priceList)->contains(fn ($entry) => isset($entry['model_id']));
                if ($product->has_variants && $hasModelIds) {
                    foreach ($priceList as $entry) {
                        if (!isset($entry['model_id'])) {
                            continue;
                        }
                        $variant = ProductVariant::where('product_id', $product->id)
                            ->where('model_id', $entry['model_id'])
                            ->first();

                        $newPrice = (float) ($entry['original_price'] ?? 0);
                        if ($variant) {
                            $oldPrice = $variant->price;
                            $variant->update(['price' => $newPrice]);
                            $this->recordInventoryLog(
                                $product,
                                $variant->id,
                                $variant->model_sku,
                                null,
                                null,
                                $oldPrice,
                                $newPrice,
                                'local_staged',
                                'success'
                            );
                        }
                    }
                    $minPrice = ProductVariant::where('product_id', $product->id)->min('price');
                    $product->update([
                        'price' => $minPrice !== null ? (float) $minPrice : $product->price,
                        'needs_push' => true,
                    ]);
                } elseif ($product->has_variants) {
                    $newPrice = (float) ($priceList[0]['original_price'] ?? 0);
                    $variants = ProductVariant::where('product_id', $product->id)->get();
                    foreach ($variants as $variant) {
                        $oldPrice = $variant->price;
                        $variant->update(['price' => $newPrice]);
                        $this->recordInventoryLog(
                            $product,
                            $variant->id,
                            $variant->model_sku,
                            null,
                            null,
                            $oldPrice,
                            $newPrice,
                            'local_staged',
                            'success'
                        );
                    }
                    $product->update(['price' => $newPrice, 'needs_push' => true]);
                } else {
                    $oldPrice = $product->price;
                    $newPrice = (float) ($priceList[0]['original_price'] ?? 0);
                    $product->update([
                        'price' => $newPrice,
                        'needs_push' => true,
                    ]);
                    $this->recordInventoryLog(
                        $product,
                        null,
                        $product->item_sku,
                        null,
                        null,
                        $oldPrice,
                        $newPrice,
                        'local_staged',
                        'success'
                    );
                }
            }
        });

        return $product->fresh()->load(['variants', 'store']);
    }

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>|null  $stockList
     * @param  list<array{model_id?:int,original_price?:float|int|string}>|null  $priceList
     */
    private function updateLazadaProduct(Product $product, ?array $stockList, ?array $priceList): Product
    {
        $store = $product->store;
        if (!$store->token || !$store->is_active) {
            throw new StoreNotActiveException();
        }

        $product->loadMissing('variants');

        $skuUpdates = $this->lazada->buildPriceQuantitySkuUpdates($product, $stockList, $priceList);
        $payloadXml = $this->lazada->buildPriceQuantityPayload($skuUpdates);
        $accessToken = decrypt($store->token->access_token);
        $region = $store->region ?? 'sg';
        $response = $this->lazada->updatePriceQuantity($accessToken, $region, $payloadXml);

        if (($response['code'] ?? '0') !== '0') {
            throw new \RuntimeException($response['message'] ?? 'Lazada updatePriceQuantity failed.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $skuUpdates) {
            $productData = [];
            $updatedVariantIds = [];

            foreach ($skuUpdates as $update) {
                $skuId = $update['SkuId'] ?? null;

                if ($product->has_variants && $skuId !== null) {
                    $variant = ProductVariant::where('product_id', $product->id)
                        ->where('model_id', $skuId)
                        ->first();

                    if ($variant) {
                        $changes = [];
                        $oldStock = $variant->stock;
                        $oldPrice = $variant->price;
                        $newStock = null;
                        $newPrice = null;

                        if (array_key_exists('Quantity', $update)) {
                            $newStock = (int) $update['Quantity'];
                            $changes['stock'] = $newStock;
                        }
                        if (array_key_exists('Price', $update)) {
                            $newPrice = (float) $update['Price'];
                            $changes['price'] = $newPrice;
                        }
                        if ($changes !== []) {
                            $variant->update($changes);
                            $updatedVariantIds[] = $variant->id;
                            $this->recordInventoryLog(
                                $product,
                                $variant->id,
                                $variant->model_sku,
                                $newStock !== null ? $oldStock : null,
                                $newStock,
                                $newPrice !== null ? $oldPrice : null,
                                $newPrice,
                                'user',
                                'success'
                            );
                        }
                    }

                    continue;
                }

                $oldStock = $product->stock;
                $oldPrice = $product->price;
                $newStock = null;
                $newPrice = null;

                if (array_key_exists('Quantity', $update)) {
                    $newStock = (int) $update['Quantity'];
                    $productData['stock'] = $newStock;
                }
                if (array_key_exists('Price', $update)) {
                    $newPrice = (float) $update['Price'];
                    $productData['price'] = $newPrice;
                }

                if ($newStock !== null || $newPrice !== null) {
                    $this->recordInventoryLog(
                        $product,
                        null,
                        $product->item_sku,
                        $newStock !== null ? $oldStock : null,
                        $newStock,
                        $newPrice !== null ? $oldPrice : null,
                        $newPrice,
                        'user',
                        'success'
                    );
                }
            }

            if ($product->has_variants) {
                $freshVariants = ProductVariant::where('product_id', $product->id)->get();
                if ($freshVariants->isNotEmpty()) {
                    $productData['stock'] = (int) $freshVariants->sum('stock');
                    $productData['price'] = (float) $freshVariants->min('price');
                }
            }

            $productData['synced_at'] = now();
            $productData['needs_push'] = false;
            $product->update($productData);
        });

        return $product->fresh()->load('variants');
    }

    /**
     * Record an inventory audit log entry.
     */
    private function recordInventoryLog(
        Product $product,
        ?int $variantId,
        ?string $sku,
        ?int $oldStock,
        ?int $newStock,
        float|int|string|null $oldPrice,
        float|int|string|null $newPrice,
        string $source = 'user',
        string $status = 'success',
        ?string $errorMessage = null,
    ): InventoryLog {
        return InventoryLog::create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'sku' => $sku,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'old_price' => $oldPrice !== null ? (float) $oldPrice : null,
            'new_price' => $newPrice !== null ? (float) $newPrice : null,
            'source' => $source,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Synchronize stock across other stores matching the SKU.
     */
    public function syncLinkedStores(User $user, string $sku, int $newStock, int $originProductId): void
    {
        // 1. Find matching variants in other products (excluding origin product)
        $matchingVariants = ProductVariant::where('model_sku', $sku)
            ->where('product_id', '!=', $originProductId)
            ->whereHas('product.store', function ($q) use ($user) {
                $this->applyWorkspaceStoreScope($q, $user, true);
            })
            ->with('product.store')
            ->get();

        foreach ($matchingVariants as $variant) {
            $payload = [
                [
                    'model_id' => (int) $variant->model_id,
                    'seller_stock' => [
                        ['stock' => $newStock]
                    ]
                ]
            ];
            if ($variant->product?->store?->platform === 'lazada') {
                $this->updateStock($user, $variant->product_id, $payload);
            } else {
                \App\Jobs\UpdateShopeeStockJob::dispatch($user, $variant->product_id, $payload, 'linked_sync');
            }
        }

        // 2. Find matching simple products in other products (excluding origin product)
        $matchingProducts = Product::where('item_sku', $sku)
            ->where('has_variants', false)
            ->where('id', '!=', $originProductId)
            ->whereHas('store', function ($q) use ($user) {
                $this->applyWorkspaceStoreScope($q, $user, true);
            })
            ->with('store')
            ->get();

        foreach ($matchingProducts as $prod) {
            $payload = [
                [
                    'seller_stock' => [
                        ['stock' => $newStock]
                    ]
                ]
            ];
            if ($prod->store?->platform === 'lazada') {
                $this->updateStock($user, $prod->id, $payload);
            } else {
                \App\Jobs\UpdateShopeeStockJob::dispatch($user, $prod->id, $payload, 'linked_sync');
            }
        }
    }

    /**
     * Update stock for all products matching a SKU (cross-store consolidated update).
     * mode=push dispatches marketplace updates; mode=local writes MelurCom only.
     */
    public function updateStockBySku(User $user, string $sku, int $stock, string $mode = 'push'): array
    {
        $products = Product::where('item_sku', $sku)
            ->whereHas('store', function ($q) use ($user) {
                $this->applyWorkspaceStoreScope($q, $user, true);
            })
            ->with(['store', 'variants'])
            ->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException("No products found with SKU '{$sku}'.");
        }

        $results = [];

        foreach ($products as $product) {
            if ($mode === 'local') {
                if ($product->has_variants && $product->variants->isNotEmpty()) {
                    $payload = $product->variants->map(function (ProductVariant $variant) use ($stock) {
                        return [
                            'model_id' => (int) $variant->model_id,
                            'seller_stock' => [['stock' => $stock]],
                        ];
                    })->values()->all();
                } else {
                    $payload = [['seller_stock' => [['stock' => $stock]]]];
                }
                $this->applyLocalStockAndPrice($user, $product->id, $payload, null);
                $results[] = "Store #{$product->store_id}: saved locally";
                continue;
            }

            if (!$product->store->token || !$product->store->is_active) {
                $results[] = "Store #{$product->store_id}: skipped (inactive)";
                continue;
            }

            if ($product->has_variants && $product->variants->isNotEmpty()) {
                $payload = $product->variants->map(function (ProductVariant $variant) use ($stock) {
                    return [
                        'model_id' => (int) $variant->model_id,
                        'seller_stock' => [['stock' => $stock]],
                    ];
                })->values()->all();
            } else {
                $payload = [
                    ['seller_stock' => [['stock' => $stock]]]
                ];
            }

            $platform = $product->store->platform ?: 'shopee';
            try {
                if ($platform === 'lazada') {
                    $this->updateStock($user, $product->id, $payload);
                    $results[] = "Store #{$product->store_id} ({$platform}): updated";
                } else {
                    \App\Jobs\UpdateShopeeStockJob::dispatch($user, $product->id, $payload, 'user');
                    $results[] = "Store #{$product->store_id} ({$platform}): queued";
                }
            } catch (\Throwable $e) {
                $results[] = "Store #{$product->store_id} ({$platform}): error: ".$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Update price for all products matching a SKU (cross-store consolidated update).
     * mode=push dispatches marketplace updates; mode=local writes MelurCom only.
     */
    public function updatePriceBySku(User $user, string $sku, float $price, string $mode = 'push'): array
    {
        $products = Product::where('item_sku', $sku)
            ->whereHas('store', function ($q) use ($user) {
                $this->applyWorkspaceStoreScope($q, $user, true);
            })
            ->with(['store', 'variants'])
            ->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException("No products found with SKU '{$sku}'.");
        }

        $results = [];

        foreach ($products as $product) {
            if ($mode === 'local') {
                if ($product->has_variants && $product->variants->isNotEmpty()) {
                    $payload = $product->variants->map(function ($variant) use ($price) {
                        return [
                            'model_id' => (int) $variant->model_id,
                            'original_price' => $price,
                        ];
                    })->values()->all();
                } else {
                    $payload = [['original_price' => $price]];
                }
                $this->applyLocalStockAndPrice($user, $product->id, null, $payload);
                $results[] = "Store #{$product->store_id}: saved locally";
                continue;
            }

            if (!$product->store->token || !$product->store->is_active) {
                $results[] = "Store #{$product->store_id}: skipped (inactive)";
                continue;
            }

            if ($product->has_variants && $product->variants->isNotEmpty()) {
                $payload = $product->variants->map(function ($variant) use ($price) {
                    return [
                        'model_id' => (int) $variant->model_id,
                        'original_price' => $price,
                    ];
                })->values()->all();

            } else {
                $payload = [
                    ['original_price' => $price],
                ];
            }

            $platform = $product->store->platform ?: 'shopee';
            try {
                $this->updatePrice($user, $product->id, $payload);
                $results[] = "Store #{$product->store_id} ({$platform}): updated";
            } catch (\Throwable $e) {
                $results[] = "Store #{$product->store_id} ({$platform}): error: ".$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Queue catalog pulls (Shopee → MelurCom). Staggers full-store jobs to reduce rate limits.
     *
     * @param  list<int>|null  $productIds  Local product IDs for selective pull
     * @return list<array{store_id:int,shop_name:?string,mode:string,item_count:int|string,delay_seconds:int}>
     */
    public function queuePull(User $user, ?int $storeId = null, ?array $productIds = null): array
    {
        if ($productIds !== null && $productIds !== []) {
            return $this->queueSelectivePull($user, $productIds);
        }
        $stores = ShopeeStore::with('token')
            ->whereIn('workspace_id', $this->workspaceIds($user))
            ->where('is_active', true)
            ->when($storeId, fn ($q, $id) => $q->where('id', $id))
            ->orderBy('id')
            ->get();
        $queued = [];
        $delaySeconds = 0;
        $staggerSeconds = 120;
        foreach ($stores as $store) {
            if (! $store->token) {
                continue;
            }
            \App\Jobs\SyncShopeeStoreJob::dispatch($store->id)
                ->delay(now()->addSeconds($delaySeconds));
            $queued[] = [
                'store_id' => $store->id,
                'shop_name' => $store->shop_name,
                'mode' => 'full',
                'item_count' => 'all',
                'delay_seconds' => $delaySeconds,
            ];
            $delaySeconds += $staggerSeconds;
        }
        return $queued;
    }

    /**
     * Queue or execute push of local stock for selected marketplace products.
     *
     * @param  list<int>  $productIds
     * @return list<array{product_id:int,store_id:int,delay_seconds:int}>
     */
    public function queuePushSelected(User $user, array $productIds): array
    {
        $products = Product::with(['variants', 'store'])
            ->whereIn('id', $productIds)
            ->whereHas('store', fn ($q) => $this->applyWorkspaceStoreScope($q, $user, true))
            ->get();
        $queued = [];
        $delaySeconds = 0;
        foreach ($products as $index => $product) {
            if (! $product->store?->token) {
                continue;
            }
            $stockList = $this->buildStockListFromLocal($product);
            if ($stockList === []) {
                continue;
            }
            $delaySeconds = (int) floor($index / 5);
            if ($product->store->platform === 'lazada') {
                $this->updateStock($user, $product->id, $stockList);
                $delaySeconds = 0;
            } else {
                \App\Jobs\UpdateShopeeStockJob::dispatch($user, $product->id, $stockList, 'user')
                    ->delay(now()->addSeconds($delaySeconds));
                // Clear immediately when push is accepted; job restores needs_push on failure.
                $product->update(['needs_push' => false]);
            }
            $queued[] = [
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'delay_seconds' => $delaySeconds,
            ];
        }
        return $queued;
    }

    /**
     * @param  list<int>  $productIds
     * @return list<array{store_id:int,shop_name:?string,mode:string,item_count:int|string,delay_seconds:int}>
     */
    private function queueSelectivePull(User $user, array $productIds): array
    {
        $products = Product::with('store')
            ->whereIn('id', $productIds)
            ->whereHas('store', fn ($q) => $this->applyWorkspaceStoreScope($q, $user, true))
            ->get();
        $byStore = [];
        foreach ($products as $product) {
            if (! $product->store?->token) {
                continue;
            }
            $byStore[$product->store_id]['store'] = $product->store;
            $byStore[$product->store_id]['item_ids'][] = (int) $product->item_id;
        }
        $queued = [];
        $delaySeconds = 0;
        foreach ($byStore as $storeId => $payload) {
            $itemIds = array_values(array_unique($payload['item_ids']));
            if (($payload['store']->platform ?? 'shopee') === 'lazada') {
                \App\Jobs\SyncShopeeStoreJob::dispatch((int) $storeId)
                    ->delay(now()->addSeconds($delaySeconds));
            } else {
                \App\Jobs\SyncShopeeStoreJob::dispatch((int) $storeId, $itemIds)
                    ->delay(now()->addSeconds($delaySeconds));
            }
            $queued[] = [
                'store_id' => (int) $storeId,
                'shop_name' => $payload['store']->shop_name,
                'mode' => ($payload['store']->platform ?? 'shopee') === 'lazada' ? 'full' : 'selected',
                'item_count' => count($itemIds),
                'delay_seconds' => $delaySeconds,
            ];
            $delaySeconds += 30;
        }
        return $queued;
    }

    /**
     * @return list<array{model_id?:int,seller_stock:list<array{stock:int}>}>
     */
    private function buildStockListFromLocal(Product $product): array
    {
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            $list = [];
            foreach ($product->variants as $variant) {
                $list[] = [
                    'model_id' => (int) $variant->model_id,
                    'seller_stock' => [['stock' => (int) $variant->stock]],
                ];
            }
            return $list;
        }
        return [
            ['seller_stock' => [['stock' => (int) $product->stock]]],
        ];
    }

    /**
     * Pull selected Shopee item_ids for one store (base-info + models).
     *
     * @param  list<int>  $itemIds
     */
    public function syncItemsForStore(ShopeeStore $store, array $itemIds, ?callable $onProgress = null, ?callable $onError = null): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        if ($itemIds === []) {
            return;
        }
        try {
            $this->callWithTokenRetry($store, function ($client) use ($store, $itemIds, $onProgress, $onError) {
                foreach (array_chunk($itemIds, 50) as $chunk) {
                    $this->pullItemChunk($client, $store, $chunk, $onProgress, $onError);
                    $this->paceApiCall(100000);
                }
                return null;
            });
        } catch (StoreNotActiveException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw new StoreNotActiveException($e->getMessage());
        }
    }

    /**
     * Sync all products for a single store (called by job or command).
     */
    public function syncForStore(ShopeeStore $store, ?callable $onProgress = null, ?callable $onError = null): void
    {
        if ($store->platform === 'lazada') {
            $this->syncLazadaForStore($store);
            return;
        }

        try {
            $this->callWithTokenRetry($store, function ($client) use ($store, $onProgress, $onError) {
                $offset = 0;
                $pageSize = 100;
                do {
                    $response = $this->callShopeeWithRateLimitRetry(
                        fn () => $client->Product->getItemList([
                            'offset' => $offset,
                            'page_size' => $pageSize,
                            'item_status' => 'NORMAL',
                        ])
                    );
                    $items = ShopeeResponseHelper::extractItems($response);
                    if (empty($items)) {
                        break;
                    }
                    $itemIds = array_values(array_filter(array_map(function ($item) {
                        return $item['item_id'] ?? $item['id'] ?? null;
                    }, $items)));
                    if (empty($itemIds)) {
                        break;
                    }
                    foreach (array_chunk($itemIds, 50) as $chunk) {
                        try {
                            $this->pullItemChunk($client, $store, $chunk, $onProgress, $onError);
                        } catch (\Exception $e) {
                            if ($e instanceof AuthorizationException || ($e instanceof ShopeeException && $this->shopee->isTokenError($e))) {
                                throw $e;
                            }
                            if ($this->isRateLimitError($e)) {
                                throw $e;
                            }
                            if ($onError) {
                                $onError('Batch request failed for IDs '.implode(',', $chunk).": {$e->getMessage()}", $e);
                            }
                        }
                        $this->paceApiCall(100000);
                    }
                    $offset += $pageSize;
                    $this->paceApiCall(150000);
                } while (count($items) >= $pageSize);
                return null;
            });
        } catch (StoreNotActiveException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw new StoreNotActiveException($e->getMessage());
        }
    }

    /**
     * @param  list<int>  $chunk
     */
    private function pullItemChunk($client, ShopeeStore $store, array $chunk, ?callable $onProgress, ?callable $onError): void
    {
        $detail = $this->callShopeeWithRateLimitRetry(
            fn () => $client->Product->getItemBaseInfo(array_map('intval', $chunk))
        );
        $itemList = $detail['item_list'] ?? $detail['item'] ?? $detail['items'] ?? [];
        if (! is_array($itemList) || empty($itemList)) {
            $itemList = isset($detail['item']) ? [$detail['item']] : [$detail];
        }
        foreach ($itemList as $itemData) {
            $itemId = $itemData['item_id'] ?? $itemData['id'] ?? null;
            if (! $itemId) {
                continue;
            }
            try {
                $product = Product::updateOrCreate(
                    ['store_id' => $store->id, 'item_id' => $itemId],
                    [
                        'item_name' => $itemData['item_name'] ?? null,
                        'item_sku' => $itemData['item_sku'] ?? null,
                        'category_id' => $itemData['category_id'] ?? null,
                        'price' => ShopeeResponseHelper::extractPrice($itemData),
                        'stock' => ShopeeResponseHelper::extractStock($itemData),
                        'has_variants' => ShopeeResponseHelper::extractHasVariants($itemData),
                        'raw_response' => $itemData,
                        'synced_at' => now(),
                        'needs_push' => false,
                    ]
                );
                if ($product->has_variants) {
                    try {
                        $this->paceApiCall(80000);
                        $modelsResponse = $this->callShopeeWithRateLimitRetry(
                            fn () => $client->Product->getModelList(['item_id' => (int) $itemId])
                        );
                        $models = ShopeeResponseHelper::extractModels($modelsResponse);
                        foreach ($models as $model) {
                            $modelId = $model['model_id'] ?? null;
                            if (! $modelId) {
                                continue;
                            }
                            ProductVariant::updateOrCreate(
                                ['product_id' => $product->id, 'model_id' => $modelId],
                                [
                                    'name' => $model['model_name'] ?? null,
                                    'model_sku' => $model['model_sku'] ?? $model['sku'] ?? null,
                                    'price' => ShopeeResponseHelper::extractModelPrice($model),
                                    'stock' => ShopeeResponseHelper::extractModelStock($model),
                                    'raw_response' => $model,
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        if ($this->isRateLimitError($e) || ($e instanceof AuthorizationException) || ($e instanceof ShopeeException && $this->shopee->isTokenError($e))) {
                            throw $e;
                        }
                        if ($onError) {
                            $onError("Variant sync failed for item #{$itemId}: {$e->getMessage()}", $e);
                        }
                    }
                }
                if ($onProgress) {
                    $onProgress($product);
                }
            } catch (\Exception $e) {
                if ($this->isRateLimitError($e) || ($e instanceof AuthorizationException) || ($e instanceof ShopeeException && $this->shopee->isTokenError($e))) {
                    throw $e;
                }
                if ($onError) {
                    $onError("Item #{$itemId} failed to save: {$e->getMessage()}", $e);
                }
            }
        }
    }

    private function callShopeeWithRateLimitRetry(callable $callback, int $maxAttempts = 5): mixed
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                return $callback();
            } catch (\Exception $e) {
                if ($attempt >= $maxAttempts || ! $this->isRateLimitError($e)) {
                    throw $e;
                }
                usleep((int) (min(30, 2 ** $attempt) * 1_000_000));
            }
        }
    }

    private function isRateLimitError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'rate_limit')
            || str_contains($msg, 'too many requests')
            || str_contains($msg, 'error_limit');
    }

    private function paceApiCall(int $microseconds): void
    {
        usleep($microseconds);
    }

    /**
     * Run a Shopee API call; on Invalid access_token, refresh once and retry before disconnecting.
     *
     * Delegates to ShopeeService so refresh/retry semantics live in one place.
     */
    private function callWithTokenRetry(ShopeeStore $store, callable $callback): mixed
    {
        return $this->shopee->callWithTokenRetry(
            $store,
            $callback,
            fn (string $message): \Throwable => new StoreNotActiveException($message)
        );
    }

    /**
     * Sync all products for a Lazada store.
     */
    public function syncLazadaForStore(ShopeeStore $store): void
    {
        if (!$store->token || !$store->is_active) {
            return;
        }

        $accessToken = decrypt($store->token->access_token);
        $region = $store->region ?? 'sg';
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->lazada->getProducts($accessToken, $region, $offset, $limit);
            $data = $response['data'] ?? [];
            $products = $data['products'] ?? [];

            if (empty($products)) {
                break;
            }

            foreach ($products as $itemData) {
                $itemId = $itemData['item_id'] ?? null;
                if (!$itemId) {
                    continue;
                }

                $attributes = $itemData['attributes'] ?? [];
                $itemName = $attributes['name'] ?? "Lazada Item #{$itemId}";
                $skus = $itemData['skus'] ?? [];

                $hasVariants = count($skus) > 1;
                $firstSku = $skus[0] ?? [];
                $itemSku = $firstSku['SellerSku'] ?? null;
                $price = (float) ($firstSku['price'] ?? 0);
                $stock = (int) ($firstSku['quantity'] ?? 0);

                $product = Product::updateOrCreate(
                    ['store_id' => $store->id, 'item_id' => $itemId],
                    [
                        'item_name' => $itemName,
                        'item_sku' => $itemSku,
                        'category_id' => $itemData['primary_category'] ?? null,
                        'price' => $price,
                        'stock' => $stock,
                        'has_variants' => $hasVariants,
                        'raw_response' => $itemData,
                        'synced_at' => now(),
                        'needs_push' => false,
                    ]
                );

                if ($hasVariants) {
                    $totalStock = 0;
                    foreach ($skus as $skuData) {
                        $skuId = $skuData['SkuId'] ?? null;
                        if (!$skuId) {
                            continue;
                        }
                        $variantStock = (int) ($skuData['quantity'] ?? 0);
                        $variantPrice = (float) ($skuData['price'] ?? 0);
                        $variantName = $skuData['color_family'] ?? $skuData['size'] ?? $skuData['package_content'] ?? $skuData['SellerSku'] ?? "SKU #{$skuId}";

                        ProductVariant::updateOrCreate(
                            ['product_id' => $product->id, 'model_id' => $skuId],
                            [
                                'name' => $variantName,
                                'model_sku' => $skuData['SellerSku'] ?? null,
                                'price' => $variantPrice,
                                'stock' => $variantStock,
                                'raw_response' => $skuData,
                            ]
                        );
                        $totalStock += $variantStock;
                    }

                    $product->update([
                        'stock' => $totalStock,
                    ]);
                }
            }

            $offset += count($products);
            $totalProducts = (int) ($data['total_products'] ?? 0);
        } while ($offset < $totalProducts && count($products) >= $limit);
    }
}
