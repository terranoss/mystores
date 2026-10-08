<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Notifications\OrderUrgentNotification;
use App\Notifications\StoreReauthNotification;
use Carbon\Carbon;

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

    /**
     * Check urgent shipping deadlines and generate order notifications without duplicates.
     */
    public function generateUrgentOrderNotifications(): void
    {
        $orders = Order::with('store.user')
            ->whereNotIn('order_status', ['COMPLETED', 'CANCELLED', 'SHIPPED'])
            ->whereNotNull('ship_by_date')
            ->get();

        foreach ($orders as $order) {
            $user = $order->store?->user;
            if (!$user) continue;

            $shipBy = Carbon::parse($order->ship_by_date);
            $isOverdue = $shipBy->isPast();
            $isDueToday = $shipBy->isToday();

            if (!$isOverdue && !$isDueToday) {
                continue;
            }

            // De-duplication check: Skip if notification already sent for this specific order
            $alreadyNotified = $user->notifications()
                ->where('data->category', 'order_urgent')
                ->where('data->order_id', $order->id)
                ->exists();

            if (!$alreadyNotified) {
                $user->notify(new OrderUrgentNotification(
                    orderId: $order->id,
                    orderSn: $order->order_sn,
                    shopName: $order->store->shop_name ?? "Store #{$order->store->shop_id}",
                    shipByDate: $shipBy->format('Y-m-d'),
                    isOverdue: $isOverdue
                ));
            }
        }
    }
}
