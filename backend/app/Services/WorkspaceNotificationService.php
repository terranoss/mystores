<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PickListComment;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\OrderNewNotification;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\PickListCommentNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkspaceNotificationService
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
    ) {}

    /**
     * Notify workspace members about a newly synced order (today only)
     * or an order status change. Safe to call after every upsert.
     */
    public function notifyOrderUpsert(
        ShopeeStore $store,
        Order $order,
        bool $wasCreated,
        ?string $previousStatus,
    ): void {
        try {
            $workspace = $this->resolveWorkspace($store);
            if (! $workspace) {
                return;
            }

            $shopName = $store->shop_name ?: "Store #{$store->shop_id}";
            $platform = strtoupper((string) ($store->platform ?: 'shopee'));
            $status = (string) ($order->order_status ?? '');

            if ($wasCreated && $this->isOrderedToday($order)) {
                $this->notifyWorkspace(
                    $workspace,
                    new OrderNewNotification(
                        orderId: $order->id,
                        orderSn: (string) $order->order_sn,
                        shopName: $shopName,
                        platform: $platform,
                        orderStatus: $status !== '' ? $status : 'unknown',
                    ),
                    dedupe: [
                        'category' => 'order_new',
                        'order_id' => $order->id,
                    ],
                );

                return;
            }

            if (
                ! $wasCreated
                && $previousStatus !== null
                && $status !== ''
                && (string) $previousStatus !== $status
            ) {
                $this->notifyWorkspace(
                    $workspace,
                    new OrderStatusChangedNotification(
                        orderId: $order->id,
                        orderSn: (string) $order->order_sn,
                        shopName: $shopName,
                        platform: $platform,
                        fromStatus: (string) $previousStatus,
                        toStatus: $status,
                    ),
                    dedupe: [
                        'category' => 'order_status',
                        'order_id' => $order->id,
                        'order_status' => $status,
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Order notification failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify other workspace members about a new pick-list comment.
     */
    public function notifyPickListComment(
        Workspace $workspace,
        PickListComment $comment,
        User $author,
    ): void {
        try {
            $preview = Str::limit(trim($comment->body), 80, '…');
            $this->notifyWorkspace(
                $workspace,
                new PickListCommentNotification(
                    commentId: $comment->id,
                    authorName: $author->name ?: 'Teammate',
                    itemName: $comment->item_name ?: $comment->item_key,
                    bodyPreview: $preview,
                    kind: $comment->kind,
                    itemKey: $comment->item_key,
                ),
                excludeUserId: (int) $author->id,
            );
        } catch (\Throwable $e) {
            Log::warning('Pick-list comment notification failed', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isOrderedToday(Order $order): bool
    {
        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $today = Carbon::today($tz);
        $orderedAt = $order->ordered_at;
        if ($orderedAt) {
            return Carbon::parse($orderedAt)->timezone($tz)->isSameDay($today);
        }

        return Carbon::parse($order->created_at)->timezone($tz)->isSameDay($today);
    }

    private function resolveWorkspace(ShopeeStore $store): ?Workspace
    {
        if ($store->workspace_id) {
            return $store->workspace ?? Workspace::find($store->workspace_id);
        }
        $owner = $store->user;
        if (! $owner) {
            return null;
        }

        return $this->workspaceAccess->primaryWorkspace($owner)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($owner);
    }

    /**
     * @param  array{category: string, order_id?: int, order_status?: string}|null  $dedupe
     */
    private function notifyWorkspace(
        Workspace $workspace,
        object $notification,
        ?int $excludeUserId = null,
        ?array $dedupe = null,
    ): void {
        foreach ($this->workspaceUsers($workspace) as $user) {
            if ($excludeUserId !== null && (int) $user->id === $excludeUserId) {
                continue;
            }
            if ($dedupe !== null && $this->userHasUnread($user, $dedupe)) {
                continue;
            }
            $user->notify($notification);
        }
    }

    /**
     * @param  array{category: string, order_id?: int, order_status?: string}  $dedupe
     */
    private function userHasUnread(User $user, array $dedupe): bool
    {
        $query = $user->unreadNotifications()
            ->where('data->category', $dedupe['category']);
        if (array_key_exists('order_id', $dedupe)) {
            $query->where('data->order_id', $dedupe['order_id']);
        }
        if (array_key_exists('order_status', $dedupe)) {
            $query->where('data->order_status', $dedupe['order_status']);
        }

        return $query->exists();
    }

    /**
     * @return list<User>
     */
    private function workspaceUsers(Workspace $workspace): array
    {
        return $this->workspaceAccess->members($workspace)
            ->map(fn ($m) => $m->user)
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }
}
