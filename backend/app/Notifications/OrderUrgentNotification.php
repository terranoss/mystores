<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderUrgentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderSn,
        public readonly string $shopName,
        public readonly string $shipByDate,
        public readonly bool $isOverdue
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $statusText = $this->isOverdue ? 'OVERDUE' : 'Due Today';
        return [
            'category' => 'order_urgent',
            'title' => "Order {$statusText}: {$this->orderSn}",
            'message' => "Order {$this->orderSn} from {$this->shopName} must be shipped by {$this->shipByDate}.",
            'order_id' => $this->orderId,
            'order_sn' => $this->orderSn,
            'action_type' => 'view_order',
        ];
    }
}
