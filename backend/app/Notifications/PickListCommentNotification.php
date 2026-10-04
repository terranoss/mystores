<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PickListCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $commentId,
        public readonly string $authorName,
        public readonly string $itemName,
        public readonly string $bodyPreview,
        public readonly string $kind,
        public readonly string $itemKey,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $kindLabel = $this->kind === 'pending' ? 'Pending' : 'Today';

        return [
            'category' => 'picklist_comment',
            'title' => "{$this->authorName} commented",
            'message' => "{$kindLabel} pick list · {$this->itemName}: {$this->bodyPreview}",
            'comment_id' => $this->commentId,
            'kind' => $this->kind,
            'item_key' => $this->itemKey,
            'action_type' => 'view_picklist',
        ];
    }
}
