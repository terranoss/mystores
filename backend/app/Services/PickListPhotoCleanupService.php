<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PickListComment;
use App\Models\PickListCompletion;
use App\Models\ShopeeStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes pick-list completion photos when no open (still-to-fulfill) orders
 * still need that item in the workspace.
 */
class PickListPhotoCleanupService
{
    /**
     * Clean up pick photos for items on this order after its status may have changed.
     *
     * @return int Number of completion rows deleted
     */
    public function cleanupForOrder(Order $order): int
    {
        $order->loadMissing(['items', 'store']);
        $workspaceId = (int) ($order->store?->workspace_id ?? 0);
        if ($workspaceId <= 0 || $order->items->isEmpty()) {
            return 0;
        }

        $keys = [];
        foreach ($order->items as $item) {
            $keys[] = PickListCompletion::makeItemKey($item->item_id, $item->model_id, $item->item_name);
        }

        return $this->cleanupItemKeys($workspaceId, array_values(array_unique($keys)));
    }

    /**
     * @param  list<string>  $itemKeys
     * @return int Number of completion rows deleted
     */
    public function cleanupItemKeys(int $workspaceId, array $itemKeys): int
    {
        $itemKeys = array_values(array_filter(array_unique($itemKeys), fn ($k) => $k !== ''));
        if ($itemKeys === []) {
            return 0;
        }

        $deleted = 0;
        foreach ($itemKeys as $itemKey) {
            if ($this->hasOpenOrdersForItemKey($workspaceId, $itemKey)) {
                continue;
            }
            $deleted += $this->deleteCompletionsForItemKey($workspaceId, $itemKey);
        }

        return $deleted;
    }

    /**
     * Sweep all completions older than today (and today's if safe) for all workspaces.
     *
     * @return int Number of completion rows deleted
     */
    public function cleanupStale(): int
    {
        $deleted = 0;
        $workspaceIds = PickListCompletion::query()
            ->distinct()
            ->pluck('workspace_id');

        foreach ($workspaceIds as $workspaceId) {
            $keys = PickListCompletion::query()
                ->where('workspace_id', $workspaceId)
                ->distinct()
                ->pluck('item_key')
                ->all();
            $deleted += $this->cleanupItemKeys((int) $workspaceId, $keys);
        }

        return $deleted;
    }

    /**
     * Delete pick-list comments older than the given number of days.
     *
     * @return int Number of comment rows deleted
     */
    public function pruneOldComments(int $days = 14): int
    {
        $cutoff = now()->subDays(max(1, $days))->toDateString();

        return PickListComment::query()
            ->whereDate('pick_date', '<', $cutoff)
            ->delete();
    }

    private function hasOpenOrdersForItemKey(int $workspaceId, string $itemKey): bool
    {
        $storeIds = ShopeeStore::query()
            ->where('workspace_id', $workspaceId)
            ->pluck('id');
        if ($storeIds->isEmpty()) {
            return false;
        }

        $openStatuses = $this->openStatuses();
        $placeholders = implode(',', array_fill(0, count($openStatuses), '?'));
        $orders = Order::query()
            ->whereIn('store_id', $storeIds)
            ->whereRaw('LOWER(COALESCE(order_status, \'\')) in ('.$placeholders.')', $openStatuses)
            ->with('items:id,order_id,item_id,model_id,item_name')
            ->get(['id', 'store_id', 'order_status']);

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = PickListCompletion::makeItemKey($item->item_id, $item->model_id, $item->item_name);
                if ($key === $itemKey) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Raw statuses where the seller may still need to pick the item.
     *
     * @return list<string>
     */
    private function openStatuses(): array
    {
        return array_map('strtolower', OrderStatus::rawValuesFor([OrderStatus::class, 'needsFulfillment']));
    }

    private function deleteCompletionsForItemKey(int $workspaceId, string $itemKey): int
    {
        $completions = PickListCompletion::query()
            ->where('workspace_id', $workspaceId)
            ->where('item_key', $itemKey)
            ->get();

        if ($completions->isEmpty()) {
            return 0;
        }

        $disk = Storage::disk('local');
        foreach ($completions as $completion) {
            if ($completion->photo_path && $disk->exists($completion->photo_path)) {
                $disk->delete($completion->photo_path);
            }
            $completion->delete();
        }

        Log::info('Pick list photos deleted; no open orders need item.', [
            'workspace_id' => $workspaceId,
            'item_key' => $itemKey,
            'count' => $completions->count(),
        ]);

        return $completions->count();
    }
}
