<?php

namespace App\Services;

use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class WorkspaceActivityLogger
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        User $user,
        string $action,
        string $summary,
        ?Model $subject = null,
        array $meta = [],
        ?Workspace $workspace = null,
    ): ?WorkspaceActivityLog {
        $workspace = $workspace ?? $this->resolveWorkspace($user, $subject);
        if (!$workspace) {
            return null;
        }

        return WorkspaceActivityLog::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }

    private function resolveWorkspace(User $user, ?Model $subject): ?Workspace
    {
        if ($subject instanceof ShopeeStore && $subject->workspace_id) {
            return $subject->workspace ?? Workspace::find($subject->workspace_id);
        }

        if ($subject && method_exists($subject, 'store')) {
            $subject->loadMissing('store');
            $store = $subject->store ?? null;
            if ($store instanceof ShopeeStore && $store->workspace_id) {
                return $store->workspace ?? Workspace::find($store->workspace_id);
            }
        }

        return $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);
    }
}
