<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PickListComment;
use App\Models\PickListCommentRead;
use App\Models\PickListCompletion;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use App\Services\WorkspaceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PickListCommentController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceActivityLogger $activityLogger,
        private readonly WorkspaceNotificationService $workspaceNotifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(PickListCompletion::KINDS)],
            'item_key' => ['required', 'string', 'max:191'],
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $pickDate = Carbon::today($tz)->toDateString();

        $comments = PickListComment::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', $validated['kind'])
            ->whereDate('pick_date', $pickDate)
            ->where('item_key', $validated['item_key'])
            ->with('user:id,name')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (PickListComment $c) => $c->toApiPayload())
            ->values()
            ->all();

        return response()->json([
            'data' => $comments,
            'count' => count($comments),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(PickListCompletion::KINDS)],
            'item_key' => ['required', 'string', 'max:191'],
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $pickDate = Carbon::today($tz)->toDateString();
        $now = now();

        PickListCommentRead::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'kind' => $validated['kind'],
                'pick_date' => $pickDate,
                'item_key' => $validated['item_key'],
            ],
            ['last_read_at' => $now],
        );

        return response()->json([
            'message' => 'Comments marked as read.',
            'last_read_at' => $now->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(PickListCompletion::KINDS)],
            'item_key' => ['required', 'string', 'max:191'],
            'item_id' => ['nullable', 'string', 'max:255'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $pickDate = Carbon::today($tz)->toDateString();
        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'Comment cannot be empty.', 'errors' => [
                'body' => ['Comment cannot be empty.'],
            ]], 422);
        }

        $comment = PickListComment::create([
            'workspace_id' => $workspace->id,
            'kind' => $validated['kind'],
            'pick_date' => $pickDate,
            'item_key' => $validated['item_key'],
            'item_id' => $validated['item_id'] ?? null,
            'model_id' => $validated['model_id'] ?? null,
            'item_name' => $validated['item_name'],
            'user_id' => $user->id,
            'body' => $body,
        ]);
        $comment->load('user:id,name');

        $this->activityLogger->log(
            $user,
            'picklist.commented',
            'Commented on '.$validated['item_name'],
            $comment,
            ['kind' => $validated['kind'], 'item_key' => $validated['item_key']],
            $workspace,
        );

        $this->workspaceNotifications->notifyPickListComment($workspace, $comment, $user);

        return response()->json($comment->toApiPayload(), 201);
    }

    public function destroy(Request $request, PickListComment $comment): JsonResponse
    {
        $user = $request->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if (! in_array((int) $comment->workspace_id, $workspaceIds, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if ((int) $comment->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Only the author can delete this comment.'], 403);
        }

        $workspace = $comment->workspace;
        $itemName = $comment->item_name ?: $comment->item_key;
        $itemKey = $comment->item_key;
        $kind = $comment->kind;
        $comment->delete();

        $this->activityLogger->log(
            $user,
            'picklist.comment_deleted',
            'Deleted comment on '.$itemName,
            null,
            ['kind' => $kind, 'item_key' => $itemKey],
            $workspace,
        );

        return response()->json(['message' => 'Comment deleted.']);
    }
}
