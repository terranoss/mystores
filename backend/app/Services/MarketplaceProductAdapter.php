<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShopeeStore;

/**
 * One marketplace's product API, behind the gateway.
 *
 * Adapters own exactly one page or one marketplace call each; pagination and
 * sync orchestration stay in ProductService.
 */
interface MarketplaceProductAdapter
{
    /**
     * @param  list<array{model_id?:int,seller_stock?:list<array{stock:int}>}>  $stockList
     * @return array<string, mixed>
     */
    public function pushStock(ShopeeStore $store, Product $product, array $stockList): array;

    /**
     * @param  list<array{model_id?:int,original_price?:float|int|string}>  $priceList
     * @return array<string, mixed>
     */
    public function pushPrice(ShopeeStore $store, Product $product, array $priceList): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function fetchItemList(ShopeeStore $store, array $params): array;

    /**
     * @param  list<int|string>  $itemIds
     * @return array<int|string, mixed>
     */
    public function fetchItemDetails(ShopeeStore $store, array $itemIds): array;

    /**
     * @return array<int|string, mixed>
     */
    public function fetchModels(ShopeeStore $store, int $itemId): array;
}
