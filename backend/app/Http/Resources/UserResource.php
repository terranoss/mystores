<?php

namespace App\Http\Resources;

use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = app(WorkspaceAccess::class)->primaryWorkspace($this->resource);
        $role = $workspace
            ? app(WorkspaceAccess::class)->roleInWorkspace($this->resource, $workspace)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'workspace_role' => $role,
            'workspace_id' => $workspace?->id,
            'is_workspace_owner' => $role === 'owner',
        ];
    }
}
