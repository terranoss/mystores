<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceCredential;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceCredentialController extends Controller
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceConfig $workspaceConfig
    ) {}

    /**
     * Get credentials for the current workspace
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $workspace = $this->getUserWorkspace($user);

        if (!$workspace) {
            return response()->json(['message' => 'Workspace not found'], Response::HTTP_NOT_FOUND);
        }

        // Get credentials for both platforms
        $shopeeCred = $workspace->credentials()
            ->where('platform', 'shopee')
            ->where('is_active', true)
            ->first();
            
        $lazadaCred = $workspace->credentials()
            ->where('platform', 'lazada')
            ->where('is_active', true)
            ->first();

        // Return format expected by frontend
        // For security, we don't return actual secret values, just whether they're set
        return response()->json([
            'id' => ($shopeeCred ? $shopeeCred->id : ($lazadaCred ? $lazadaCred->id : null)),
            'partner_id' => $shopeeCred?->partner_id ?? '',
            'partner_key' => $shopeeCred && !empty($shopeeCred->partner_key) ? '[SET]' : '',
            'push_partner_key' => $shopeeCred && !empty($shopeeCred->push_partner_key) ? '[SET]' : '',
            'app_key' => $lazadaCred?->app_key ?? '',
            'app_secret' => $lazadaCred && !empty($lazadaCred->app_secret) ? '[SET]' : '',
        ]);
    }

    /**
     * Update or create credentials for the current workspace
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $workspace = $this->getUserWorkspace($user);

        if (!$workspace) {
            return response()->json(['message' => 'Workspace not found'], Response::HTTP_NOT_FOUND);
        }

        // Only allow owner to update credentials
        $role = $this->workspaceAccess->roleInWorkspace($user, $workspace);
        if ($role !== 'owner') {
            return response()->json(['message' => 'Only the workspace owner can manage credentials.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'partner_id' => 'sometimes|nullable|string|max:255',
            'partner_key' => 'sometimes|nullable|string',
            'push_partner_key' => 'sometimes|nullable|string',
            'app_key' => 'sometimes|nullable|string|max:255',
            'app_secret' => 'sometimes|nullable|string',
        ]);

        // Update or create Shopee credentials
        if ($request->hasAny(['partner_id', 'partner_key', 'push_partner_key'])) {
            $shopeeCred = $workspace->credentials()
                ->where('platform', 'shopee')
                ->first() ?? new WorkspaceCredential([
                    'workspace_id' => $workspace->id,
                    'platform' => 'shopee',
                ]);

            if ($request->has('partner_id')) {
                $shopeeCred->partner_id = $request->partner_id ?: null;
            }
            if ($request->has('partner_key') && !empty($request->partner_key)) {
                $shopeeCred->partner_key = $request->partner_key;
            }
            if ($request->has('push_partner_key') && !empty($request->push_partner_key)) {
                $shopeeCred->push_partner_key = $request->push_partner_key;
            }

            $shopeeCred->save();
        }

        // Update or create Lazada credentials
        if ($request->hasAny(['app_key', 'app_secret'])) {
            $lazadaCred = $workspace->credentials()
                ->where('platform', 'lazada')
                ->first() ?? new WorkspaceCredential([
                    'workspace_id' => $workspace->id,
                    'platform' => 'lazada',
                ]);

            if ($request->has('app_key')) {
                $lazadaCred->app_key = $request->app_key ?: null;
            }
            if ($request->has('app_secret') && !empty($request->app_secret)) {
                $lazadaCred->app_secret = $request->app_secret;
            }

            $lazadaCred->save();
        }

        // Clear cached config so changes take effect immediately
        $this->workspaceConfig->clearCache($workspace->id);

        return response()->json([
            'message' => 'Credentials updated successfully',
            'workspace_id' => $workspace->id,
            'has_shopee' => !empty($workspace->credentials()
                ->where('platform', 'shopee')
                ->where('is_active', true)
                ->first()?->partner_key),
            'has_lazada' => !empty($workspace->credentials()
                ->where('platform', 'lazada')
                ->where('is_active', true)
                ->first()?->app_secret),
        ]);
    }

    /**
     * Delete credentials for the current workspace
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $workspace = $this->getUserWorkspace($user);

        if (!$workspace) {
            return response()->json(['message' => 'Workspace not found'], Response::HTTP_NOT_FOUND);
        }

        // Only allow owner to delete credentials
        $role = $this->workspaceAccess->roleInWorkspace($user, $workspace);
        if ($role !== 'owner') {
            return response()->json(['message' => 'Only the workspace owner can manage credentials.'], Response::HTTP_FORBIDDEN);
        }

        // Delete both platform credentials
        $workspace->credentials()->delete();

        // Clear cached config
        $this->workspaceConfig->clearCache($workspace->id);

        return response()->json([
            'message' => 'Credentials deleted successfully',
        ]);
    }

    /**
     * Get the current user's workspace
     */
    private function getUserWorkspace($user)
    {
        return $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);
    }
}