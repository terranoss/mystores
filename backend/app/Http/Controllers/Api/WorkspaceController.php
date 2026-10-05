<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceActivityLog;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user);
        if (!$workspace) {
            $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
        }
        $role = $this->workspaceAccess->roleInWorkspace($user, $workspace);
        $members = $this->workspaceAccess->members($workspace);
        $pendingInvites = $workspace->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get(['id', 'email', 'role', 'expires_at', 'created_at']);

        return response()->json([
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'role' => $role,
                'is_owner' => $role === 'owner',
            ],
            'members' => $members->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'role' => $m->role,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
            ]),
            'pending_invites' => $pendingInvites,
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user);
        if (!$workspace) {
            $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
        }
        $role = $this->workspaceAccess->roleInWorkspace($user, $workspace);
        $isOwner = $role === WorkspaceMember::ROLE_OWNER;

        $query = WorkspaceActivityLog::query()
            ->where('workspace_id', $workspace->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (!$isOwner) {
            $query->where('user_id', $user->id);
        } elseif (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $logs = $query->paginate(30);

        return response()->json($logs);
    }

    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        try {
            $invite = $this->workspaceAccess->invite($user, $workspace, $validated['email']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogger->log(
            $user,
            'team.invite',
            "Invited {$invite->email} to the workspace",
            null,
            ['email' => $invite->email],
            $workspace
        );

        return response()->json([
            'message' => 'Invite created. Share the token with the teammate to accept after they log in.',
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'token' => $invite->token,
                'expires_at' => $invite->expires_at,
            ],
        ], 201);
    }

    public function acceptInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|size:64',
        ]);
        try {
            $member = $this->workspaceAccess->acceptInvite($request->user(), $validated['token']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $workspace = $member->workspace;
        $this->activityLogger->log(
            $request->user(),
            'team.accept_invite',
            'Accepted workspace invite',
            null,
            [],
            $workspace
        );

        return response()->json([
            'message' => 'Joined workspace successfully.',
            'membership' => [
                'id' => $member->id,
                'workspace_id' => $member->workspace_id,
                'role' => $member->role,
            ],
        ]);
    }

    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $memberId)
            ->with('user:id,name,email')
            ->first();

        try {
            $this->workspaceAccess->removeMember($user, $workspace, $memberId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->activityLogger->log(
            $user,
            'team.remove_member',
            'Removed member '.($member?->user?->email ?? "#{$memberId}"),
            null,
            ['removed_user_id' => $member?->user_id, 'email' => $member?->user?->email],
            $workspace
        );

        return response()->json(['message' => 'Member removed.']);
    }
}
