<?php

namespace App\Services;

use App\Models\ShopeeStore;
use App\Models\Workspace;
use EcomPHP\Shopee\Client;
use EcomPHP\Shopee\Errors\AuthorizationException;
use EcomPHP\Shopee\Errors\ShopeeException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\WorkspaceAccess;

class ShopeeService
{
    /** Refresh access tokens this many seconds before expiry (Shopee: refresh before 4h expiry). */
    public const ACCESS_TOKEN_REFRESH_BUFFER_SECONDS = 1800;

    private Client $client;
    private WorkspaceAccess $workspaceAccess;
    private WorkspaceConfig $workspaceConfig;

    /**
     * @param WorkspaceAccess $workspaceAccess
     * @param WorkspaceConfig $workspaceConfig
     */
    public function __construct(
        ?WorkspaceAccess $workspaceAccess = null,
        ?WorkspaceConfig $workspaceConfig = null
    ) {
        $this->workspaceAccess = $workspaceAccess ?? app(WorkspaceAccess::class);
        $this->workspaceConfig = $workspaceConfig ?? app(WorkspaceConfig::class);
        
        // Initialize client with default config (will be updated per workspace as needed)
        $this->client = new Client(
            config('shopee.partner_id'),
            config('shopee.partner_key'),
            ['timeout' => 30]
        );

        if (config('shopee.sandbox')) {
            $this->client->useDebugMode();
        }
    }

    private ?int $activeWorkspaceId = null;

    /**
     * Set an explicit active workspace ID for the service context (useful in background jobs / CLI).
     */
    public function setActiveWorkspaceId(?int $workspaceId): self
    {
        $this->activeWorkspaceId = $workspaceId;
        return $this;
    }

    /**
     * Run a callback within the context of a specific workspace.
     */
    public function withWorkspace(int $workspaceId, callable $callback): mixed
    {
        $previous = $this->activeWorkspaceId;
        $this->activeWorkspaceId = $workspaceId;
        try {
            return $callback($this);
        } finally {
            $this->activeWorkspaceId = $previous;
        }
    }

    /**
     * Get the current user's workspace ID
     */
    private function getCurrentWorkspaceId(): ?int
    {
        if ($this->activeWorkspaceId !== null) {
            return $this->activeWorkspaceId;
        }

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $workspace = $this->workspaceAccess->ensurePersonalWorkspace($user);
        return $workspace->id;
    }

    /**
     * Get Shopee configuration for the current user's workspace
     */
    private function getCurrentWorkspaceShopeeConfig(): array
    {
        $workspaceId = $this->getCurrentWorkspaceId();
        if ($workspaceId) {
            return $this->workspaceConfig->getShopeeConfig($workspaceId);
        }

        // Fallback to global config
        return [
            'partner_id' => config('shopee.partner_id'),
            'partner_key' => config('shopee.partner_key'),
            'push_partner_key' => config('shopee.push_partner_key'),
            'redirect_url' => config('shopee.redirect_url'),
            'webhook_url' => config('shopee.webhook_url'),
            'sandbox' => config('shopee.sandbox', true),
        ];
    }

    /**
     * Get Shopee configuration for a specific workspace
     */
    private function getWorkspaceShopeeConfig(int $workspaceId): array
    {
        if ($workspaceId) {
            return $this->workspaceConfig->getShopeeConfig($workspaceId);
        }

        // Fallback to global config
        return [
            'partner_id' => config('shopee.partner_id'),
            'partner_key' => config('shopee.partner_key'),
            'push_partner_key' => config('shopee.push_partner_key'),
            'redirect_url' => config('shopee.redirect_url'),
            'webhook_url' => config('shopee.webhook_url'),
            'sandbox' => config('shopee.sandbox', true),
        ];
    }

    /**
     * Configure the client for a specific workspace
     */
    private function configureClientForWorkspace(int $workspaceId): void
    {
        $config = $this->getWorkspaceShopeeConfig($workspaceId);
        
        // Recreate the client with workspace-specific credentials
        $this->client = new Client(
            $config['partner_id'],
            $config['partner_key'],
            ['timeout' => 30]
        );

        if ($config['sandbox']) {
            $this->client->useDebugMode();
        }
    }

    /**
     * Configure the client for the current user's workspace
     */
    private function configureClientForCurrentWorkspace(): void
    {
        $workspaceId = $this->getCurrentWorkspaceId();
        if ($workspaceId) {
            $this->configureClientForWorkspace($workspaceId);
        }
    }

    /**
     * Get the raw SDK client (for direct API access).
     */
    public function client(): Client
    {
        $this->configureClientForCurrentWorkspace();
        return $this->client;
    }

    /**
     * Set the access token for a specific store and return the client.
     * Refreshes the access token when expired or within the near-expiry buffer.
     */
    public function forStore(ShopeeStore $store): Client
    {
        $store->loadMissing('token');

        if (!$store->token) {
            throw new \RuntimeException("Store #{$store->id} has no token.");
        }

        if ($store->token->auth_expires_at->isPast()) {
            throw new \RuntimeException(
                "Store #{$store->id} authorization expired. Please reauthorize from the Dashboard."
            );
        }

        // Configure client for this store's workspace
        $this->configureClientForWorkspace($store->workspace_id);

        if ($this->accessTokenNeedsRefresh($store)) {
            $this->refreshStoreToken($store);
            $store->load('token');
        }

        $accessToken = $this->resolveAccessToken($store);
        $this->client->setAccessToken((int) $store->shop_id, $accessToken);

        return $this->client;
    }

    /**
     * Refresh shop access/refresh tokens via Shopee's RefreshAccessToken API.
     * Uses a per-shop lock so concurrent callers cannot burn the single-use refresh_token.
     *
     * @throws \Exception When refresh fails (failure metadata is recorded on the token row)
     */
    public function refreshStoreToken(ShopeeStore $store): void
    {
        $store->loadMissing('token');

        if (!$store->token) {
            throw new \RuntimeException("Store #{$store->id} has no token.");
        }

        if ($store->token->auth_expires_at->isPast()) {
            throw new \RuntimeException(
                "Store #{$store->id} authorization expired. Please reauthorize from the Dashboard."
            );
        }

        $lock = Cache::lock("shopee:token-refresh:shop_{$store->shop_id}", 30);

        try {
            $lock->block(15, function () use ($store) {
                $store->load('token');
                $token = $store->token;

                if (!$token) {
                    throw new \RuntimeException("Store #{$store->id} has no token.");
                }

                // Another request may have refreshed while we waited for the lock.
                if (!$this->accessTokenNeedsRefresh($store)) {
                    return;
                }

                $token->update(['last_refresh_attempt_at' => now()]);

                try {
                    $refreshToken = decrypt($token->refresh_token);
                    $newTokens = $this->refreshViaApi($store, $refreshToken);

                    $token->update([
                        'access_token' => encrypt($newTokens['access_token']),
                        'refresh_token' => encrypt($newTokens['refresh_token']),
                        'access_token_expires_at' => now()->addSeconds($newTokens['expire_in'] ?? 14400),
                        'refresh_token_expires_at' => now()->addDays(30),
                        'last_refreshed_at' => now(),
                        'last_refresh_failed_at' => null,
                        'last_refresh_error' => null,
                        'refresh_failure_count' => 0,
                    ]);

                    if (!$store->is_active) {
                        $store->update(['is_active' => true]);
                    }

                    $this->clearStoreCache($store);
                    $store->setRelation('token', $token->fresh());
                } catch (\Exception $e) {
                    $token->forceFill([
                        'last_refresh_failed_at' => now(),
                        'last_refresh_error' => mb_substr($e->getMessage(), 0, 255),
                    ]);
                    $token->refresh_failure_count = ((int) $token->refresh_failure_count) + 1;
                    $token->save();
                    $this->clearStoreCache($store);
                    throw $e;
                }
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException(
                "Store #{$store->id} token refresh is already in progress. Please retry.",
                0,
                $e
            );
        }
    }

    /**
     * Exchange a refresh token against the store's own workspace credentials.
     *
     * Refresh runs from CLI/cron (no authenticated user) and from other tenants'
     * requests, so the client must be configured for `$store->workspace_id`
     * instead of whatever workspace happens to be active.
     *
     * @return array<string, mixed>
     */
    private function refreshViaApi(ShopeeStore $store, string $refreshToken): array
    {
        $callback = fn () => $this->auth()->refreshNewToken($refreshToken, $store->shop_id);

        return $store->workspace_id
            ? $this->withWorkspace($store->workspace_id, $callback)
            : $callback();
    }

    /**
     * Whether the store's access token is expired or within the refresh buffer.
     */
    public function accessTokenNeedsRefresh(ShopeeStore $store): bool
    {
        $store->loadMissing('token');

        if (!$store->token || !$store->token->access_token_expires_at) {
            return true;
        }

        return $store->token->access_token_expires_at->lte(
            now()->addSeconds(self::ACCESS_TOKEN_REFRESH_BUFFER_SECONDS)
        );
    }

    /**
     * Clear cached data for a store (e.g. on token expiry).
     */
    public function clearStoreCache(ShopeeStore $store): void
    {
        Cache::forget($this->tokenCacheKey($store));
    }

    /**
     * Mark a store as inactive due to auth failure and clear its cache.
     */
    public function invalidateStore(ShopeeStore $store): void
    {
        $this->clearStoreCache($store);
        $store->update(['is_active' => false]);
    }

    /**
     * Get the Auth helper for OAuth operations.
     */
    public function auth()
    {
        $this->configureClientForCurrentWorkspace();
        return $this->client->auth();
    }

    /**
     * Get the callback URL configured for the app.
     */
    public function getRedirectUrl(): string
    {
        $this->configureClientForCurrentWorkspace();
        // Return the redirect URL from the client's config (which is now workspace-specific)
        // But we need to extract it from the client or get it from config directly
        // Let's get it from the workspace config instead
        $workspaceId = $this->getCurrentWorkspaceId();
        if ($workspaceId) {
            $config = $this->workspaceConfig->getShopeeConfig($workspaceId);
            return $config['redirect_url'] ?? config('shopee.redirect_url');
        }

        return config('shopee.redirect_url');
    }

    /**
     * Fetch the current holiday / vacation mode status for a store.
     */
    public function getHolidayMode(ShopeeStore $store): array
    {
        return $this->callWithTokenRetry($store, function (Client $client) {
            $res = $client->call('GET', 'shop/get_shop_holiday_mode');
            return is_array($res) && isset($res['response']) ? $res['response'] : (is_array($res) ? $res : []);
        });
    }

    /**
     * Set holiday / vacation mode for a store.
     */
    public function setHolidayMode(ShopeeStore $store, bool $enabled, int $modeType = 0): array
    {
        return $this->callWithTokenRetry($store, function (Client $client) use ($enabled, $modeType) {
            $res = $client->call('POST', 'shop/set_shop_holiday_mode', [
                RequestOptions::JSON => [
                    'holiday_mode_on' => $enabled,
                    'holiday_mode_type' => $modeType,
                ],
            ]);
            return is_array($res) && isset($res['response']) ? $res['response'] : (is_array($res) ? $res : []);
        });
    }

    /**
     * Execute a callback using the authenticated store client, with automatic token refresh retry on token errors.
     */
    public function callWithTokenRetry(ShopeeStore $store, callable $callback): mixed
    {
        $client = $this->forStore($store);

        try {
            return $callback($client);
        } catch (AuthorizationException | ShopeeException $e) {
            if ($e instanceof ShopeeException && !$this->isTokenError($e)) {
                throw $e;
            }

            try {
                $this->clearStoreCache($store);
                $this->refreshStoreToken($store);
                $store->unsetRelation('token');
                $store->load('token');
                $client = $this->forStore($store);

                return $callback($client);
            } catch (AuthorizationException | ShopeeException $retryError) {
                if ($retryError instanceof ShopeeException && !$this->isTokenError($retryError)) {
                    throw $retryError;
                }
                $this->invalidateStore($store);
                throw new \RuntimeException(
                    "Store #{$store->id} token expired. Please re-link from the Dashboard. ({$retryError->getMessage()})"
                );
            } catch (\Exception $refreshError) {
                $this->invalidateStore($store);
                throw new \RuntimeException(
                    "Store #{$store->id} token expired. Please re-link from the Dashboard. ({$refreshError->getMessage()})"
                );
            }
        }
    }

    private function isTokenError(ShopeeException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'access_token') || str_contains($msg, 'token expired');
    }

    private function resolveAccessToken(ShopeeStore $store): string
    {
        $token = $store->token;
        $cacheKey = $this->tokenCacheKey($store);
        $secondsUntilExpiry = max(
            1,
            $token->access_token_expires_at->getTimestamp() - now()->getTimestamp()
        );
        $ttlSeconds = min(3600, $secondsUntilExpiry);

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($token) {
            return decrypt($token->access_token);
        });
    }

    private function tokenCacheKey(ShopeeStore $store): string
    {
        return "shopee:token:shop_{$store->shop_id}";
    }
}