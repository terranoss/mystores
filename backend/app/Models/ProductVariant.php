<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'model_id',
        'name',
        'price',
        'stock',
        'raw_response',
        'model_sku',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
