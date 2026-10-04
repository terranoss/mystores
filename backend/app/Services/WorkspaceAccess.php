<?php

namespace App\Services;

use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceAccess
{
    /**
     * Ensure the user owns a personal workspace (create if missing).
     */
    public function ensurePersonalWorkspace(User $user): Workspace
    {
        $existing = WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->where('role', WorkspaceMember::ROLE_OWNER)
            ->with('workspace')
            ->first();

        if ($existing?->workspace) {
            return $existing->workspace;
        }

        return DB::transaction(function () use ($user) {
            $workspace = Workspace::create([
                'name' => $user->name ? ($user->name.' Workspace') : 'Workspace',
                'owner_user_id' => $user->id,
            ]);
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceMember::ROLE_OWNER,
            ]);
            return $workspace;
        });
    }

    /**
     * Primary workspace for the user (prefer workspaces that have stores).
     */
    public function primaryWorkspace(User $user): ?Workspace
    {
        $ownedWithStores = Workspace::query()
            ->where('owner_user_id', $user->id)
            ->whereHas('stores')
            ->orderBy('id')
            ->first();
        if ($ownedWithStores) {
            return $ownedWithStores;
        }

        $memberWithStores = WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->whereHas('workspace.stores')
            ->with('workspace')
            ->orderBy('id')
            ->first();
        if ($memberWithStores?->workspace) {
            return $memberWithStores->workspace;
        }

        $owned = WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->where('role', WorkspaceMember::ROLE_OWNER)
            ->with('workspace')
            ->first();
        if ($owned?->workspace) {
            return $owned->workspace;
        }

        $member = WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->with('workspace')
            ->orderBy('id')
            ->first();

        return $member?->workspace;
    }

    /**
     * @return list<int>
     */
    public function workspaceIdsFor(User $user): array
    {
        return WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->pluck('workspace_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessStore(User $user, ShopeeStore $store): bool
    {
        if ($store->workspace_id === null) {
            return (int) $store->user_id === (int) $user->id;
        }
        return in_array((int) $store->workspace_id, $this->workspaceIdsFor($user), true);
    }

    public function isOwnerOfWorkspace(User $user, Workspace $workspace): bool
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('role', WorkspaceMember::ROLE_OWNER)
            ->exists();
    }

    public function isOwnerOfStore(User $user, ShopeeStore $store): bool
    {
        if ($store->workspace_id === null) {
            return (int) $store->user_id === (int) $user->id;
        }
        $workspace = $store->workspace ?? Workspace::find($store->workspace_id);
        if (!$workspace) {
            return false;
        }
        return $this->isOwnerOfWorkspace($user, $workspace);
    }

    public function roleInWorkspace(User $user, Workspace $workspace): ?string
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->value('role');
    }

    /**
     * @return Collection<int, WorkspaceMember>
     */
    public function members(Workspace $workspace): Collection
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->with('user:id,name,email')
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();
    }

    public function invite(User $owner, Workspace $workspace, string $email): WorkspaceInvite
    {
        if (!$this->isOwnerOfWorkspace($owner, $workspace)) {
            throw new \RuntimeException('Only the workspace owner can invite members.');
        }
        $email = strtolower(trim($email));
        if ($email === strtolower($owner->email)) {
            throw new \RuntimeException('You cannot invite yourself.');
        }
        $alreadyMember = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))
            ->exists();
        if ($alreadyMember) {
            throw new \RuntimeException('That user is already a workspace member.');
        }

        WorkspaceInvite::query()
            ->where('workspace_id', $workspace->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->delete();

        return WorkspaceInvite::create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'token' => WorkspaceInvite::makeToken(),
            'role' => WorkspaceMember::ROLE_MEMBER,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function acceptInvite(User $user, string $token): WorkspaceMember
    {
        $invite = WorkspaceInvite::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->first();
        if (!$invite || $invite->expires_at->isPast()) {
            throw new \RuntimeException('Invite is invalid or expired.');
        }
        if (strtolower($user->email) !== strtolower($invite->email)) {
            throw new \RuntimeException('This invite was sent to a different email address.');
        }
        if (WorkspaceMember::query()
            ->where('workspace_id', $invite->workspace_id)
            ->where('user_id', $user->id)
            ->exists()) {
            $invite->update(['accepted_at' => now()]);
            return WorkspaceMember::query()
                ->where('workspace_id', $invite->workspace_id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($user, $invite) {
            $member = WorkspaceMember::create([
                'workspace_id' => $invite->workspace_id,
                'user_id' => $user->id,
                'role' => WorkspaceMember::ROLE_MEMBER,
            ]);
            $invite->update(['accepted_at' => now()]);
            return $member;
        });
    }

    public function removeMember(User $owner, Workspace $workspace, int $memberId): void
    {
        if (!$this->isOwnerOfWorkspace($owner, $workspace)) {
            throw new \RuntimeException('Only the workspace owner can remove members.');
        }
        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $memberId)
            ->firstOrFail();
        if ($member->role === WorkspaceMember::ROLE_OWNER) {
            throw new \RuntimeException('Cannot remove the workspace owner.');
        }
        $member->delete();
    }

    /**
     * Accept any pending invites matching the user's email (e.g. after account create).
     */
    public function acceptPendingInvitesForEmail(User $user): int
    {
        $invites = WorkspaceInvite::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();
        $count = 0;
        foreach ($invites as $invite) {
            try {
                $this->acceptInvite($user, $invite->token);
                $count++;
            } catch (\Throwable) {
                // skip conflicts
            }
        }
        return $count;
    }
}
