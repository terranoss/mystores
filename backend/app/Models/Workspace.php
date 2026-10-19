<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'owner_user_id',
        'public_id',
    ];

    private static ?bool $hasPublicIdColumn = null;

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->public_id)) {
                if (static::$hasPublicIdColumn === null) {
                    static::$hasPublicIdColumn = \Illuminate\Support\Facades\Schema::hasColumn('workspaces', 'public_id');
                }
                if (static::$hasPublicIdColumn) {
                    $workspace->public_id = (string) \Illuminate\Support\Str::uuid();
                }
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WorkspaceInvite::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(ShopeeStore::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(WorkspaceCredential::class);
    }
}
