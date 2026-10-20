<?php

namespace App\Services;

use App\Models\ShopeeStore;
use App\Notifications\StoreReauthNotification;

class NotificationGeneratorService
{
    public function __construct(
        private readonly StoreHealthService $storeHealth,
    ) {}

    /**
     * Check store health and generate token/reauth notifications without duplicates.
     */
    public function generateStoreHealthNotifications(): void
    {
        $stores = ShopeeStore::with(['user', 'token'])->get();

        foreach ($stores as $store) {
            $user = $store->user;
            if (!$user) continue;

            $status = $this->storeHealth->computeHealth($store)['connection_status'];
            if (!in_array($status, ['expiring_soon', 'refresh_failed', 'reauthorize_required', 'missing_token'])) {
                continue;
            }

            // De-duplication check: Skip if an unread store_reauth notification already exists for this store
            $hasUnread = $user->unreadNotifications()
                ->where('data->category', 'store_reauth')
                ->where('data->store_id', $store->id)
                ->exists();

            if (!$hasUnread) {
                $reason = match ($status) {
                    'expiring_soon' => 'token is expiring soon',
                    'refresh_failed' => 'token refresh failed',
                    'reauthorize_required' => 'requires seller re-authorization',
                    'missing_token' => 'token has expired',
                    default => 'connection issue detected',
                };

                $user->notify(new StoreReauthNotification(
                    storeId: $store->id,
                    shopName: $store->shop_name ?? "Store #{$store->shop_id}",
                    platform: strtoupper($store->platform),
                    reason: $reason
                ));
            }
        }
    }
}
