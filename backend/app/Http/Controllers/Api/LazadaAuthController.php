<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopeeOAuthState;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Services\LazadaService;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LazadaAuthController extends Controller
{
    public function __construct(
        private readonly LazadaService $lazada,
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    /**
     * Generate Lazada authorization URL for linking a new store.
     * Mirrors ShopeeAuthController::authUrl() pattern exactly:
     *  - Only the workspace owner may link stores.
     *  - A 64-char random state is stored (hashed) in shopee_oauth_states for CSRF protection.
     *  - State is passed through the redirect_uri so Lazada echoes it back in the callback.
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
            'user_id'    => $user->id,
            'platform'   => 'lazada',
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json(['url' => $this->lazada->getAuthUrl($state)]);
    }

    /**
     * OAuth callback — Lazada redirects here after the seller authorizes.
     *
     * Lazada returns: code, state (via redirect_uri), and optionally seller_id.
     * We use the same shopee_oauth_states table (consumed_at pattern) to match the state
     * back to the user who initiated the flow.
     */
    public function callback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        try {
            $code  = $request->string('code')->toString();
            $state = $request->string('state')->toString();

            if (empty($code) || strlen($state) !== 64) {
                Log::warning('Invalid Lazada OAuth callback parameters', [
                    'has_code'     => !empty($code),
                    'state_length' => strlen($state),
                ]);
                return redirect()->away($frontendUrl . '/dashboard?error=oauth_failed');
            }

            // Consume the OAuth state inside a transaction (same as Shopee)
            $oauthState = DB::transaction(function () use ($state) {
                $record = ShopeeOAuthState::query()
                    ->where('state_hash', hash('sha256', $state))
                    ->where('platform', 'lazada')
                    ->lockForUpdate()
                    ->first();

                if (!$record || $record->consumed_at || $record->expires_at->isPast()) {
                    return null;
                }

                $record->update(['consumed_at' => now()]);
                return $record;
            });

            if (!$oauthState) {
                Log::warning('Lazada OAuth state invalid, expired, or already used');
                return redirect()->away($frontendUrl . '/dashboard?error=oauth_failed');
            }

            $user = $oauthState->user()->firstOrFail();

            // Exchange code for tokens
            $tokenData = $this->lazada->createToken($code);

            // Parse response — Lazada token response fields
            $accessToken      = $tokenData['access_token'];
            $refreshToken     = $tokenData['refresh_token'];
            $expiresIn        = (int) ($tokenData['expires_in'] ?? 2592000);        // default 30 days
            $refreshExpiresIn = (int) ($tokenData['refresh_expires_in'] ?? 15552000); // default 180 days
            $country          = strtolower($tokenData['country'] ?? 'sg');
            $account          = $tokenData['account'] ?? null;

            // seller_id may be present as a numeric string
            $sellerId = isset($tokenData['seller_id'])
                ? (int) $tokenData['seller_id']
                : (int) ($tokenData['account_id'] ?? 0);

            // Fallback: derive a stable ID from the account email
            if ($sellerId === 0 && $account) {
                $sellerId = abs(crc32($account));
            }

            // Prefer /seller/get shop name — token "account" is often an email, not the store name.
            $shopName = $this->lazada->resolveShopName($accessToken, $country)
                ?? "Lazada Seller #{$sellerId}";
            $accessExpiresAt   = now()->addSeconds($expiresIn);
            $refreshExpiresAt  = now()->addSeconds($refreshExpiresIn);

            // Reauthorization: state carries store_id from handleReauthorize()
            $isReauthorization = !empty($oauthState->store_id);

            // If this is a reauthorization, verify seller_id matches the intended store
            if ($isReauthorization) {
                $intendedStore = ShopeeStore::findOrFail($oauthState->store_id);
                if ((int) $intendedStore->shop_id !== (int) $sellerId) {
                    Log::warning('Lazada reauthorization seller_id mismatch', [
                        'intended_shop_id' => $intendedStore->shop_id,
                        'returned_seller_id' => $sellerId,
                    ]);
                    return redirect()->away(
                        $frontendUrl . '/dashboard?error=reauthorize_shop_mismatch'
                    );
                }
            }

            DB::transaction(function () use (
                $user, $oauthState, $sellerId, $shopName, $country,
                $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt,
                $isReauthorization
            ) {
                if ($isReauthorization) {
                    $store = ShopeeStore::findOrFail($oauthState->store_id);
                    $store->update([
                        'shop_name' => $shopName ?? $store->shop_name,
                        'region'    => $country ?? $store->region,
                        'is_active' => true,
                    ]);
                } else {
                    $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
                    $store = ShopeeStore::updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'platform'     => 'lazada',
                            'shop_id'      => $sellerId,
                        ],
                        [
                            'user_id'   => $user->id,
                            'shop_name' => $shopName,
                            'region'    => $country,
                            'is_active' => true,
                        ]
                    );
                }

                // Tokens are encrypted at rest — same table, same pattern as Shopee
                ShopeeToken::updateOrCreate(
                    ['store_id' => $store->id],
                    [
                        'access_token'              => encrypt($accessToken),
                        'refresh_token'             => encrypt($refreshToken),
                        'access_token_expires_at'   => $accessExpiresAt,
                        'refresh_token_expires_at'  => $refreshExpiresAt,
                        // auth_expires_at = when the seller must re-authorize (same as refresh expiry for Lazada)
                        'auth_expires_at'           => $refreshExpiresAt,
                        'last_refreshed_at'         => now(),
                        'last_refresh_attempt_at'   => null,
                        'last_refresh_failed_at'    => null,
                        'last_refresh_error'        => null,
                        'refresh_failure_count'     => 0,
                    ]
                );

                $this->activityLogger->log(
                    $user,
                    $isReauthorization ? 'store.reauthorize' : 'store.connect',
                    ($isReauthorization ? 'Reauthorized' : 'Linked') . " Lazada store {$shopName}",
                    $store,
                    ['shop_id' => $store->shop_id, 'platform' => 'lazada']
                );
            });

            return redirect()->away($frontendUrl . '/dashboard?linked=1');

        } catch (\Throwable $e) {
            Log::error('Lazada OAuth callback failed', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);
            return redirect()->away($frontendUrl . '/dashboard?error=oauth_failed');
        }
    }

    /**
     * Generate a reauthorization URL for an existing Lazada store.
     * Stores the store_id in the OAuth state so the callback updates
     * the existing record instead of creating a new one.
     */
    public function reauthorize(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can reauthorize stores.'], 403);
        }

        if ($store->platform !== 'lazada') {
            return response()->json(['message' => 'This store is not a Lazada store.'], 400);
        }

        $state = Str::random(64);
        ShopeeOAuthState::create([
            'user_id'    => $request->user()->id,
            'store_id'   => $store->id,
            'platform'   => 'lazada',
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json(['url' => $this->lazada->getAuthUrl($state)]);
    }

    /**
     * Soft-disconnect a Lazada store.
     * Stops syncing; removes token; marks store inactive.
     * Preserves store record, products, orders.
     */
    public function disconnect(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can disconnect stores.'], 403);
        }

        if ($store->platform !== 'lazada') {
            return response()->json(['message' => 'This store is not a Lazada store.'], 400);
        }

        DB::transaction(function () use ($store) {
            $store->update(['is_active' => false]);
            $store->token()?->delete();
        });

        $this->activityLogger->log(
            $request->user(),
            'store.disconnect',
            'Disconnected Lazada store ' . ($store->shop_name ?: "seller #{$store->shop_id}"),
            $store,
            ['shop_id' => $store->shop_id, 'platform' => 'lazada']
        );

        return response()->json([
            'message' => 'Lazada store disconnected. Syncing stopped. Products, orders, and history are preserved.',
        ]);
    }

    /**
     * Permanently delete a Lazada store and all its data.
     * Requires explicit confirmation phrase 'DELETE STORE DATA'.
     */
    public function unlink(Request $request, ShopeeStore $store): JsonResponse
    {
        if (!$this->workspaceAccess->isOwnerOfStore($request->user(), $store)) {
            return response()->json(['message' => 'Only the workspace owner can unlink stores.'], 403);
        }

        if ($store->platform !== 'lazada') {
            return response()->json(['message' => 'This store is not a Lazada store.'], 400);
        }

        $request->validate([
            'confirmation' => 'required|string|in:DELETE STORE DATA',
        ]);

        $shopLabel = $store->shop_name ?: "seller #{$store->shop_id}";
        $workspace = $store->workspace;

        $this->activityLogger->log(
            $request->user(),
            'store.unlink',
            "Unlinked Lazada store {$shopLabel}",
            null,
            ['shop_id' => $store->shop_id, 'platform' => 'lazada'],
            $workspace
        );

        DB::transaction(function () use ($store) {
            $store->token()?->delete();
            $store->delete();
        });

        return response()->json(['message' => 'Lazada store and all related data deleted permanently.']);
    }
}
