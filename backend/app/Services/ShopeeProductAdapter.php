<?php

namespace App\Services;

use App\Exceptions\StoreNotActiveException;
use App\Models\Product;
use App\Models\ShopeeStore;
use GuzzleHttp\RequestOptions;

/**
 * Shopee product API adapter.
 *
 * Wraps the Shopee SDK product calls in the existing token-retry path so the
 * gateway never touches the raw client itself.
 */
class ShopeeProductAdapter implements MarketplaceProductAdapter
{
    public function __construct(private readonly ShopeeService $shopee) {}

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>  $stockList
     * @return array<string, mixed>
     */
    public function pushStock(ShopeeStore $store, Product $product, array $stockList): array
    {
        // Explicit casts prevent Go unmarshalling errors in the Shopee API.
        $formattedStockList = array_map(function ($entry) {
            $formatted = [];

            if (isset($entry['model_id'])) {
                $formatted['model_id'] = (int) $entry['model_id'];
            }

            if (isset($entry['seller_stock'])) {
                $formatted['seller_stock'] = array_map(
                    fn ($stockEntry) => ['stock' => (int) $stockEntry['stock']],
                    $entry['seller_stock'],
                );
            }

            return $formatted;
        }, $stockList);

        return $this->assertNoApiError($this->call($store, 'POST', 'product/update_stock', [
            RequestOptions::JSON => [
                'item_id' => (int) $product->item_id,
                'stock_list' => $formattedStockList,
            ],
        ]));
    }

    /**
     * @param  list<array{model_id?:int,original_price?:float|int|string}>  $priceList
     * @return array<string, mixed>
     */
    public function pushPrice(ShopeeStore $store, Product $product, array $priceList): array
    {
        // Explicit casts prevent Go unmarshalling errors in the Shopee API.
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

        return $this->assertNoApiError($this->call($store, 'POST', 'product/update_price', [
            RequestOptions::JSON => [
                'item_id' => (int) $product->item_id,
                'price_list' => $formattedPriceList,
            ],
        ]));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function fetchItemList(ShopeeStore $store, array $params): array
    {
        return (array) $this->run($store, fn ($client) => $client->Product->getItemList($params));
    }

    /**
     * @param  list<int|string>  $itemIds
     * @return array<string, mixed>
     */
    public function fetchItemDetails(ShopeeStore $store, array $itemIds): array
    {
        return (array) $this->run(
            $store,
            fn ($client) => $client->Product->getItemBaseInfo(array_map('intval', $itemIds)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchModels(ShopeeStore $store, int $itemId): array
    {
        return (array) $this->run(
            $store,
            fn ($client) => $client->Product->getModelList(['item_id' => $itemId]),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function call(ShopeeStore $store, string $method, string $path, array $options): array
    {
        return (array) $this->run($store, fn ($client) => $client->Product->call($method, $path, $options));
    }

    private function run(ShopeeStore $store, callable $callback): mixed
    {
        return $this->shopee->callWithTokenRetry(
            $store,
            $callback,
            fn (string $message): \Throwable => new StoreNotActiveException($message),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function assertNoApiError(array $response): array
    {
        if (! empty($response['error'])) {
            throw new \Exception('Shopee API Error: '.($response['message'] ?? $response['error']));
        }

        return $response;
    }
}
