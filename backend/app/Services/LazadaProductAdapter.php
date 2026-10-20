<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopeeStore;

/**
 * Lazada product API adapter.
 *
 * Lazada rotates its refresh token on every use, so this adapter borrows the
 * order service's per-store locked refresh before any product call. Concurrent
 * product and order calls therefore wait on the same mutex instead of burning
 * each other's single-use refresh token.
 */
class LazadaProductAdapter implements MarketplaceProductAdapter
{
    public function __construct(
        private readonly LazadaService $lazada,
        private readonly LazadaOrderService $orderService,
    ) {}

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>  $stockList
     * @return array<string, mixed>
     */
    public function pushStock(ShopeeStore $store, Product $product, array $stockList): array
    {
        return $this->pushSkuUpdates(
            $store,
            $this->lazada->buildPriceQuantitySkuUpdates($product, $stockList, null),
        );
    }

    /**
     * @param  list<array{model_id?:int,original_price?:float|int|string}>  $priceList
     * @return array<string, mixed>
     */
    public function pushPrice(ShopeeStore $store, Product $product, array $priceList): array
    {
        return $this->pushSkuUpdates(
            $store,
            $this->lazada->buildPriceQuantitySkuUpdates($product, null, $priceList),
        );
    }

    /**
     * Push one Lazada price/quantity batch, refreshing the token under the
     * order service's per-store lock first.
     *
     * Lazada accepts stock and price for the same SKU set in a single call, so
     * callers that change both pass one merged update list.
     *
     * @param  list<array{SellerSku?:string,SkuId?:int|string,ItemId?:int|string,Quantity?:int,Price?:float|string}>  $skuUpdates
     * @return array<string, mixed>
     */
    public function pushSkuUpdates(ShopeeStore $store, array $skuUpdates): array
    {
        $this->orderService->ensureFreshToken($store);

        $payloadXml = $this->lazada->buildPriceQuantityPayload($skuUpdates);

        $response = $this->lazada->updatePriceQuantity(
            $this->accessToken($store),
            $store->region ?? 'sg',
            $payloadXml,
        );

        if (($response['code'] ?? '0') !== '0') {
            throw new \RuntimeException($response['message'] ?? 'Lazada updatePriceQuantity failed.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function fetchItemList(ShopeeStore $store, array $params): array
    {
        $this->orderService->ensureFreshToken($store);

        return $this->lazada->getProducts(
            $this->accessToken($store),
            $store->region ?? 'sg',
            (int) ($params['offset'] ?? 0),
            (int) ($params['limit'] ?? 50),
            (string) ($params['filter'] ?? 'all'),
        );
    }

    /**
     * Lazada returns one detail payload per item id.
     *
     * @param  list<int|string>  $itemIds
     * @return list<array<string, mixed>>
     */
    public function fetchItemDetails(ShopeeStore $store, array $itemIds): array
    {
        $this->orderService->ensureFreshToken($store);

        $accessToken = $this->accessToken($store);
        $region = $store->region ?? 'sg';
        $details = [];

        foreach (array_map('intval', $itemIds) as $itemId) {
            $details[] = $this->lazada->getItemDetail($accessToken, $region, $itemId);
        }

        return $details;
    }

    /**
     * Lazada exposes variant models on the item detail payload.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchModels(ShopeeStore $store, int $itemId): array
    {
        $this->orderService->ensureFreshToken($store);

        $response = $this->lazada->getItemDetail(
            $this->accessToken($store),
            $store->region ?? 'sg',
            $itemId,
        );

        $skus = $response['data']['skus'] ?? [];

        return is_array($skus) ? $skus : [];
    }

    private function accessToken(ShopeeStore $store): string
    {
        $store->loadMissing('token');

        if (! $store->token) {
            throw new \RuntimeException("Store #{$store->id} has no Lazada token.");
        }

        return decrypt($store->token->access_token);
    }
}
