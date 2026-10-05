<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceCredential extends Model
{
    protected $fillable = [
        'workspace_id',
        'platform',
        'partner_id',
        'partner_key',
        'push_partner_key',
        'app_key',
        'app_secret',
        'redirect_url',
        'is_active',
    ];

    protected $casts = [
        'partner_key' => 'encrypted',
        'push_partner_key' => 'encrypted',
        'app_secret' => 'encrypted',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
