<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Services\WorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceRegistrationController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);

        DeviceRegistration::updateOrCreate(
            ['player_id' => $validated['player_id']],
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ]
        );

        return response()->json([
            'workspace_public_id' => $workspace->public_id,
        ]);
    }

    public function unregister(Request $request, string $playerId): JsonResponse
    {
        $user = $request->user();

        // A user cannot unregister another user's device; the attempt succeeds without removing the other device.
        DeviceRegistration::query()
            ->where('player_id', $playerId)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Device unregistered']);
    }
}
