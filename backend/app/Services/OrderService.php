<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\User;

class OrderService
{
    private const DETAIL_OPTIONAL_FIELDS = 'buyer_username,recipient_address,item_list,pay_time,total_amount,cancel_reason';

    private const WATERMARK_OVERLAP_MINUTES = 10;

    private const MAX_LOOKBACK_DAYS = 14;

    private readonly WorkspaceAccess $workspaceAccess;
    private readonly PickListPhotoCleanupService $pickListPhotos;
    private readonly WorkspaceNotificationService $workspaceNotifications;

    public function __construct(
        private readonly ShopeeService $shopee,
        ?WorkspaceAccess $workspaceAccess = null,
        ?PickListPhotoCleanupService $pickListPhotos = null,
        ?WorkspaceNotificationService $workspaceNotifications = null,
    ) {
        $this->workspaceAccess = $workspaceAccess ?? app(WorkspaceAccess::class);
        $this->pickListPhotos = $pickListPhotos ?? app(PickListPhotoCleanupService::class);
        $this->workspaceNotifications = $workspaceNotifications ?? app(WorkspaceNotificationService::class);
    }

    /**
     * @return list<int>
     */
    private function workspaceIds(User $user): array
    {
        $ids = $this->workspaceAccess->workspaceIdsFor($user);
        if ($ids === []) {
            $ids = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }
        return $ids;
    }

    /**
     * Get orders for a user's stores, with optional filters.
     *
     * @param  list<string>|null  $statusIn
     */
    public function getOrders(
        User $user,
        ?int $storeId = null,
        ?string $status = null,
        ?string $search = null,
        ?string $sortBy = null,
        string $sortDir = 'desc',
        ?array $statusIn = null,
        ?string $urgency = null,
        ?string $orderedFrom = null,
        ?string $orderedTo = null,
        ?string $platform = null,
    ): mixed {
        $query = Order::whereHas('store', function ($q) use ($user, $platform) {
            $q->whereIn('workspace_id', $this->workspaceIds($user));
            if ($platform === 'lazada' || $platform === 'shopee') {
                $q->where('platform', $platform);
            }
        })
            ->with(['items', 'store:id,shop_name,shop_id,region,platform'])
            ->when($storeId, fn ($q, $id) => $q->where('store_id', $id));

        if ($status !== null && $status !== '') {
            $query->where('order_status', $status);
        } elseif (is_array($statusIn) && $statusIn !== []) {
            $query->whereIn('order_status', array_values($statusIn));
        }

        if ($search !== null && $search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('order_sn', 'like', $term)
                    ->orWhere('buyer_username', 'like', $term)
                    ->orWhereHas('items', fn ($iq) => $iq->where('item_name', 'like', $term));
            });
        }

        if ($urgency !== null && $urgency !== '') {
            $this->applyUrgencyFilter($query, $urgency);
        }

        if ($orderedFrom !== null && $orderedFrom !== '') {
            $query->where('ordered_at', '>=', $orderedFrom . ' 00:00:00');
        }
        if ($orderedTo !== null && $orderedTo !== '') {
            $query->where('ordered_at', '<=', $orderedTo . ' 23:59:59');
        }

        $allowedSort = ['order_sn', 'order_status', 'total_amount', 'ordered_at', 'ship_by_date', 'synced_at', 'created_at'];
        if (in_array($sortBy, $allowedSort, true)) {
            $dir = $sortDir === 'asc' ? 'asc' : 'desc';
            if ($sortBy === 'ship_by_date') {
                $query->orderByRaw('ship_by_date is null')
                    ->orderBy('ship_by_date', $dir)
                    ->orderByDesc('id');
            } else {
                $query->orderBy($sortBy, $dir);
            }
        } else {
            $query->orderByDesc('ordered_at')->orderByDesc('id');
        }

        return $query->paginate(50);
    }

    /**
     * Filter actionable orders by ship_by_date urgency.
     */
    private function applyUrgencyFilter($query, string $urgency): void
    {
        $actionable = ['READY_TO_SHIP', 'PROCESSED', 'pending', 'packed'];
        $startOfToday = now()->startOfDay();
        $query->whereIn('order_status', $actionable)->whereNotNull('ship_by_date');

        if ($urgency === 'overdue') {
            $query->where('ship_by_date', '<', $startOfToday);
            return;
        }

        if ($urgency === 'due_today') {
            $query->where('ship_by_date', '>=', $startOfToday)
                ->where('ship_by_date', '<', $startOfToday->copy()->addDay());
            return;
        }

        if ($urgency === 'due_48h') {
            $query->where('ship_by_date', '>=', $startOfToday)
                ->where('ship_by_date', '<', $startOfToday->copy()->addHours(48));
        }
    }

    /**
     * Get a single order with items, owner-checked.
     */
    public function getOrder(User $user, int $id): Order
    {
        $order = Order::with(['items', 'store'])->findOrFail($id);

        if (!$this->workspaceAccess->canAccessStore($user, $order->store)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden.');
        }

        return $order;
    }

    /**
     * Cancel an order via Shopee API and update local DB.
     */
    public function cancelOrder(User $user, int $id, string $reason): Order
    {
        $order = $this->getOrder($user, $id);
        $store = $order->store;
        $store->loadMissing('token');

        if (!$store->token || !$store->is_active) {
            abort(400, 'Store token missing or inactive.');
        }

        $cancelableStatuses = ['READY_TO_SHIP', 'PROCESSED'];
        if (!in_array($order->order_status, $cancelableStatuses)) {
            abort(400, "Order cannot be cancelled in status: {$order->order_status}");
        }

        $client = $this->shopee->forStore($store);
        $itemList = [];
        if ($reason === 'OUT_OF_STOCK') {
            if ($order->items->isEmpty()) {
                $order = $this->syncOrderBySn($store, $order->order_sn);
            }

            $itemList = $order->items
                ->map(fn (OrderItem $item) => [
                    'item_id' => (int) $item->item_id,
                    'model_id' => (int) ($item->model_id ?? 0),
                ])
                ->values()
                ->all();

            if (empty($itemList)) {
                abort(422, 'Order items are unavailable. Sync the order and try again.');
            }
        }

        $response = $client->Order->cancelOrder($order->order_sn, $reason, $itemList);

        $order->update([
            'order_status' => 'CANCELLED',
            'cancel_reason' => $reason,
            'raw_response' => is_array($response) ? $response : ['cancel_response' => $response],
            'synced_at' => now(),
        ]);

        $order = $order->fresh()->load(['items', 'store']);
        $this->pickListPhotos->cleanupForOrder($order);

        return $order;
    }

    /**
     * Trigger a sync for a user's active stores.
     * Defaults to Shopee only so existing clients keep prior behavior.
     */
    public function syncForUser(User $user, string $platform = 'shopee'): array
    {
        if ($platform === 'lazada') {
            return app(LazadaOrderService::class)->syncForUser($user);
        }

        $stores = ShopeeStore::with('token')
            ->whereIn('workspace_id', $this->workspaceIds($user))
            ->where('is_active', true)
            ->where('platform', 'shopee')
            ->get();

        $results = [];

        foreach ($stores as $store) {
            if (!$store->token) {
                $results[] = "Store #{$store->id}: skipped (no token)";
                continue;
            }

            try {
                $this->syncForStore($store);
                $results[] = "Store #{$store->id}: synced";
            } catch (\Exception $e) {
                $results[] = "Store #{$store->id}: failed - {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Sync orders for a single Shopee store using update_time watermark reconciliation.
     */
    public function syncForStore(ShopeeStore $store): void
    {
        if (($store->platform ?? 'shopee') !== 'shopee') {
            throw new \InvalidArgumentException("Store #{$store->id} is not a Shopee store.");
        }

        $client = $this->shopee->forStore($store);
        $syncStartedAt = now();
        $cursor = null;
        $pageSize = 50;
        $timeFrom = $this->resolveTimeFrom($store);

        do {
            $params = [
                'page_size' => $pageSize,
                'time_range_field' => 'update_time',
                'time_from' => $timeFrom,
                'time_to' => $syncStartedAt->timestamp,
            ];

            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $client->Order->getOrderList($params);
            $orders = $response['order_list'] ?? $response['orders'] ?? [];
            $cursor = $response['next_cursor'] ?? null;
            $more = (bool) ($response['more'] ?? !empty($cursor));

            if (empty($orders)) {
                break;
            }

            $orderSns = array_values(array_filter(array_map(
                fn ($orderData) => $orderData['order_sn'] ?? null,
                $orders
            )));

            foreach (array_chunk($orderSns, 50) as $chunk) {
                $detailResponse = $client->Order->getOrderDetail($chunk, [
                    'response_optional_fields' => self::DETAIL_OPTIONAL_FIELDS,
                ]);

                $details = $detailResponse['order_list'] ?? [];
                foreach ($details as $detailData) {
                    $this->upsertOrderFromDetail($store, $detailData);
                }
            }
        } while ($more);

        $store->update(['orders_synced_at' => $syncStartedAt]);
    }

    /**
     * Fetch and upsert a single order by order_sn (used by Order Status Push).
     */
    public function syncOrderBySn(ShopeeStore $store, string $orderSn): Order
    {
        $client = $this->shopee->forStore($store);
        $detailResponse = $client->Order->getOrderDetail($orderSn, [
            'response_optional_fields' => self::DETAIL_OPTIONAL_FIELDS,
        ]);

        $detailData = $detailResponse['order_list'][0] ?? $detailResponse['order'] ?? null;
        if (!is_array($detailData)) {
            throw new \RuntimeException("Order detail not found for {$orderSn}");
        }

        return $this->upsertOrderFromDetail($store, $detailData);
    }

    /**
     * Re-sync after ship_order. Shopee may briefly still report READY_TO_SHIP.
     */
    public function syncAfterShip(ShopeeStore $store, string $orderSn, int $attempts = 3, int $delayMs = 800): Order
    {
        $order = $this->syncOrderBySn($store, $orderSn);
        for ($i = 1; $i < $attempts; $i++) {
            if (in_array($order->order_status, ['PROCESSED', 'SHIPPED'], true)) {
                return $order;
            }
            usleep($delayMs * 1000);
            $order = $this->syncOrderBySn($store, $orderSn);
        }

        return $order;
    }

    /**
     * Persist one Shopee order detail payload into local orders / order_items.
     */
    public function upsertOrderFromDetail(ShopeeStore $store, array $detailData): Order
    {
        $orderSn = $detailData['order_sn'] ?? null;
        if (!$orderSn) {
            throw new \InvalidArgumentException('Order detail missing order_sn.');
        }

        $payTime = $detailData['pay_time'] ?? $detailData['paid_time'] ?? null;
        $createTime = $detailData['create_time'] ?? null;

        $attributes = [
            'order_status' => $detailData['order_status'] ?? null,
            'total_amount' => $detailData['total_amount'] ?? 0,
            'currency' => $detailData['currency'] ?? null,
            'buyer_username' => $detailData['buyer_username'] ?? $detailData['buyer_user_name'] ?? null,
            'shipping_address' => $this->extractAddress($detailData),
            'paid_time' => $this->formatUnixTimestamp($payTime),
            'ship_by_date' => $this->formatUnixTimestamp($detailData['ship_by_date'] ?? null),
            'cancel_reason' => $detailData['cancel_reason'] ?? null,
            'raw_response' => $detailData,
            'synced_at' => now(),
        ];
        // Only set ordered_at when Shopee sends create_time — never wipe an existing value.
        $orderedAt = $this->formatUnixTimestamp($createTime);
        if ($orderedAt !== null) {
            $attributes['ordered_at'] = $orderedAt;
        }

        $previous = Order::query()
            ->where('store_id', $store->id)
            ->where('order_sn', $orderSn)
            ->first();
        $previousStatus = $previous?->order_status;

        $order = Order::updateOrCreate(
            ['store_id' => $store->id, 'order_sn' => $orderSn],
            $attributes
        );
        $wasCreated = $order->wasRecentlyCreated;

        foreach ($detailData['item_list'] ?? [] as $item) {
            $sku = $item['model_sku'] ?? $item['item_sku'] ?? null;
            OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'item_id' => $item['item_id'] ?? $item['id'] ?? 0],
                [
                    'model_id' => $item['model_id'] ?? null,
                    'item_name' => $item['item_name'] ?? null,
                    'sku' => $sku,
                    'quantity' => $this->resolveItemQuantity($item),
                    'price' => $item['model_discounted_price'] ?? $item['model_original_price'] ?? $item['price'] ?? 0,
                ]
            );
        }

        $order = $order->fresh()->load(['items', 'store']);
        $this->pickListPhotos->cleanupForOrder($order);
        $this->workspaceNotifications->notifyOrderUpsert(
            $store,
            $order,
            $wasCreated,
            $previousStatus,
        );

        return $order;
    }

    /**
     * Correct order_items.quantity from stored Shopee raw_response.item_list
     * (model_quantity), for rows previously defaulted to 1.
     *
     * @return array{updated:int,skipped:int}
     */
    public function backfillQuantitiesFromRaw(): array
    {
        $updated = 0;
        $skipped = 0;
        Order::query()
            ->whereHas('store', fn ($q) => $q->where('platform', 'shopee'))
            ->whereNotNull('raw_response')
            ->with('items')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$updated, &$skipped) {
                foreach ($orders as $order) {
                    $raw = $order->raw_response;
                    $itemList = is_array($raw) ? ($raw['item_list'] ?? null) : null;
                    if (! is_array($itemList) || $itemList === []) {
                        $skipped++;
                        continue;
                    }
                    $changed = false;
                    foreach ($itemList as $item) {
                        $itemId = (int) ($item['item_id'] ?? $item['id'] ?? 0);
                        if ($itemId <= 0) {
                            continue;
                        }
                        $qty = $this->resolveItemQuantity($item);
                        $row = $order->items->firstWhere('item_id', $itemId);
                        if (! $row) {
                            OrderItem::updateOrCreate(
                                ['order_id' => $order->id, 'item_id' => $itemId],
                                [
                                    'model_id' => $item['model_id'] ?? null,
                                    'item_name' => $item['item_name'] ?? null,
                                    'quantity' => $qty,
                                    'price' => $item['model_discounted_price'] ?? $item['model_original_price'] ?? $item['price'] ?? 0,
                                ]
                            );
                            $changed = true;
                            continue;
                        }
                        if ((int) $row->quantity !== $qty) {
                            $row->quantity = $qty;
                            $row->save();
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Fill ordered_at from stored raw_response.create_time for rows missing it.
     *
     * @return array{updated:int,skipped:int}
     */
    public function backfillOrderedAtFromRaw(): array
    {
        $updated = 0;
        $skipped = 0;
        Order::query()
            ->whereNull('ordered_at')
            ->whereNotNull('raw_response')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$updated, &$skipped) {
                foreach ($orders as $order) {
                    $raw = $order->raw_response;
                    $createTime = is_array($raw) ? ($raw['create_time'] ?? null) : null;
                    $orderedAt = $this->formatUnixTimestamp($createTime);
                    if ($orderedAt === null) {
                        $skipped++;
                        continue;
                    }
                    $order->ordered_at = $orderedAt;
                    $order->save();
                    $updated++;
                }
            });
        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Convert Shopee unix timestamp to datetime string.
     * Treats missing / zero / negative values as null (avoids 1970-01-01).
     */
    private function formatUnixTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $ts = (int) $value;
        if ($ts <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function resolveTimeFrom(ShopeeStore $store): int
    {
        $floor = now()->subDays(self::MAX_LOOKBACK_DAYS);

        if (!$store->orders_synced_at) {
            return $floor->timestamp;
        }

        $from = $store->orders_synced_at->copy()->subMinutes(self::WATERMARK_OVERLAP_MINUTES);

        return $from->lt($floor) ? $floor->timestamp : $from->timestamp;
    }

    private function extractAddress(array $data): ?string
    {
        $addr = $data['recipient_address'] ?? $data['shipping_address'] ?? null;
        if (is_array($addr)) {
            return implode(', ', array_filter([
                $addr['full_address'] ?? null,
                $addr['city'] ?? null,
                $addr['state'] ?? null,
                $addr['country'] ?? null,
                $addr['zip_code'] ?? $addr['zipcode'] ?? null,
            ]));
        }
        return $addr;
    }

    /**
     * Shopee get_order_detail uses model_quantity (sometimes model_quantity_purchased).
     *
     * @param  array<string,mixed>  $item
     */
    private function resolveItemQuantity(array $item): int
    {
        $raw = $item['model_quantity']
            ?? $item['model_quantity_purchased']
            ?? $item['quantity']
            ?? 1;

        $qty = (int) $raw;

        return $qty > 0 ? $qty : 1;
    }
}
