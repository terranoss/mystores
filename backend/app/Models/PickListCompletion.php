<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListCompletion extends Model
{
    public const KIND_TODAY = 'today';

    public const KIND_PENDING = 'pending';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_TODAY,
        self::KIND_PENDING,
    ];

    protected $fillable = [
        'workspace_id',
        'kind',
        'pick_date',
        'item_key',
        'item_id',
        'model_id',
        'item_name',
        'photo_path',
        'picked_by',
        'picked_at',
    ];

    protected function casts(): array
    {
        return [
            'pick_date' => 'date',
            'picked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public static function makeItemKey(mixed $itemId, mixed $modelId, ?string $itemName): string
    {
        $idPart = $itemId !== null && $itemId !== '' ? (string) $itemId : (string) ($itemName ?? '');
        $modelPart = $modelId !== null && $modelId !== '' ? (string) $modelId : '';

        return $idPart.'_'.$modelPart;
    }
}
