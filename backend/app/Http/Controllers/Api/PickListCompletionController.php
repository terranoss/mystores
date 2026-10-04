<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PickListCompletion;
use App\Services\PickListPhotoCompressor;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PickListCompletionController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceActivityLogger $activityLogger,
        private readonly PickListPhotoCompressor $photoCompressor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(PickListCompletion::KINDS)],
            'item_key' => ['required', 'string', 'max:191'],
            'item_id' => ['nullable', 'string', 'max:255'],
            'model_id' => ['nullable', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:500'],
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $pickDate = Carbon::today($tz)->toDateString();

        $disk = Storage::disk('local');
        $path = $this->photoCompressor->store(
            $request->file('photo'),
            'picklist-photos/'.$workspace->id.'/'.$pickDate,
            'local'
        );

        $existing = PickListCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', $validated['kind'])
            ->whereDate('pick_date', $pickDate)
            ->where('item_key', $validated['item_key'])
            ->first();

        if ($existing) {
            if ($existing->photo_path && $disk->exists($existing->photo_path)) {
                $disk->delete($existing->photo_path);
            }
            $existing->update([
                'item_id' => $validated['item_id'] ?? null,
                'model_id' => $validated['model_id'] ?? null,
                'item_name' => $validated['item_name'],
                'photo_path' => $path,
                'picked_by' => $user->id,
                'picked_at' => now(),
            ]);
            $completion = $existing->fresh(['picker:id,name']);
            $this->activityLogger->log(
                $user,
                'picklist.picked',
                'Replaced pick photo for '.$validated['item_name'],
                $completion,
                ['kind' => $validated['kind'], 'item_key' => $validated['item_key']],
                $workspace,
            );
        } else {
            $completion = PickListCompletion::create([
                'workspace_id' => $workspace->id,
                'kind' => $validated['kind'],
                'pick_date' => $pickDate,
                'item_key' => $validated['item_key'],
                'item_id' => $validated['item_id'] ?? null,
                'model_id' => $validated['model_id'] ?? null,
                'item_name' => $validated['item_name'],
                'photo_path' => $path,
                'picked_by' => $user->id,
                'picked_at' => now(),
            ]);
            $completion->load('picker:id,name');
            $this->activityLogger->log(
                $user,
                'picklist.picked',
                'Picked '.$validated['item_name'],
                $completion,
                ['kind' => $validated['kind'], 'item_key' => $validated['item_key']],
                $workspace,
            );
        }

        return response()->json($this->completionPayload($completion), $existing ? 200 : 201);
    }

    public function destroy(Request $request): JsonResponse
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

        $completion = PickListCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', $validated['kind'])
            ->whereDate('pick_date', $pickDate)
            ->where('item_key', $validated['item_key'])
            ->first();

        if (! $completion) {
            return response()->json(['message' => 'Pick completion not found.'], 404);
        }

        $disk = Storage::disk('local');
        if ($completion->photo_path && $disk->exists($completion->photo_path)) {
            $disk->delete($completion->photo_path);
        }

        $itemName = $completion->item_name;
        $itemKey = $completion->item_key;
        $kind = $completion->kind;
        $completion->delete();

        $this->activityLogger->log(
            $user,
            'picklist.unpicked',
            'Unpicked '.$itemName,
            null,
            ['kind' => $kind, 'item_key' => $itemKey],
            $workspace,
        );

        return response()->json(['message' => 'Pick completion removed.']);
    }

    public function photo(PickListCompletion $completion): StreamedResponse|JsonResponse
    {
        $user = request()->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if (! in_array((int) $completion->workspace_id, $workspaceIds, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $disk = Storage::disk('local');
        if (! $completion->photo_path || ! $disk->exists($completion->photo_path)) {
            return response()->json(['message' => 'Photo not found.'], 404);
        }

        $mime = $disk->mimeType($completion->photo_path) ?: 'image/jpeg';

        return $disk->response($completion->photo_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * @return array{id: int, picked_by: array{id: int, name: string}, picked_at: string, photo_url: string}
     */
    public static function completionPayload(PickListCompletion $completion): array
    {
        $completion->loadMissing('picker:id,name');

        return [
            'id' => $completion->id,
            'picked_by' => [
                'id' => (int) $completion->picked_by,
                'name' => $completion->picker?->name ?? 'Unknown',
            ],
            'picked_at' => $completion->picked_at?->toIso8601String(),
            'photo_url' => '/dashboard/picklist-photos/'.$completion->id,
        ];
    }
}
