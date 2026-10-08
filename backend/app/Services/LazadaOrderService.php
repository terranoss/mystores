<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LazadaOrderService
{
    private const WATERMARK_OVERLAP_MINUTES = 10;

    private const MAX_LOOKBACK_DAYS = 14;

    /** Statuses still expected to change via seller/logistics actions. */
    private const ACTIONABLE_STATUSES = [
        'unpaid',
        'pending',
        'packed',
        'ready_to_ship',
    ];

    /**
     * Lifetime of the token-refresh mutex.
     *
     * Must stay comfortably above the worst case refresh round trip
     * (LazadaService: 10s connect + 30s request) plus token persistence,
     * otherwise the lock would lapse while the upstream call is still
     * running and a parallel caller could burn the rotated refresh token.
     */
    private const TOKEN_REFRESH_LOCK_SECONDS = 120;

    /** How long a caller waits for an in-flight refresh before giving up. */
    private const TOKEN_REFRESH_BLOCK_SECONDS = 15;

    /** Status priority: lower = more actionable for seller. */
    private const STATUS_PRIORITY = [
        'pending' => 10,
        'unpaid' => 20,
        'packed' => 30,
        'ready_to_ship' => 40,
        'shipped' => 50,
        'delivered' => 60,
        // MY/SG/ID: after delivered, buyer confirmed receipt (terminal).
        'confirmed' => 65,
        'returned' => 70,
        'failed' => 80,
        'failed_delivery' => 80,
        'canceled' => 90,
        'cancelled' => 90,
    ];

    public function __construct(
        private readonly LazadaService $lazada,
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly PickListPhotoCleanupService $pickListPhotos,
        private readonly WorkspaceNotificationService $workspaceNotifications,
    ) {}

    /**
     * Run a callback within the workspace context of the Lazada service if supported.
     */
    private function runWithWorkspace(?int $workspaceId, callable $callback): mixed
    {
        if ($workspaceId !== null && method_exists($this->lazada, 'withWorkspace') && !($this->lazada instanceof \Mockery\MockInterface)) {
            return $this->lazada->withWorkspace($workspaceId, $callback);
        }

        return $callback();
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

    public function syncForUser(User $user): array
    {
        $stores = ShopeeStore::with('token')
            ->whereIn('workspace_id', $this->workspaceIds($user))
            ->where('is_active', true)
            ->where('platform', 'lazada')
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

    public function syncForStore(ShopeeStore $store): void
    {
        if (($store->platform ?? '') !== 'lazada') {
            throw new \InvalidArgumentException("Store #{$store->id} is not a Lazada store.");
        }

        $this->runWithWorkspace($store->workspace_id, function () use ($store) {
            $this->ensureFreshToken($store);
            $creds = $this->lazada->credentialsForStore($store);
            $this->refreshShopName($store, $creds);
            $syncStartedAt = now();
            $updateAfter = $this->resolveUpdateAfter($store);
            $offset = 0;
            $limit = 50;

            do {
                $response = $this->lazada->getOrdersList($creds['access_token'], $creds['region'], [
                    'update_after' => $updateAfter,
                    'sort_by' => 'updated_at',
                    'sort_direction' => 'ASC',
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

                if (isset($response['code']) && (string) $response['code'] !== '0') {
                    throw new \RuntimeException($response['message'] ?? 'Lazada getOrders failed.');
                }

                $data = $response['data'] ?? [];
                $orders = $data['orders'] ?? [];
                if ($orders === []) {
                    break;
                }

                foreach ($orders as $orderData) {
                    $orderId = $orderData['order_id'] ?? $orderData['order_number'] ?? null;
                    if ($orderId === null || $orderId === '') {
                        continue;
                    }
                    try {
                        $this->syncOrderById($store, (string) $orderId, $orderData);
                    } catch (\Exception $e) {
                        Log::warning('Lazada order sync item failed', [
                            'store_id' => $store->id,
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $count = (int) ($data['count'] ?? count($orders));
                $offset += count($orders);
                $total = (int) ($data['countTotal'] ?? $offset);
            } while ($offset < $total && count($orders) >= $limit && $count > 0);

            $store->update(['orders_synced_at' => $syncStartedAt]);
            $this->resyncStaleActionableOrders($store, 2);
        });
    }

    public function syncOrderById(ShopeeStore $store, string $orderId, ?array $listData = null): Order
    {
        return $this->runWithWorkspace($store->workspace_id, function () use ($store, $orderId, $listData) {
            $this->ensureFreshToken($store);
            $creds = $this->lazada->credentialsForStore($store);

            $orderResponse = $this->lazada->getOrder($creds['access_token'], $creds['region'], $orderId);
            if (isset($orderResponse['code']) && (string) $orderResponse['code'] !== '0') {
                throw new \RuntimeException($orderResponse['message'] ?? "Lazada getOrder failed for {$orderId}");
            }
            $orderData = $orderResponse['data'] ?? $orderResponse;
            if ($listData) {
                $orderData = array_merge($listData, is_array($orderData) ? $orderData : []);
            }

            $itemsResponse = $this->lazada->getOrderItems($creds['access_token'], $creds['region'], $orderId);
            if (isset($itemsResponse['code']) && (string) $itemsResponse['code'] !== '0') {
                throw new \RuntimeException($itemsResponse['message'] ?? "Lazada getOrderItems failed for {$orderId}");
            }
            $items = $itemsResponse['data'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            return $this->upsertLazadaOrder($store, $orderData, $items);
        });
    }

    /**
     * @param  list<array<string,mixed>>  $items
     */
    public function upsertLazadaOrder(ShopeeStore $store, array $orderData, array $items): Order
    {
        $orderId = (string) ($orderData['order_id'] ?? $orderData['order_number'] ?? '');
        if ($orderId === '') {
            throw new \InvalidArgumentException('Lazada order missing order_id.');
        }

        $existing = Order::where('store_id', $store->id)->where('order_sn', $orderId)->first();

        $statuses = [];
        foreach ($items as $item) {
            $st = strtolower((string) ($item['status'] ?? ''));
            if ($st !== '') {
                $statuses[] = $st;
            }
        }
        if ($statuses === [] && isset($orderData['statuses']) && is_array($orderData['statuses'])) {
            $statuses = array_map(fn ($s) => strtolower((string) $s), $orderData['statuses']);
        }
        $orderStatus = $this->deriveOrderStatus($statuses, $existing?->order_status);

        $createdAt = $this->parseLazadaDate($orderData['created_at'] ?? null);

        $existingShipBy = $existing?->ship_by_date
            ? $existing->ship_by_date->format('Y-m-d H:i:s')
            : null;
        $shipBy = $this->resolveShipByDate($orderData, $items, $existingShipBy);

        $packages = $this->extractPackagesFromItems($items);
        if ($packages === [] && is_array($existing?->raw_response['packages'] ?? null)) {
            $packages = $existing->raw_response['packages'];
        }

        $raw = [
            'order' => $orderData,
            'items' => $items,
            'packages' => $packages,
        ];
        if (is_array($existing?->raw_response['pack_response'] ?? null)) {
            $raw['pack_response'] = $existing->raw_response['pack_response'];
        }

        $attributes = [
            'order_status' => $orderStatus,
            'total_amount' => (float) ($orderData['price'] ?? $this->sumItemPrices($items)),
            'currency' => $store->region === 'my' ? 'MYR' : ($store->region === 'sg' ? 'SGD' : null),
            'buyer_username' => trim(($orderData['customer_first_name'] ?? '') . ' ' . ($orderData['customer_last_name'] ?? '')) ?: null,
            'shipping_address' => $this->formatAddress($orderData['address_shipping'] ?? null),
            'paid_time' => $createdAt,
            'ship_by_date' => $shipBy,
            'cancel_reason' => null,
            'raw_response' => $raw,
            'synced_at' => now(),
        ];
        if ($createdAt) {
            $attributes['ordered_at'] = $createdAt;
        }

        return DB::transaction(function () use ($store, $orderId, $attributes, $items, $raw, $existing) {
            $previousStatus = $existing?->order_status;
            $order = Order::updateOrCreate(
                ['store_id' => $store->id, 'order_sn' => $orderId],
                $attributes
            );
            $wasCreated = $order->wasRecentlyCreated;

            $seenItemIds = [];
            foreach ($items as $item) {
                $orderItemId = (int) ($item['order_item_id'] ?? 0);
                if ($orderItemId <= 0) {
                    continue;
                }
                $seenItemIds[] = $orderItemId;
                $productId = (int) ($item['product_id'] ?? $item['shop_id'] ?? $item['sku_id'] ?? $orderItemId);
                $sku = $item['sku'] ?? $item['seller_sku'] ?? $item['shop_sku'] ?? null;
                OrderItem::updateOrCreate(
                    ['order_id' => $order->id, 'model_id' => $orderItemId],
                    [
                        'item_id' => $productId,
                        'item_name' => $item['name'] ?? $item['product_main_image'] ?? "Item #{$orderItemId}",
                        'sku' => $sku,
                        'quantity' => 1,
                        'price' => (float) ($item['item_price'] ?? $item['paid_price'] ?? 0),
                    ]
                );
            }

            if ($seenItemIds !== []) {
                OrderItem::where('order_id', $order->id)
                    ->whereNotNull('model_id')
                    ->whereNotIn('model_id', $seenItemIds)
                    ->delete();
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
        });
    }

    public function cancelOrder(User $user, int $orderId, string $reasonId, ?string $reasonDetail = null): Order
    {
        $order = $this->getAccessibleLazadaOrder($user, $orderId);
        $store = $order->store;
        $this->ensureFreshToken($store);

        if (!in_array($order->order_status, ['pending', 'unpaid', 'packed'], true)) {
            abort(400, "Lazada order cannot be cancelled in status: {$order->order_status}");
        }

        $itemIds = $order->items->pluck('model_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($itemIds === []) {
            abort(422, 'Order items are unavailable. Sync the order and try again.');
        }

        $creds = $this->lazada->credentialsForStore($store);
        $this->lazada->cancelOrderItems(
            $creds['access_token'],
            $creds['region'],
            $order->order_sn,
            $itemIds,
            $reasonId,
            $reasonDetail
        );

        $order->update([
            'order_status' => 'canceled',
            'cancel_reason' => $reasonId,
            'synced_at' => now(),
        ]);

        try {
            $synced = $this->syncOrderById($store, $order->order_sn);
            $this->pickListPhotos->cleanupForOrder($synced);

            return $synced;
        } catch (\Exception) {
            $fresh = $order->fresh()->load(['items', 'store']);
            $this->pickListPhotos->cleanupForOrder($fresh);

            return $fresh;
        }
    }

    /**
     * @return array{order:Order,shipping_allocate_type:string,shipment_provider_code:?string,order_item_ids:list<int>,providers:array}
     */
    public function fulfillmentOptions(User $user, int $orderId): array
    {
        $order = $this->getAccessibleLazadaOrder($user, $orderId);
        $store = $order->store;
        $this->ensureFreshToken($store);

        $itemIds = $order->items->pluck('model_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($itemIds === []) {
            abort(422, 'No Lazada order item IDs available. Sync the order first.');
        }

        $creds = $this->lazada->credentialsForStore($store);
        $providersResponse = $this->lazada->getShipmentProviders(
            $creds['access_token'],
            $creds['region'],
            $order->order_sn,
            $itemIds
        );

        $result = $providersResponse['result'] ?? $providersResponse;
        $data = $result['data'] ?? [];
        $providers = $data['shipment_providers'] ?? [];
        $platformDefault = (int) ($data['platform_default'] ?? 1);
        $allocateType = 'TFS';
        $providerCode = null;
        if (is_array($providers) && $providers !== []) {
            $first = $providers[0];
            $allocateType = (string) ($first['shipping_allocate_type'] ?? 'TFS');
            $providerCode = $first['provider_code'] ?? null;
        }
        if ($platformDefault === 1) {
            $allocateType = $allocateType ?: 'TFS';
            $providerCode = null;
        }

        return [
            'order' => $order,
            'shipping_allocate_type' => $allocateType,
            'shipment_provider_code' => $providerCode,
            'order_item_ids' => $itemIds,
            'providers' => is_array($providers) ? $providers : [],
            'platform_default' => $platformDefault,
        ];
    }

    public function packOrder(User $user, int $orderId, ?string $shippingAllocateType = null, ?string $shipmentProviderCode = null): Order
    {
        $options = $this->fulfillmentOptions($user, $orderId);
        $order = $options['order'];
        $store = $order->store;

        if ($order->order_status !== 'pending') {
            abort(400, 'Only pending Lazada orders can be packed.');
        }

        $allocateType = $shippingAllocateType ?: $options['shipping_allocate_type'];
        $providerCode = $shipmentProviderCode ?: $options['shipment_provider_code'];

        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->packOrder(
            $creds['access_token'],
            $creds['region'],
            $order->order_sn,
            $options['order_item_ids'],
            $allocateType,
            $providerCode
        );

        $packages = $this->extractPackagesFromPackResponse($response);
        if ($packages === []) {
            throw new \RuntimeException('Lazada pack succeeded but returned no package_id.');
        }

        $raw = is_array($order->raw_response) ? $order->raw_response : [];
        $raw['pack_response'] = $response;
        $raw['packages'] = $packages;
        $order->update([
            'order_status' => 'packed',
            'raw_response' => $raw,
            'synced_at' => now(),
        ]);

        try {
            return $this->syncOrderById($store, $order->order_sn);
        } catch (\Exception) {
            return $order->fresh()->load(['items', 'store']);
        }
    }

    /**
     * @return array{pdf_url:?string,file:?string,doc_type:string,packages:list<string>}
     */
    public function printAwb(User $user, int $orderId): array
    {
        $order = $this->getAccessibleLazadaOrder($user, $orderId);
        $store = $order->store;
        $this->ensureFreshToken($store);

        $packageIds = $this->packageIdsForOrder($order);
        if ($packageIds === []) {
            abort(400, 'No package_id found. Pack the order before printing AWB.');
        }

        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->printAwb($creds['access_token'], $creds['region'], $packageIds, 'PDF');
        $result = $response['result'] ?? $response;
        $data = $result['data'] ?? [];

        return [
            'pdf_url' => $data['pdf_url'] ?? null,
            'file' => $data['file'] ?? null,
            'doc_type' => $data['doc_type'] ?? 'PDF',
            'packages' => $packageIds,
        ];
    }

    /**
     * Resolve Lazada AWB document to raw PDF bytes.
     *
     * @return array{pdf: string, order: Order, packages: list<string>}
     */
    public function downloadAwbPdf(User $user, int $orderId): array
    {
        $order = $this->getAccessibleLazadaOrder($user, $orderId);
        $status = strtolower((string) $order->order_status);
        if (!in_array($status, ['packed', 'ready_to_ship', 'shipped'], true)) {
            abort(400, 'Order must be packed, ready to ship, or shipped to print AWB.');
        }

        $doc = $this->printAwb($user, $orderId);
        $pdf = $this->resolveAwbPdfBytes($doc);
        if ($pdf === null) {
            abort(502, 'Lazada AWB document was empty.');
        }

        return [
            'pdf' => $pdf,
            'order' => $order->fresh()->load(['items', 'store']),
            'packages' => $doc['packages'],
        ];
    }

    /**
     * @param  array{pdf_url:?string,file:?string,doc_type?:string,packages?:list<string>}  $doc
     */
    public function resolveAwbPdfBytes(array $doc): ?string
    {
        if (!empty($doc['pdf_url'])) {
            $pdf = Http::timeout(60)->get($doc['pdf_url']);
            if ($pdf->successful() && str_starts_with($pdf->body(), '%PDF')) {
                return $pdf->body();
            }
        }

        if (!empty($doc['file'])) {
            $decoded = base64_decode($doc['file'], true);
            if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
                return $decoded;
            }
        }

        return null;
    }

    public function readyToShip(User $user, int $orderId): Order
    {
        $order = $this->getAccessibleLazadaOrder($user, $orderId);
        $store = $order->store;
        $this->ensureFreshToken($store);

        if (!in_array($order->order_status, ['packed', 'ready_to_ship'], true)) {
            abort(400, 'Order must be packed before marking ready to ship.');
        }

        $packageIds = $this->packageIdsForOrder($order);
        if ($packageIds === []) {
            abort(400, 'No package_id found. Pack the order first.');
        }

        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->readyToShip($creds['access_token'], $creds['region'], $packageIds);

        $raw = is_array($order->raw_response) ? $order->raw_response : [];
        $raw['rts_response'] = $response;
        $order->update([
            'order_status' => 'ready_to_ship',
            'raw_response' => $raw,
            'synced_at' => now(),
        ]);

        try {
            return $this->syncOrderById($store, $order->order_sn);
        } catch (\Exception) {
            return $order->fresh()->load(['items', 'store']);
        }
    }

    public function getAccessibleLazadaOrder(User $user, int $id): Order
    {
        $order = Order::with(['items', 'store'])->findOrFail($id);
        if (!$this->workspaceAccess->canAccessStore($user, $order->store)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Forbidden.');
        }
        if (($order->store->platform ?? '') !== 'lazada') {
            abort(400, 'This order is not a Lazada order.');
        }

        return $order;
    }

    /**
     * Ensure the store's Lazada access token is fresh, refreshing it when needed.
     *
     * Lazada rotates the refresh token on every use and invalidates the previous
     * one immediately, so the refresh is guarded by a per-store mutex: parallel
     * callers wait for the lock and then re-read the token row instead of burning
     * a second single-use refresh token.
     *
     * @throws \RuntimeException When the refresh token is expired, another refresh
     *                           is in progress, or the upstream refresh failed
     *                           (failure metadata is recorded on the token row).
     */
    public function ensureFreshToken(ShopeeStore $store): void
    {
        $store->loadMissing('token');
        if (!$store->token || $this->tokenIsFresh($store->token)) {
            return;
        }

        $lock = Cache::lock(
            "lazada:token-refresh:store_{$store->id}",
            self::TOKEN_REFRESH_LOCK_SECONDS
        );

        try {
            $lock->block(self::TOKEN_REFRESH_BLOCK_SECONDS, function () use ($store) {
                // Another request may have refreshed while we waited for the lock.
                $store->unsetRelation('token');
                $store->load('token');
                $token = $store->token;

                if (!$token || $this->tokenIsFresh($token)) {
                    return;
                }

                if ($token->refresh_token_expires_at && $token->refresh_token_expires_at->isPast()) {
                    throw new \RuntimeException("Store #{$store->id} Lazada refresh token expired. Please reconnect.");
                }

                $token->update(['last_refresh_attempt_at' => now()]);

                $refreshToken = decrypt($token->refresh_token);

                try {
                    $data = $this->runWithWorkspace($store->workspace_id, fn () => $this->lazada->refreshToken($refreshToken));
                } catch (\Throwable $e) {
                    $token->forceFill([
                        'last_refresh_failed_at' => now(),
                        'last_refresh_error' => mb_substr($e->getMessage(), 0, 255),
                    ]);
                    $token->refresh_failure_count = ((int) $token->refresh_failure_count) + 1;
                    $token->save();

                    throw $e;
                }

                $refreshExpiresIn = (int) ($data['refresh_expires_in'] ?? $data['refresh_token_expires_in'] ?? 15552000);

                $token->update([
                    'access_token' => encrypt($data['access_token']),
                    'refresh_token' => encrypt($data['refresh_token'] ?? $refreshToken),
                    'access_token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 2592000)),
                    'refresh_token_expires_at' => now()->addSeconds($refreshExpiresIn),
                    'auth_expires_at' => now()->addSeconds($refreshExpiresIn),
                    'last_refreshed_at' => now(),
                    'last_refresh_attempt_at' => now(),
                    'last_refresh_failed_at' => null,
                    'last_refresh_error' => null,
                    'refresh_failure_count' => 0,
                ]);

                $store->unsetRelation('token');
                $store->load('token');
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException(
                "Store #{$store->id} Lazada token refresh is already in progress. Please retry.",
                0,
                $e
            );
        }
    }

    /**
     * Lazada access tokens stay valid for ~30 days; treat 30 minutes as the buffer.
     */
    private function tokenIsFresh(ShopeeToken $token): bool
    {
        return $token->access_token_expires_at !== null
            && $token->access_token_expires_at->gt(now()->addMinutes(30));
    }

    private function refreshShopName(ShopeeStore $store, array $creds): void
    {
        $name = $this->lazada->resolveShopName($creds['access_token'], $creds['region']);
        if ($name === null || $name === $store->shop_name) {
            return;
        }
        $store->update(['shop_name' => $name]);
    }

    private function resolveUpdateAfter(ShopeeStore $store): string
    {
        // Ignore a poisoned watermark when this store has never successfully
        // persisted any orders (e.g. older product sync wrote orders_synced_at).
        $hasLocalOrders = Order::where('store_id', $store->id)->exists();
        if (!$store->orders_synced_at || !$hasLocalOrders) {
            return now()->subDays(self::MAX_LOOKBACK_DAYS)->toIso8601String();
        }
        $from = $store->orders_synced_at->copy()->subMinutes(self::WATERMARK_OVERLAP_MINUTES);
        $min = now()->subDays(self::MAX_LOOKBACK_DAYS);
        if ($from->lt($min)) {
            $from = $min;
        }

        return $from->toIso8601String();
    }

    /**
     * Re-fetch actionable Lazada orders older than the incremental watermark so
     * completed orders do not stay stuck as pending ("To pack") in MelurCom.
     *
     * @return array{attempted:int,updated:int,failed:int,errors:list<string>}
     */
    public function resyncStaleActionableOrders(ShopeeStore $store, int $olderThanDays = 2): array
    {
        if (($store->platform ?? '') !== 'lazada') {
            throw new \InvalidArgumentException("Store #{$store->id} is not a Lazada store.");
        }
        if ($olderThanDays < 1) {
            $olderThanDays = 1;
        }

        $this->ensureFreshToken($store);
        $cutoff = now()->subDays($olderThanDays);
        $orders = Order::query()
            ->where('store_id', $store->id)
            ->whereIn('order_status', self::ACTIONABLE_STATUSES)
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($inner) use ($cutoff) {
                    $inner->whereNotNull('ordered_at')->where('ordered_at', '<=', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    $inner->whereNull('ordered_at')
                        ->whereNotNull('synced_at')
                        ->where('synced_at', '<=', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    $inner->whereNull('ordered_at')
                        ->whereNull('synced_at')
                        ->where('created_at', '<=', $cutoff);
                });
            })
            ->orderBy('id')
            ->get();

        $attempted = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            $attempted++;
            $before = strtolower((string) $order->order_status);
            try {
                $fresh = $this->syncOrderById($store, $order->order_sn);
                if (strtolower((string) $fresh->order_status) !== $before) {
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "{$order->order_sn}: {$e->getMessage()}";
                Log::warning('Lazada stale order resync failed', [
                    'store_id' => $store->id,
                    'order_id' => $order->order_sn,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'attempted' => $attempted,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function deriveOrderStatus(array $statuses, ?string $existingStatus = null): string
    {
        if ($statuses === []) {
            // Do not regress an existing advanced status when the API omits statuses.
            return $this->normalizeStatus($existingStatus) ?? 'pending';
        }
        $best = null;
        $bestPriority = PHP_INT_MAX;
        foreach ($statuses as $status) {
            $normalized = $this->normalizeStatus((string) $status);
            if ($normalized === null) {
                continue;
            }
            $priority = self::STATUS_PRIORITY[$normalized] ?? 100;
            if ($priority < $bestPriority) {
                $bestPriority = $priority;
                $best = $normalized;
            }
        }

        return $best ?? ($this->normalizeStatus($existingStatus) ?? 'pending');
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'cancelled' => 'canceled',
            // Keep "confirmed" as-is: post-delivered buyer confirmation (MY/SG/ID).
            'failed_delivery' => 'failed',
            default => $normalized,
        };
    }

    /**
     * Earliest usable ship-by from order/item fields; keep existing when API sends none.
     *
     * @param  array<string,mixed>  $orderData
     * @param  list<array<string,mixed>>  $items
     */
    private function resolveShipByDate(array $orderData, array $items, ?string $existingShipBy): ?string
    {
        $candidates = [];
        $orderPromised = $orderData['promised_shipping_times'] ?? null;
        if ($orderPromised !== null && $orderPromised !== '') {
            $candidates[] = $orderPromised;
        }
        foreach ($items as $item) {
            foreach (['promised_shipping_time', 'sla_time_stamp'] as $key) {
                $value = $item[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        $parsed = [];
        foreach ($candidates as $candidate) {
            $date = $this->parseLazadaDate($candidate);
            if ($date !== null) {
                $parsed[] = $date;
            }
        }
        if ($parsed === []) {
            return $existingShipBy;
        }
        sort($parsed);

        return $parsed[0];
    }

    private function parseLazadaDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts <= 0) {
                return null;
            }
            if ($ts > 20000000000) {
                $ts = (int) floor($ts / 1000);
            }
            if ($ts <= 0) {
                return null;
            }

            return date('Y-m-d H:i:s', $ts);
        }
        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function formatAddress(mixed $address): ?string
    {
        if (!is_array($address)) {
            return null;
        }
        $parts = array_filter([
            trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
            $address['address1'] ?? null,
            $address['address2'] ?? null,
            $address['address3'] ?? null,
            $address['address4'] ?? null,
            $address['city'] ?? null,
            $address['post_code'] ?? null,
            $address['country'] ?? null,
            $address['phone'] ?? null,
        ], fn ($p) => $p !== null && $p !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  list<array<string,mixed>>  $items
     */
    private function sumItemPrices(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) ($item['item_price'] ?? $item['paid_price'] ?? 0);
        }

        return $sum;
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return list<array{package_id:string,tracking_number?:string,shipment_provider?:string}>
     */
    private function extractPackagesFromItems(array $items): array
    {
        $packages = [];
        foreach ($items as $item) {
            $packageId = $item['package_id'] ?? null;
            if (!$packageId) {
                continue;
            }
            $packages[(string) $packageId] = [
                'package_id' => (string) $packageId,
                'tracking_number' => $item['tracking_code'] ?? $item['tracking_number'] ?? null,
                'shipment_provider' => $item['shipment_provider'] ?? null,
            ];
        }

        return array_values($packages);
    }

    /**
     * @return list<array{package_id:string,tracking_number?:string,shipment_provider?:string,order_item_id?:int}>
     */
    private function extractPackagesFromPackResponse(array $response): array
    {
        $result = $response['result'] ?? $response;
        $packList = $result['data']['pack_order_list'] ?? [];
        $packages = [];
        foreach ($packList as $packOrder) {
            foreach ($packOrder['order_item_list'] ?? [] as $item) {
                if ((string) ($item['item_err_code'] ?? '0') !== '0') {
                    continue;
                }
                $packageId = $item['package_id'] ?? null;
                if (!$packageId) {
                    continue;
                }
                $packages[(string) $packageId] = [
                    'package_id' => (string) $packageId,
                    'tracking_number' => $item['tracking_number'] ?? null,
                    'shipment_provider' => $item['shipment_provider'] ?? null,
                    'order_item_id' => isset($item['order_item_id']) ? (int) $item['order_item_id'] : null,
                ];
            }
        }

        return array_values($packages);
    }

    /**
     * @return list<string>
     */
    private function packageIdsForOrder(Order $order): array
    {
        $raw = is_array($order->raw_response) ? $order->raw_response : [];
        $packages = $raw['packages'] ?? [];
        $ids = [];
        foreach ($packages as $package) {
            if (is_array($package) && !empty($package['package_id'])) {
                $ids[] = (string) $package['package_id'];
            } elseif (is_string($package) && $package !== '') {
                $ids[] = $package;
            }
        }

        return array_values(array_unique($ids));
    }
}
