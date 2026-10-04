<?php

namespace App\Helpers;

class ShopeeResponseHelper
{
    public static function extractItems(array $response): array
    {
        return $response['item_list'] ?? $response['items'] ?? $response['item'] ?? [];
    }

    public static function extractItemData(array $detail): array
    {
        return $detail['item'] ?? $detail['item_list'][0] ?? $detail;
    }

    public static function extractStock(array $itemData): int
    {
        return (int) ($itemData['stock'] ?? ($itemData['stock_info_v2']['summary_info']['total_available_stock'] ?? 0));
    }

    public static function extractPrice(array $itemData): float
    {
        return (float) ($itemData['price'] ?? ($itemData['price_info'][0]['original_price'] ?? 0));
    }

    public static function extractHasVariants(array $itemData): bool
    {
        return !empty($itemData['has_variants']) || !empty($itemData['tier_variation']);
    }

    public static function extractModels(array $modelsResponse): array
    {
        return $modelsResponse['model'] ?? $modelsResponse['model_list'] ?? [];
    }

    public static function extractModelStock(array $model): int
    {
        return (int) ($model['stock'] ?? ($model['stock_info_v2']['summary_info']['total_available_stock'] ?? 0));
    }

    public static function extractModelPrice(array $model): float
    {
        return (float) ($model['original_price'] ?? $model['price'] ?? 0);
    }
}
