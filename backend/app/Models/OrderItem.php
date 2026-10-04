<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'item_id',
        'model_id',
        'item_name',
        'sku',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected $appends = [
        'sku',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getSkuAttribute(): ?string
    {
        if (!empty($this->attributes['sku'])) {
            return (string) $this->attributes['sku'];
        }

        // 1. If variant model_id exists, try to get model_sku
        if (!empty($this->model_id)) {
            $variant = ProductVariant::where('model_id', $this->model_id)->first();
            if ($variant && !empty($variant->model_sku)) {
                return (string) $variant->model_sku;
            }
        }

        // 2. Try to get product item_sku
        if (!empty($this->item_id)) {
            $productQuery = Product::where('item_id', $this->item_id);
            if ($this->relationLoaded('order') && $this->order?->store_id) {
                $productQuery->where('store_id', $this->order->store_id);
            }
            $product = $productQuery->first();
            if ($product && !empty($product->item_sku)) {
                return (string) $product->item_sku;
            }
        }

        // 3. Try to extract SKU from order raw_response
        if ($this->relationLoaded('order') && $this->order && is_array($this->order->raw_response)) {
            $raw = $this->order->raw_response;
            // Shopee raw response
            if (isset($raw['item_list']) && is_array($raw['item_list'])) {
                foreach ($raw['item_list'] as $rawItem) {
                    $rawItemId = (int) ($rawItem['item_id'] ?? $rawItem['id'] ?? 0);
                    if ($rawItemId === (int) $this->item_id) {
                        $rawModelId = isset($rawItem['model_id']) ? (int) $rawItem['model_id'] : null;
                        if ($this->model_id !== null && $rawModelId === (int) $this->model_id) {
                            if (!empty($rawItem['model_sku'])) return (string) $rawItem['model_sku'];
                        }
                        if (!empty($rawItem['item_sku'])) return (string) $rawItem['item_sku'];
                        if (!empty($rawItem['model_sku'])) return (string) $rawItem['model_sku'];
                    }
                }
            }
            // Lazada raw response
            if (isset($raw['items']) && is_array($raw['items'])) {
                foreach ($raw['items'] as $rawItem) {
                    $rawItemId = (int) ($rawItem['order_item_id'] ?? $rawItem['item_id'] ?? 0);
                    if ($rawItemId === (int) $this->item_id) {
                        if (!empty($rawItem['sku'])) return (string) $rawItem['sku'];
                        if (!empty($rawItem['seller_sku'])) return (string) $rawItem['seller_sku'];
                    }
                }
            }
        }

        return null;
    }
}
