<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeOAuthState extends Model
{
    protected $table = 'shopee_oauth_states';

    protected $fillable = [
        'user_id',
        'store_id',
        'platform',
        'state_hash',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'state_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
