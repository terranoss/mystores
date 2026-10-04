<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Services\WorkspaceActivityLogger;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateStockRequest;
use App\Http\Requests\UpdatePriceRequest;
use App\Http\Requests\UpdateStockPriceRequest;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    /**
     * List products for the user's stores.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getProducts(
            $request->user(),
            $request->integer('store_id'),
            $request->string('search')->toString(),
            $request->string('sort_by')->toString(),
            $request->string('sort_dir', 'asc')->toString(),
        );

        return response()->json($products);
    }

    /**
     * Get a single product with variants.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $product = $this->productService->getProduct($request->user(), $id);

        return response()->json(['product' => $product]);
    }

    /**
     * Update product/variant stock via Shopee API.
     */
    public function updateStock(UpdateStockRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $product = $this->productService->updateStock(
            $request->user(),
            $id,
            $validated['stock_list']
        );

        $this->activityLogger->log(
            $request->user(),
            'product.update_stock',
            'Updated stock for '.($product->item_name ?: "item {$product->item_id}"),
            $product,
            ['item_id' => $product->item_id, 'item_sku' => $product->item_sku]
        );

        return response()->json([
            'message' => 'Stock updated successfully.',
            'product' => $product,
        ]);
    }

    /**
     * Update product/variant price via Shopee API.
     */
    public function updatePrice(UpdatePriceRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $product = $this->productService->updatePrice(
            $request->user(),
            $id,
            $validated['price_list']
        );

        $this->activityLogger->log(
            $request->user(),
            'product.update_price',
            'Updated price for '.($product->item_name ?: "item {$product->item_id}"),
            $product,
            ['item_id' => $product->item_id, 'item_sku' => $product->item_sku]
        );

        return response()->json([
            'message' => 'Price updated successfully.',
            'product' => $product,
        ]);
    }

    /**
     * Update product/variant stock and price via Shopee API.
     */
    public function updateStockAndPrice(\App\Http\Requests\UpdateStockPriceRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $product = $this->productService->updateStockAndPrice(
            $request->user(),
            $id,
            $validated['stock_list'] ?? null,
            $validated['price_list'] ?? null,
            $validated['mode'] ?? 'push',
        );

        $this->activityLogger->log(
            $request->user(),
            'product.update_stock_price',
            (($validated['mode'] ?? 'push') === 'local' ? 'Saved local stock/price for ' : 'Updated stock/price for ')
                .($product->item_name ?: "item {$product->item_id}"),
            $product,
            [
                'item_id' => $product->item_id,
                'item_sku' => $product->item_sku,
                'mode' => $validated['mode'] ?? 'push',
                'needs_push' => (bool) $product->needs_push,
            ]
        );

        return response()->json([
            'message' => ($validated['mode'] ?? 'push') === 'local'
                ? 'Saved locally. Use Push selected to sync to the marketplace.'
                : 'Stock and Price updated successfully.',
            'product' => $product,
        ]);
    }

    /**
     * Queue catalog pull from Shopee (async). Prefer store_id or product_ids for large catalogs.
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer|min:1',
            'product_ids' => 'nullable|array|max:200',
            'product_ids.*' => 'integer|min:1',
        ]);
        $queued = $this->productService->queuePull(
            $request->user(),
            isset($validated['store_id']) ? (int) $validated['store_id'] : null,
            $validated['product_ids'] ?? null,
        );

        $this->activityLogger->log(
            $request->user(),
            'product.pull',
            'Queued product pull ('.count($queued).' job(s))',
            null,
            ['queued' => count($queued), 'store_id' => $validated['store_id'] ?? null]
        );

        return response()->json([
            'message' => 'Pull queued.',
            'status' => 'sync_started',
            'queued' => $queued,
        ], 202);
    }

    /**
     * Queue push of local stock to Shopee for selected products.
     */
    public function pushSelected(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:200',
            'product_ids.*' => 'integer|min:1',
        ]);
        $queued = $this->productService->queuePushSelected(
            $request->user(),
            $validated['product_ids']
        );

        $this->activityLogger->log(
            $request->user(),
            'product.push',
            'Queued stock push for '.count($queued).' product(s)',
            null,
            ['queued' => count($queued)]
        );

        return response()->json([
            'message' => 'Push queued for '.count($queued).' product(s).',
            'status' => 'push_started',
            'queued' => $queued,
        ], 202);
    }

    /**
     * @deprecated Use pull(). Kept for compatibility; now queues async pull.
     */
    public function sync(Request $request): JsonResponse
    {
        return $this->pull($request);
    }

    /**
     * List products grouped by SKU for cross-store consolidated view.
     */
    public function indexGrouped(Request $request): JsonResponse
    {
        $result = $this->productService->getProductsGroupedBySku(
            $request->user(),
            $request->filled('store_id') ? $request->integer('store_id') : null,
            $request->filled('search') ? $request->string('search')->toString() : null,
            $request->filled('stock_health') ? $request->string('stock_health')->toString() : null,
            $request->filled('link_status') ? $request->string('link_status')->toString() : null,
            $request->filled('sort_by') ? $request->string('sort_by')->toString() : null,
            $request->string('sort_dir', 'asc')->toString(),
            $request->filled('sync_state') ? $request->string('sync_state')->toString() : null,
        );

        return response()->json($result);
    }

    /**
     * Update stock for all products matching a SKU (cross-store).
     */
    public function updateStockBySku(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'stock' => 'required|integer|min:0|max:999999',
            'mode' => 'nullable|string|in:local,push',
        ]);

        $mode = $validated['mode'] ?? 'push';
        $results = $this->productService->updateStockBySku(
            $request->user(),
            $validated['sku'],
            $validated['stock'],
            $mode,
        );

        $this->activityLogger->log(
            $request->user(),
            'product.update_stock_by_sku',
            ($mode === 'local' ? 'Saved local stock' : 'Updated stock')." by SKU {$validated['sku']} to {$validated['stock']}",
            null,
            ['sku' => $validated['sku'], 'stock' => $validated['stock'], 'stores' => count($results), 'mode' => $mode]
        );

        return response()->json([
            'message' => $mode === 'local'
                ? 'Stock saved locally for '.count($results).' listing(s). Use Push selected to sync.'
                : 'Stock update dispatched for '.count($results).' store(s).',
            'results' => $results,
        ]);
    }

    /**
     * Update price for all products matching a SKU (cross-store).
     */
    public function updatePriceBySku(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:100000000',
            'mode' => 'nullable|string|in:local,push',
        ]);

        $mode = $validated['mode'] ?? 'push';
        $results = $this->productService->updatePriceBySku(
            $request->user(),
            $validated['sku'],
            (float) $validated['price'],
            $mode,
        );

        $this->activityLogger->log(
            $request->user(),
            'product.update_price_by_sku',
            ($mode === 'local' ? 'Saved local price' : 'Updated price')." by SKU {$validated['sku']} to {$validated['price']}",
            null,
            ['sku' => $validated['sku'], 'price' => $validated['price'], 'stores' => count($results), 'mode' => $mode]
        );

        return response()->json([
            'message' => $mode === 'local'
                ? 'Price saved locally for '.count($results).' listing(s). Use Push selected to sync.'
                : 'Price update dispatched for '.count($results).' store(s).',
            'results' => $results,
        ]);
    }
}
