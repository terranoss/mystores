<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderNewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderSn,
        public readonly string $shopName,
        public readonly string $platform,
        public readonly string $orderStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'order_new',
            'title' => "New order: {$this->orderSn}",
            'message' => "New today order from {$this->shopName} ({$this->platform}) — status {$this->orderStatus}.",
            'order_id' => $this->orderId,
            'order_sn' => $this->orderSn,
            'platform' => $this->platform,
            'order_status' => $this->orderStatus,
            'action_type' => 'view_order',
        ];
    }
}
