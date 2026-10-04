<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StoreReauthNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $storeId,
        public readonly string $shopName,
        public readonly string $platform,
        public readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'store_reauth',
            'title' => "Re-authorize Store: {$this->shopName}",
            'message' => "{$this->shopName} ({$this->platform}) {$this->reason}. Re-authorization required.",
            'store_id' => $this->storeId,
            'platform' => $this->platform,
            'action_type' => 'reauthorize',
        ];
    }
}
