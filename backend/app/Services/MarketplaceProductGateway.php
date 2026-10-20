<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopeeStore;

/**
 * The only product code that talks to a marketplace's product API.
 *
 * Dispatches each operation to the Shopee or Lazada adapter so payload shapes,
 * token handling and per-call error handling live with the platform that
 * defines them.
 */
class MarketplaceProductGateway
{
    public function __construct(
        private readonly ShopeeProductAdapter $shopee,
        private readonly LazadaProductAdapter $lazada,
    ) {}

    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>  $stockList
     * @return array<string, mixed>
     */
    public function pushStock(ShopeeStore $store, Product $product, array $stockList): array
    {
        return $this->adapter($store)->pushStock($store, $product, $stockList);
    }

    /**
     * @param  list<array{model_id?:int,original_price?:float|int|string}>  $priceList
     * @return array<string, mixed>
     */
    public function pushPrice(ShopeeStore $store, Product $product, array $priceList): array
    {
        return $this->adapter($store)->pushPrice($store, $product, $priceList);
    }

    /**
     * Push one Lazada price/quantity batch built by the caller.
     *
     * Lazada accepts stock and price for the same SKU set in a single call, so
     * the combined update list is the only way to keep one API call per change.
     *
     * @param  list<array{SellerSku?:string,SkuId?:int|string,ItemId?:int|string,Quantity?:int,Price?:float|string}>  $skuUpdates
     * @return array<string, mixed>
     */
    public function pushSkuUpdates(ShopeeStore $store, array $skuUpdates): array
    {
        if ($this->adapter($store) !== $this->lazada) {
            throw new \LogicException('pushSkuUpdates is a Lazada-only gateway operation.');
        }

        return $this->lazada->pushSkuUpdates($store, $skuUpdates);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function fetchItemList(ShopeeStore $store, array $params): array
    {
        return $this->adapter($store)->fetchItemList($store, $params);
    }

    /**
     * @param  list<int|string>  $itemIds
     * @return array<int|string, mixed>
     */
    public function fetchItemDetails(ShopeeStore $store, array $itemIds): array
    {
        return $this->adapter($store)->fetchItemDetails($store, $itemIds);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function fetchModels(ShopeeStore $store, int $itemId): array
    {
        return $this->adapter($store)->fetchModels($store, $itemId);
    }

    private function adapter(ShopeeStore $store): MarketplaceProductAdapter
    {
        return ($store->platform ?? 'shopee') === 'lazada' ? $this->lazada : $this->shopee;
    }
}
