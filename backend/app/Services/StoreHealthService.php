<?php

namespace App\Services;

use App\Models\ShopeeStore;

/**
 * Single source of truth for store connection health.
 *
 * Every backend caller (store listing API, notification generator, mobile/web
 * consumers) must derive `connection_status` from this service so the rules
 * cannot drift between endpoints.
 */
class StoreHealthService
{
    /** Access token expiring within this many seconds is reported as expiring soon. */
    public const ACCESS_TOKEN_WARNING_SECONDS = 3600;

    /** Refresh token expiring within this many days is reported as expiring soon. */
    public const REFRESH_TOKEN_WARNING_DAYS = 7;

    /** Consecutive refresh failures before re-authorization becomes mandatory. */
    public const REFRESH_FAILURE_THRESHOLD = 3;

    /**
     * Compute the operational health of a store.
     *
     * @return array{connection_status: string, token_health: string, requires_reauthorization: bool}
     */
    public function computeHealth(ShopeeStore $store): array
    {
        $store->loadMissing('token');
        $token = $store->token;

        if (! $token) {
            return $this->health('missing_token', 'expired', false);
        }

        if (! $store->is_active) {
            return $this->health('disconnected', 'expired', false);
        }

        if ($token->auth_expires_at && $token->auth_expires_at->isPast()) {
            return $this->health('reauthorize_required', 'expired', true);
        }

        // Repeated refresh failures: fatal once the threshold is reached.
        if ($token->last_refresh_failed_at && (int) $token->refresh_failure_count > 0) {
            $requiresReauthorization = (int) $token->refresh_failure_count >= self::REFRESH_FAILURE_THRESHOLD;

            return $this->health(
                $requiresReauthorization ? 'reauthorize_required' : 'refresh_failed',
                'expired',
                $requiresReauthorization
            );
        }

        // Access token expired or expiring within the warning window.
        // Carbon 3 returns a signed diff, so operand order matters:
        // now()->diffInSeconds($expiresAt) is positive for a future expiry.
        if (
            ! $token->access_token_expires_at
            || $token->access_token_expires_at->isPast()
            || now()->diffInSeconds($token->access_token_expires_at) < self::ACCESS_TOKEN_WARNING_SECONDS
        ) {
            return $this->health('expiring_soon', 'expiring_soon', false);
        }

        // Refresh token expiring within the warning window — proactive re-auth.
        if (
            $token->refresh_token_expires_at
            && now()->diffInDays($token->refresh_token_expires_at) < self::REFRESH_TOKEN_WARNING_DAYS
        ) {
            return $this->health('expiring_soon', 'expiring_soon', true);
        }

        return $this->health('active', 'healthy', false);
    }

    /**
     * Whether the store needs the seller to re-authorize before syncing again.
     */
    public function requiresReauthorization(ShopeeStore $store): bool
    {
        return $this->computeHealth($store)['requires_reauthorization'];
    }

    /**
     * Whether the store can currently be synced against the marketplace API.
     */
    public function isOperational(ShopeeStore $store): bool
    {
        return $this->computeHealth($store)['connection_status'] === 'active';
    }

    /**
     * @return array{connection_status: string, token_health: string, requires_reauthorization: bool}
     */
    private function health(string $status, string $tokenHealth, bool $requiresReauthorization): array
    {
        return [
            'connection_status' => $status,
            'token_health' => $tokenHealth,
            'requires_reauthorization' => $requiresReauthorization,
        ];
    }
}
