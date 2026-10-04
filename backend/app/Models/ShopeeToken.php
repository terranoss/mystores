<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeToken extends Model
{
    protected $fillable = [
        'store_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'auth_expires_at',
        'last_refreshed_at',
        'last_refresh_attempt_at',
        'last_refresh_failed_at',
        'last_refresh_error',
        'refresh_failure_count',
    ];

    protected function casts(): array
    {
        return [
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'auth_expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'last_refresh_attempt_at' => 'datetime',
            'last_refresh_failed_at' => 'datetime',
            'refresh_failure_count' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ShopeeStore::class, 'store_id');
    }
}
