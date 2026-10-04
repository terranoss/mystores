<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListCommentRead extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'kind',
        'pick_date',
        'item_key',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'pick_date' => 'date',
            'last_read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
