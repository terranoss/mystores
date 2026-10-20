<?php

namespace App\Models;

use App\Services\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'store_id',
        'order_sn',
        'order_status',
        'total_amount',
        'currency',
        'buyer_username',
        'shipping_address',
        'ordered_at',
        'paid_time',
        'ship_by_date',
        'cancel_reason',
        'raw_response',
        'synced_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'paid_time' => 'datetime',
        'ship_by_date' => 'datetime',
        'raw_response' => 'array',
        'synced_at' => 'datetime',
    ];

    protected $appends = [
        'canonical_status',
    ];

    /**
     * Platform-independent status derived from the raw order_status value.
     *
     * Deliberately does not load the store: the mapping needs no platform hint,
     * and one is passed only when the relation is already in memory.
     */
    protected function canonicalStatus(): Attribute
    {
        return Attribute::get(function (): ?string {
            $platform = $this->relationLoaded('store')
                ? $this->getRelation('store')?->platform
                : null;

            return OrderStatus::canonical($this->order_status, $platform);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopeeStore::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getLazadaInvoiceNo(): ?string
    {
        $items = $this->raw_response['items'] ?? [];
        if (! is_array($items)) {
            return null;
        }

        foreach ($items as $item) {
            $inv = trim((string) ($item['invoice_number'] ?? ''));
            if ($inv !== '') {
                return $inv;
            }
        }

        return null;
    }

    public function resolveInvoiceNo(): string
    {
        $platform = strtolower((string) ($this->store?->platform ?? ''));
        if ($platform === 'lazada') {
            $lazadaInv = $this->getLazadaInvoiceNo();
            if ($lazadaInv !== null && $lazadaInv !== '') {
                return $lazadaInv;
            }
        }

        return (string) $this->order_sn;
    }
}
