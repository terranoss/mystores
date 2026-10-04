<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderSn,
        public readonly string $shopName,
        public readonly string $platform,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'order_status',
            'title' => "Order updated: {$this->orderSn}",
            'message' => "{$this->shopName}: {$this->fromStatus} → {$this->toStatus}.",
            'order_id' => $this->orderId,
            'order_sn' => $this->orderSn,
            'platform' => $this->platform,
            'from_status' => $this->fromStatus,
            'order_status' => $this->toStatus,
            'action_type' => 'view_order',
        ];
    }
}
