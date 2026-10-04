<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'item_id',
        'item_name',
        'category_id',
        'price',
        'stock',
        'has_variants',
        'raw_response',
        'synced_at',
        'needs_push',
        'item_sku',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_variants' => 'boolean',
        'needs_push' => 'boolean',
        'raw_response' => 'array',
        'synced_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopeeStore::class, 'store_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
