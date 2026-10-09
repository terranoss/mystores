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
    /** Placeholder returned instead of a stored secret value. */
    public const MASKED_SECRET = '[SET]';

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
        $workspace = $this->getUserWorkspace($user, $request);

        if (!$workspace) {
            return response()->json(['message' => 'Workspace not found'], Response::HTTP_NOT_FOUND);
        }

        // Credentials reveal tenant configuration — owner-only, like update()/destroy().
        if ($this->workspaceAccess->roleInWorkspace($user, $workspace) !== 'owner') {
            return response()->json(['message' => 'Only the workspace owner can view credentials.'], Response::HTTP_FORBIDDEN);
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
            'partner_key' => $shopeeCred && !empty($shopeeCred->partner_key) ? self::MASKED_SECRET : '',
            'push_partner_key' => $shopeeCred && !empty($shopeeCred->push_partner_key) ? self::MASKED_SECRET : '',
            'shopee_redirect_url' => $shopeeCred?->redirect_url ?? (string) config('shopee.redirect_url', ''),
            'app_key' => $lazadaCred?->app_key ?? '',
            'app_secret' => $lazadaCred && !empty($lazadaCred->app_secret) ? self::MASKED_SECRET : '',
            'lazada_redirect_url' => $lazadaCred?->redirect_url ?? (string) config('lazada.redirect_url', ''),
        ]);
    }

    /**
     * Update or create credentials for the current workspace
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $workspace = $this->getUserWorkspace($user, $request);

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
            'shopee_redirect_url' => 'sometimes|nullable|string|max:500',
            'app_key' => 'sometimes|nullable|string|max:255',
            'app_secret' => 'sometimes|nullable|string',
            'lazada_redirect_url' => 'sometimes|nullable|string|max:500',
        ]);

        // Update or create Shopee credentials
        if ($request->hasAny(['partner_id', 'partner_key', 'push_partner_key', 'shopee_redirect_url'])) {
            $shopeeCred = $workspace->credentials()
                ->where('platform', 'shopee')
                ->first() ?? new WorkspaceCredential([
                    'workspace_id' => $workspace->id,
                    'platform' => 'shopee',
                ]);

            if ($request->has('partner_id')) {
                $shopeeCred->partner_id = $this->normalizeValue($request->partner_id);
            }
            if ($this->isSecretUpdate($request, 'partner_key')) {
                $shopeeCred->partner_key = $this->normalizeValue($request->partner_key);
            }
            if ($this->isSecretUpdate($request, 'push_partner_key')) {
                $shopeeCred->push_partner_key = $this->normalizeValue($request->push_partner_key);
            }
            if ($request->has('shopee_redirect_url')) {
                $shopeeCred->redirect_url = $this->normalizeValue($request->shopee_redirect_url);
            }

            $shopeeCred->save();
        }

        // Update or create Lazada credentials
        if ($request->hasAny(['app_key', 'app_secret', 'lazada_redirect_url'])) {
            $lazadaCred = $workspace->credentials()
                ->where('platform', 'lazada')
                ->first() ?? new WorkspaceCredential([
                    'workspace_id' => $workspace->id,
                    'platform' => 'lazada',
                ]);

            if ($request->has('app_key')) {
                $lazadaCred->app_key = $this->normalizeValue($request->app_key);
            }
            if ($this->isSecretUpdate($request, 'app_secret')) {
                $lazadaCred->app_secret = $this->normalizeValue($request->app_secret);
            }
            if ($request->has('lazada_redirect_url')) {
                $lazadaCred->redirect_url = $this->normalizeValue($request->lazada_redirect_url);
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
        $workspace = $this->getUserWorkspace($user, $request);

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
     * Resolve the workspace these credential calls apply to.
     *
     * Prefers the explicitly requested workspace (validated for membership) and
     * otherwise falls back to the caller's primary/personal workspace.
     */
    private function getUserWorkspace($user, ?Request $request = null)
    {
        $requestedId = $request?->header('X-Workspace-Id') ?? $request?->input('workspace_id');

        if ($requestedId !== null && $requestedId !== '') {
            $workspace = \App\Models\Workspace::find((int) $requestedId);

            if (!$workspace || $this->workspaceAccess->roleInWorkspace($user, $workspace) === null) {
                return null;
            }

            return $workspace;
        }

        return $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);
    }

    /**
     * Whether a secret field was submitted with a real value.
     *
     * The masked "[SET]" placeholder returned by show() is ignored so the UI can
     * round-trip the form without overwriting the stored secret.
     */
    private function isSecretUpdate(Request $request, string $field): bool
    {
        if (! $request->has($field)) {
            return false;
        }

        $value = $request->input($field);

        return ! is_string($value) || $value !== self::MASKED_SECRET;
    }

    /**
     * Normalize a submitted credential value. Empty strings clear the value.
     */
    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}