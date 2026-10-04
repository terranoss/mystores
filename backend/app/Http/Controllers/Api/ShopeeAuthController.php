<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopeeOAuthState;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Services\ShopeeService;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopeeAuthController extends Controller
{
    public function __construct(
        private readonly ShopeeService $shopee,
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    /**
     * Generate the Shopee authorization URL for linking a store.
     * Uses the OAuth 'state' parameter to track which user is linking.
     */
    public function authUrl(Request $request): JsonResponse
    {
        $user = $request->user();
        $owned = \App\Models\Workspace::query()
            ->where('owner_user_id', $user->id)
            ->orderBy('id')
            ->first();
        if (!$owned) {
            if ($this->workspaceAccess->workspaceIdsFor($user) !== []) {
                return response()->json(['message' => 'Only the workspace owner can link stores.'], 403);
            }
            $owned = $this->workspaceAccess->ensurePersonalWorkspace($user);
        }

        $state = Str::random(64);
        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        $partnerId = (int) config('shopee.partner_id');
        $partnerKey = config('shopee.partner_key');
        $timestamp = time();
        $path = '/api/v2/shop/auth_partner';

        // Append state directly as a query parameter in the redirect URL
        // Shopee strips custom top-level query parameters but preserves query parameters inside the redirect URL
        $redirectUrl = $this->shopee->getRedirectUrl().'?state='.urlencode($state);

        $baseString = $partnerId.$path.$timestamp;
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $host = config('shopee.sandbox')
            ? 'https://openplatform.sandbox.test-stable.shopee.sg'
            : 'https://partner.shopeemobile.com';

        $url = "{$host}{$path}?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}&redirect=".urlencode($redirectUrl);

        return response()->json(['url' => $url]);
    }

    /**
     * OAuth callback — Shopee redirects here after seller authorizes.
     * Reads 'state' to identify which user initiated the link.
     * Handles both initial linking and reauthorization of existing stores.
     */
    public function callback(Request $request)
    {
        try {
            // Manually validate OAuth callback parameters instead of using $request->validate()
            // to avoid raw JSON 422 responses in this browser redirect flow.
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            if ($request->missing('code') || $request->missing('shop_id') || $request->missing('state') || strlen($request->string('state')) !== 64) {
                Log::warning('Invalid OAuth callback parameters', [
                    'has_code' => $request->has('code'),
                    'has_shop_id' => $request->has('shop_id'),
                    'state_length' => strlen($request->string('state', '')),
                ]);
                return redirect()->away($frontendUrl.'/dashboard?error=oauth_failed');
            }

            $validated = [
                'code' => $request->string('code'),
                'shop_id' => $request->integer('shop_id'),
                'state' => $request->string('state'),
            ];

            $oauthState = DB::transaction(function () use ($validated) {
                $oauthState = ShopeeOAuthState::query()
                    ->where('state_hash', hash('sha256', $validated['state']))
                    ->lockForUpdate()
                    ->first();

                if (! $oauthState || $oauthState->consumed_at || $oauthState->expires_at->isPast()) {
                    throw new \RuntimeException('OAuth state is invalid, expired, or already used.');
                }

                $oauthState->update(['consumed_at' => now()]);

                return $oauthState;
            });

            // If this is a reauthorization, verify shop_id matches the intended store
            if ($oauthState->store_id) {
                $intendedStore = ShopeeStore::findOrFail($oauthState->store_id);
                if ((int) $intendedStore->shop_id !== (int) $validated['shop_id']) {
                    Log::warning('Shopee reauthorization shop_id mismatch', [
                        'intended_shop_id' => $intendedStore->shop_id,
                        'returned_shop_id' => $validated['shop_id'],
                    ]);
                    return redirect()->away(
                        config('app.frontend_url', 'http://localhost:5173').'/dashboard?error=reauthorize_shop_mismatch'
                    );
                }
            }

            $user = $oauthState->user()->firstOrFail();

            // Exchange code for tokens
            $tokens = $this->shopee->auth()->getToken(
                $validated['code'],
                $validated['shop_id']
            );

            if (! empty($tokens['error'])) {
                throw new \Exception('Shopee error: '.($tokens['message'] ?? $tokens['error']));
            }

            $accessTokenExpiresAt = now()->addSeconds($tokens['expire_in']);
            // refresh_token_expire_in may be returned by some Shopee sandbox responses
            $refreshExpireSeconds = $tokens['refresh_token_expire_in'] ?? null;
            $refreshTokenExpiresAt = $refreshExpireSeconds
                ? now()->addSeconds($refreshExpireSeconds)
                : now()->addDays(30);
            // auth_expires_at = the seller's authorization validity (365 days per Shopee docs)
            $authExpiresAt = now()->addDays(365);

            // Fetch shop info for name/region
            $shopName = null;
            $region = null;
            try {
                $this->shopee->client()->setAccessToken(
                    $validated['shop_id'],
                    $tokens['access_token']
                );
                $shopInfo = $this->shopee->client()->Shop->getShopInfo();
                $shopName = $shopInfo['shop_name'] ?? null;
                $region = $shopInfo['region'] ?? null;
            } catch (\Exception) {
                // Best-effort
            }

            // Create or update store — preserve existing data during reauthorization
            if ($oauthState->store_id) {
                $store = ShopeeStore::findOrFail($oauthState->store_id);
                $store->update([
                    'shop_name' => $shopName ?? $store->shop_name,
                    'region' => $region ?? $store->region,
                    'is_active' => true,
                ]);
            } else {
                $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
                $store = ShopeeStore::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'shop_id' => $validated['shop_id'],
                    ],
                    [
                        'user_id' => $user->id,
                        'shop_name' => $shopName,
                        'region' => $region,
                        'is_active' => true,
                    ]
                );
            }

            // Store encrypted tokens
            ShopeeToken::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'access_token' => encrypt($tokens['access_token']),
                    'refresh_token' => encrypt($tokens['refresh_token']),
                    'access_token_expires_at' => $accessTokenExpiresAt,
                    'refresh_token_expires_at' => $refreshTokenExpiresAt,
                    'auth_expires_at' => $authExpiresAt,
                    'last_refreshed_at' => now(),
                    'last_refresh_failed_at' => null,
                    'last_refresh_error' => null,
                    'refresh_failure_count' => 0,
                ]
            );

            $this->activityLogger->log(
                $user,
                $oauthState->store_id ? 'store.reauthorize' : 'store.connect',
                ($oauthState->store_id ? 'Reauthorized' : 'Linked').' store '.($shopName ?: "shop #{$validated['shop_id']}"),
                $store,
                ['shop_id' => $validated['shop_id'], 'shop_name' => $shopName]
            );

            return redirect()->away(
                config('app.frontend_url', 'http://localhost:5173').'/dashboard?linked=1'
            );

        } catch (\Throwable $e) {
            Log::warning('Shopee OAuth callback failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'shop_id' => $request->integer('shop_id') ?: null,
            ]);

            return redirect()->away(
                config('app.frontend_url', 'http://localhost:5173').'/dashboard?error=oauth_failed'
            );
        }
    }

    /**
     * List all linked stores for the authenticated user.
     * Includes operational health status for each store.
     * Never exposes access_token, refresh_token, or partner credentials.
     */
    public function stores(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if ($workspaceIds === []) {
            $workspaceIds = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }
        $stores = ShopeeStore::with('token')
            ->withCount('products')
            ->whereIn('workspace_id', $workspaceIds)
            ->orderBy('id')
            ->get();

        return response()->json([
            'stores' => $stores->map(function (ShopeeStore $store) {
                $health = $this->computeStoreHealth($store);

                if (($store->platform ?? 'shopee') === 'shopee' && $store->is_active && $store->token && $health['connection_status'] === 'active') {
                    if ($store->vacation_mode_synced_at === null || $store->vacation_mode_synced_at->diffInHours(now()) >= 6) {
                        try {
                            $holidayRes = $this->shopee->getHolidayMode($store);
                            $store->vacation_mode = (bool) ($holidayRes['holiday_mode_on'] ?? false);
                            $store->vacation_mode_synced_at = now();
                            $store->save();
                        } catch (\Throwable) {
                            // Non-fatal, do not break store list
                        }
                    }
                }

                return [
                    'id' => $store->id,
                    'shop_id' => $store->shop_id,
                    'platform' => $store->platform ?? 'shopee',
                    'shop_name' => $store->shop_name,
                    'region' => $store->region,
                    'is_active' => $store->is_active,
                    'products_count' => (int) $store->products_count,
                    'connection_status' => $health['connection_status'],
                    'token_health' => $health['token_health'],
                    'requires_reauthorization' => $health['requires_reauthorization'],
                    'linked_at' => $store->created_at,
                    'vacation_mode' => (bool) $store->vacation_mode,
                    'vacation_mode_synced_at' => $store->vacation_mode_synced_at,
                    // Refresh tracking metadata
                    'last_refreshed_at' => $store->token?->last_refreshed_at,
                    'last_refresh_attempt_at' => $store->token?->last_refresh_attempt_at,
                    'last_refresh_failed_at' => $store->token?->last_refresh_failed_at,
                    'last_refresh_error' => $store->token?->last_refresh_error,
                    'refresh_failure_count' => $store->token?->refresh_failure_count ?? 0,
                    'access_token_expires_at' => $store->token?->access_token_expires_at,
                    'refresh_token_expires_at' => $store->token?->refresh_token_expires_at,
                ];
            }),
        ]);
    }

    /**
     * Start reauthorization for an existing store.
     * Creates an OAuth state tied to the intended store so the callback
     * updates the existing record rather than creating a new one.
     */
    public function reauthorize(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can reauthorize stores.'], 403);
        }

        $state = Str::random(64);
        ShopeeOAuthState::create([
            'user_id' => $request->user()->id,
            'store_id' => $store->id,
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        $partnerId = (int) config('shopee.partner_id');
        $partnerKey = config('shopee.partner_key');
        $timestamp = time();
        $path = '/api/v2/shop/auth_partner';

        // Pass intended shop_id so callback can verify match
        $redirectUrl = $this->shopee->getRedirectUrl()
            .'?state='.urlencode($state)
            .'&intended_shop_id='.$store->shop_id;

        $baseString = $partnerId.$path.$timestamp;
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $host = config('shopee.sandbox')
            ? 'https://openplatform.sandbox.test-stable.shopee.sg'
            : 'https://partner.shopeemobile.com';

        $url = "{$host}{$path}?partner_id={$partnerId}&timestamp={$timestamp}&sign={$sign}&redirect=".urlencode($redirectUrl);

        return response()->json(['url' => $url]);
    }

    /**
     * Safely disconnect a store.
     * Stops syncing, removes token, marks store as inactive.
     * Preserves store record, products, orders, and inventory logs.
     */
    public function disconnect(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can disconnect stores.'], 403);
        }

        DB::transaction(function () use ($store) {
            $store->update(['is_active' => false]);
            $store->token()?->delete();
        });

        $this->activityLogger->log(
            $request->user(),
            'store.disconnect',
            'Disconnected store '.($store->shop_name ?: "shop #{$store->shop_id}"),
            $store,
            ['shop_id' => $store->shop_id]
        );

        return response()->json([
            'message' => 'Store disconnected successfully. Syncing stopped. Products, orders, and history are preserved.',
        ]);
    }

    /**
     * Permanently delete a store and all associated data.
     * Requires explicit confirmation phrase 'DELETE STORE DATA'.
     */
    public function unlink(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can unlink stores.'], 403);
        }

        $validated = $request->validate([
            'confirmation' => 'required|string|in:DELETE STORE DATA',
        ]);

        $shopLabel = $store->shop_name ?: "shop #{$store->shop_id}";
        $shopId = $store->shop_id;
        $workspace = $store->workspace;

        $this->activityLogger->log(
            $request->user(),
            'store.unlink',
            "Unlinked store {$shopLabel}",
            null,
            ['shop_id' => $shopId],
            $workspace
        );

        DB::transaction(function () use ($store) {
            $store->token()->delete();
            $store->delete();
        });

        return response()->json(['message' => 'Store and all related data deleted permanently.']);
    }

    /**
     * Enable or disable Vacation Mode for a Shopee store.
     */
    public function setVacationMode(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can manage store vacation mode.'], 403);
        }

        if (($store->platform ?? 'shopee') !== 'shopee') {
            return response()->json(['message' => 'Vacation mode is only supported for Shopee stores.'], 422);
        }

        $validated = $request->validate([
            'vacation_mode' => 'required|boolean',
        ]);

        $enabled = (bool) $validated['vacation_mode'];

        try {
            $this->shopee->setHolidayMode($store, $enabled);
            $store->update([
                'vacation_mode' => $enabled,
                'vacation_mode_synced_at' => now(),
            ]);

            $statusText = $enabled ? 'enabled' : 'disabled';
            $this->activityLogger->log(
                $request->user(),
                'store.vacation_mode',
                "Vacation mode {$statusText} for ".($store->shop_name ?: "shop #{$store->shop_id}"),
                $store,
                [
                    'shop_id' => $store->shop_id,
                    'vacation_mode' => $enabled,
                ]
            );

            return response()->json([
                'message' => "Vacation mode {$statusText} successfully.",
                'vacation_mode' => $enabled,
                'vacation_mode_synced_at' => $store->vacation_mode_synced_at,
            ]);
        } catch (ShopeeException $e) {
            try {
                $live = $this->shopee->getHolidayMode($store);
                $store->update([
                    'vacation_mode' => (bool) ($live['holiday_mode_on'] ?? false),
                    'vacation_mode_synced_at' => now(),
                ]);
            } catch (\Throwable) {}

            return response()->json([
                'message' => $e->getMessage() ?: 'Shopee rejected vacation mode update.',
                'vacation_mode' => (bool) $store->vacation_mode,
            ], 422);
        } catch (\Throwable $e) {
            try {
                $live = $this->shopee->getHolidayMode($store);
                $store->update([
                    'vacation_mode' => (bool) ($live['holiday_mode_on'] ?? false),
                    'vacation_mode_synced_at' => now(),
                ]);
            } catch (\Throwable) {}

            return response()->json([
                'message' => 'Failed to update vacation mode: '.$e->getMessage(),
                'vacation_mode' => (bool) $store->vacation_mode,
            ], 422);
        }
    }

    /**
     * Get the latest vacation mode status from Shopee API and sync locally.
     */
    public function getVacationMode(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->canAccessStore($request->user(), $store)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (($store->platform ?? 'shopee') !== 'shopee') {
            return response()->json(['message' => 'Vacation mode is only supported for Shopee stores.'], 422);
        }

        try {
            $res = $this->shopee->getHolidayMode($store);
            $enabled = (bool) ($res['holiday_mode_on'] ?? false);
            $store->update([
                'vacation_mode' => $enabled,
                'vacation_mode_synced_at' => now(),
            ]);

            return response()->json([
                'vacation_mode' => $enabled,
                'vacation_mode_synced_at' => $store->vacation_mode_synced_at,
                'raw' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'vacation_mode' => (bool) $store->vacation_mode,
                'vacation_mode_synced_at' => $store->vacation_mode_synced_at,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compute the operational health status for a store.
     */
    private function computeStoreHealth(ShopeeStore $store): array
    {
        $token = $store->token;

        if (! $token) {
            return [
                'connection_status' => 'missing_token',
                'token_health' => 'expired',
                'requires_reauthorization' => false,
            ];
        }

        if ($token->auth_expires_at->isPast()) {
            return [
                'connection_status' => 'reauthorize_required',
                'token_health' => 'expired',
                'requires_reauthorization' => true,
            ];
        }

        if (! $store->is_active) {
            return [
                'connection_status' => 'disconnected',
                'token_health' => 'expired',
                'requires_reauthorization' => false,
            ];
        }

        // Refresh is failing repeatedly
        if ($token->refresh_failure_count > 0 && $token->last_refresh_failed_at) {
            $reauthorizeRequired = $token->refresh_failure_count >= 3;
            return [
                'connection_status' => $reauthorizeRequired ? 'reauthorize_required' : 'refresh_failed',
                'token_health' => 'expired',
                'requires_reauthorization' => $reauthorizeRequired,
            ];
        }

        // Access token expired or expiring within the next hour
        if ($token->access_token_expires_at->isPast() || $token->access_token_expires_at->diffInSeconds(now()) < 3600) {
            return [
                'connection_status' => 'expiring_soon',
                'token_health' => 'expiring_soon',
                'requires_reauthorization' => false,
            ];
        }

        // Refresh token expiring within 7 days
        if ($token->refresh_token_expires_at && now()->diffInDays($token->refresh_token_expires_at) < 7) {
            return [
                'connection_status' => 'expiring_soon',
                'token_health' => 'expiring_soon',
                'requires_reauthorization' => true,
            ];
        }

        return [
            'connection_status' => 'active',
            'token_health' => 'healthy',
            'requires_reauthorization' => false,
        ];
    }
}
