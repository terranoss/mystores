<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListComment extends Model
{
    protected $fillable = [
        'workspace_id',
        'kind',
        'pick_date',
        'item_key',
        'item_id',
        'model_id',
        'item_name',
        'user_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'pick_date' => 'date',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{id: int, body: string, created_at: string|null, user: array{id: int, name: string}}
     */
    public function toApiPayload(): array
    {
        $this->loadMissing('user:id,name');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => [
                'id' => (int) $this->user_id,
                'name' => $this->user?->name ?? 'Unknown',
            ],
        ];
    }
}
