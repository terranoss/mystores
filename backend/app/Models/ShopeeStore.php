<?php

namespace App\Models;

use App\Services\WorkspaceAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopeeStore extends Model
{
    protected $attributes = [
        'platform' => 'shopee',
    ];

    protected $fillable = [
        'user_id',
        'workspace_id',
        'platform',
        'shop_id',
        'shop_name',
        'region',
        'is_active',
        'orders_synced_at',
        'vacation_mode',
        'vacation_mode_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'orders_synced_at' => 'datetime',
            'vacation_mode' => 'boolean',
            'vacation_mode_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShopeeStore $store) {
            if ($store->workspace_id || !$store->user_id) {
                return;
            }
            $user = User::find($store->user_id);
            if ($user) {
                $store->workspace_id = app(WorkspaceAccess::class)
                    ->ensurePersonalWorkspace($user)
                    ->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function token(): HasOne
    {
        return $this->hasOne(ShopeeToken::class, 'store_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'store_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'store_id');
    }
}
