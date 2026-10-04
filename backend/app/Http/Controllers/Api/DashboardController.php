<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PickListComment;
use App\Models\PickListCommentRead;
use App\Models\PickListCompletion;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Services\WorkspaceAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const CANCELLED_STATUSES = [
        'CANCELLED',
        'CANCELED',
        'cancelled',
        'canceled',
    ];

    /**
     * Seller-actionable fulfillment statuses for the Pending Orders card.
     * Excludes completed terminal states (delivered, confirmed, COMPLETED, SHIPPED, etc.).
     *
     * @var list<string>
     */
    private const PENDING_ORDER_STATUSES = [
        'unpaid',
        'pending',
        'packed',
        'ready_to_ship',
        'processed',
    ];

    /** @var list<int> */
    private const ALLOWED_TREND_DAYS = [7, 14, 30];

    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
    ) {}

    public function index(): JsonResponse
    {
        $user = request()->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if ($workspaceIds === []) {
            $workspaceIds = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }

        $marketplace = request()->query('marketplace');
        $storeId = request()->query('store_id') ? (int) request()->query('store_id') : null;

        $storesQuery = ShopeeStore::whereIn('workspace_id', $workspaceIds);
        if (in_array($marketplace, ['shopee', 'lazada'], true)) {
            $storesQuery->where('platform', $marketplace);
        }
        if ($storeId) {
            $storesQuery->where('id', $storeId);
        }
        $storesCount = $storesQuery->count();

        $trendDays = $this->resolveTrendDays(request()->query('trend_days'));

        // Use Malaysia timezone (UTC+8) for "today" so orders placed at e.g. 1 AM MYT
        // are counted correctly regardless of the server's UTC clock.
        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $todayStart = Carbon::today($tz)->utc();
        $todayEnd = Carbon::today($tz)->endOfDay()->utc();
        $yesterdayStart = Carbon::today($tz)->subDay()->utc();
        $yesterdayEnd = Carbon::today($tz)->subDay()->endOfDay()->utc();
        $periodStart = Carbon::today($tz)->subDays($trendDays - 1)->startOfDay()->utc();
        $priorStart = Carbon::today($tz)->subDays(($trendDays * 2) - 1)->startOfDay()->utc();
        $priorEnd = Carbon::today($tz)->subDays($trendDays)->endOfDay()->utc();

        $workspaceOrders = fn (Builder $q) => $q->whereHas(
            'store',
            function ($store) use ($workspaceIds, $marketplace, $storeId) {
                $store->whereIn('workspace_id', $workspaceIds);
                if (in_array($marketplace, ['shopee', 'lazada'], true)) {
                    $store->where('platform', $marketplace);
                }
                if ($storeId) {
                    $store->where('id', $storeId);
                }
            }
        );

        $ordersTodayQuery = Order::query()
            ->tap($workspaceOrders)
            ->where(function ($query) use ($todayStart, $todayEnd) {
                $this->applyOrderedBetween($query, $todayStart, $todayEnd);
            });

        $pendingOrdersQuery = Order::query()
            ->tap($workspaceOrders)
            ->whereRaw('LOWER(order_status) in ('.implode(',', array_fill(0, count(self::PENDING_ORDER_STATUSES), '?')).')', self::PENDING_ORDER_STATUSES)
            ->where(function ($query) use ($todayStart) {
                $this->applyOrderedBefore($query, $todayStart);
            });

        $gmvTodayQuery = (clone $ordersTodayQuery)->tap(fn (Builder $q) => $this->excludeCancelled($q));

        $ordersToday = (clone $ordersTodayQuery)->count();
        $pendingOrdersCount = (clone $pendingOrdersQuery)->count();
        $ordersTodayByStore = $this->ordersByStore($ordersTodayQuery);
        $pendingOrdersByStore = $this->ordersByStore($pendingOrdersQuery);
        $gmvToday = round((float) (clone $gmvTodayQuery)->sum('total_amount'), 2);
        $gmvTodayByStore = $this->gmvByStore($gmvTodayQuery);

        $salesRows = $this->fetchNonCancelledOrders(
            $workspaceIds,
            $priorStart,
            $todayEnd,
            $marketplace,
            $storeId
        );
        $salesTrend = $this->buildSalesTrend($salesRows, $tz, $trendDays);
        $salesGrowth = $this->buildSalesGrowth(
            $salesRows,
            $todayStart,
            $todayEnd,
            $yesterdayStart,
            $yesterdayEnd,
            $periodStart,
            $todayEnd,
            $priorStart,
            $priorEnd,
        );

        $cancelReasons = $this->buildCancelReasonBreakdown(
            $workspaceIds,
            $periodStart,
            $todayEnd,
            $marketplace,
            $storeId
        );
        $cancelReasonsTotal = array_sum(array_column($cancelReasons, 'count'));

        $lowStockItems = Product::whereHas('store', function ($q) use ($workspaceIds, $marketplace, $storeId) {
            $q->whereIn('workspace_id', $workspaceIds);
            if (in_array($marketplace, ['shopee', 'lazada'], true)) {
                $q->where('platform', $marketplace);
            }
            if ($storeId) {
                $q->where('id', $storeId);
            }
        })
            ->where('stock', '>', 0)
            ->where('stock', '<=', 5)
            ->where('has_variants', false)
            ->count();

        $workspace = $this->workspaceAccess->primaryWorkspace($user);
        $role = $workspace
            ? $this->workspaceAccess->roleInWorkspace($user, $workspace)
            : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'stats' => [
                'stores_count' => $storesCount,
                'orders_today' => $ordersToday,
                'orders_today_by_store' => $ordersTodayByStore,
                'gmv_today' => $gmvToday,
                'gmv_today_by_store' => $gmvTodayByStore,
                'pending_orders_count' => $pendingOrdersCount,
                'pending_orders_by_store' => $pendingOrdersByStore,
                'trend_days' => $trendDays,
                'sales_trend' => $salesTrend,
                'sales_growth' => $salesGrowth,
                'cancel_reasons' => $cancelReasons,
                'cancel_reasons_total' => $cancelReasonsTotal,
                'low_stock_items' => $lowStockItems,
            ],
            'workspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'role' => $role,
            ] : null,
        ]);
    }

    public function todayPickList(): JsonResponse
    {
        $user = request()->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if ($workspaceIds === []) {
            $workspaceIds = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $todayStart = Carbon::today($tz)->utc();
        $todayEnd = Carbon::today($tz)->endOfDay()->utc();

        $orders = Order::query()
            ->whereHas('store', fn ($q) => $q->whereIn('workspace_id', $workspaceIds))
            ->tap(fn (Builder $q) => $this->excludeCancelled($q))
            ->where(function ($query) use ($todayStart, $todayEnd) {
                $this->applyOrderedBetween($query, $todayStart, $todayEnd);
            })
            ->with(['store:id,shop_name,platform', 'items'])
            ->orderByDesc('ordered_at')
            ->get();

        return $this->buildPickListResponse($orders, PickListCompletion::KIND_TODAY, $user);
    }

    public function pendingPickList(): JsonResponse
    {
        $user = request()->user();
        $workspaceIds = $this->workspaceAccess->workspaceIdsFor($user);
        if ($workspaceIds === []) {
            $workspaceIds = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $todayStart = Carbon::today($tz)->utc();

        $orders = Order::query()
            ->whereHas('store', fn ($q) => $q->whereIn('workspace_id', $workspaceIds))
            ->whereRaw('LOWER(order_status) in ('.implode(',', array_fill(0, count(self::PENDING_ORDER_STATUSES), '?')).')', self::PENDING_ORDER_STATUSES)
            ->where(function ($query) use ($todayStart) {
                $this->applyOrderedBefore($query, $todayStart);
            })
            ->with(['store:id,shop_name,platform', 'items'])
            ->orderByDesc('ordered_at')
            ->get();

        return $this->buildPickListResponse($orders, PickListCompletion::KIND_PENDING, $user);
    }

    /**
     * @param  \App\Models\User  $user
     */
    private function buildPickListResponse(Collection $orders, string $kind, $user): JsonResponse
    {
        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $pickDate = Carbon::today($tz)->toDateString();
        $aggregatedMap = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = PickListCompletion::makeItemKey($item->item_id, $item->model_id, $item->item_name);
                if (! isset($aggregatedMap[$key])) {
                    $aggregatedMap[$key] = [
                        'item_key' => $key,
                        'item_id' => $item->item_id,
                        'model_id' => $item->model_id,
                        'item_name' => $item->item_name,
                        'quantity' => 0,
                        'store_names' => [],
                        'platforms' => [],
                        'completion' => null,
                    ];
                }
                $aggregatedMap[$key]['quantity'] += (int) $item->quantity;
                $storeName = $order->store?->shop_name ?: ('Store #'.$order->store_id);
                $platform = $order->store?->platform ?: 'shopee';

                if (! in_array($storeName, $aggregatedMap[$key]['store_names'], true)) {
                    $aggregatedMap[$key]['store_names'][] = $storeName;
                }
                if (! in_array($platform, $aggregatedMap[$key]['platforms'], true)) {
                    $aggregatedMap[$key]['platforms'][] = $platform;
                }
            }
        }

        $workspace = $this->workspaceAccess->primaryWorkspace($user)
            ?? $this->workspaceAccess->ensurePersonalWorkspace($user);
        $completions = PickListCompletion::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', $kind)
            ->whereDate('pick_date', $pickDate)
            ->with('picker:id,name')
            ->get()
            ->keyBy('item_key');

        $commentsByKey = PickListComment::query()
            ->where('workspace_id', $workspace->id)
            ->where('kind', $kind)
            ->whereDate('pick_date', $pickDate)
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->groupBy('item_key');

        $readsByKey = PickListCommentRead::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->whereDate('pick_date', $pickDate)
            ->get()
            ->keyBy('item_key');

        foreach ($aggregatedMap as $key => &$row) {
            $completion = $completions->get($key);
            $row['completion'] = $completion
                ? PickListCompletionController::completionPayload($completion)
                : null;
            $itemComments = $commentsByKey->get($key, collect());
            $capped = $itemComments->take(-50)->values();
            $row['comments'] = $capped->map(fn (PickListComment $c) => $c->toApiPayload())->all();
            $row['comment_count'] = $itemComments->count();

            $lastReadAt = $readsByKey->get($key)?->last_read_at;
            $unreadFromOthers = $itemComments->filter(function (PickListComment $c) use ($user, $lastReadAt) {
                if ((int) $c->user_id === (int) $user->id) {
                    return false;
                }
                if ($lastReadAt === null) {
                    return true;
                }

                return $c->created_at !== null && $c->created_at->gt($lastReadAt);
            });
            $row['unread_comment_count'] = $unreadFromOthers->count();
            $row['has_unread_comments'] = $row['unread_comment_count'] > 0;
        }
        unset($row);

        $aggregatedItems = array_values($aggregatedMap);
        usort($aggregatedItems, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

        $orderBreakdown = $orders->map(function (Order $order) {
            return [
                'id' => $order->id,
                'order_sn' => $order->order_sn,
                'order_status' => $order->order_status,
                'buyer_username' => $order->buyer_username,
                'platform' => $order->store?->platform ?: 'shopee',
                'shop_name' => $order->store?->shop_name ?: ('Store #'.$order->store_id),
                'ordered_at' => $order->ordered_at?->toIso8601String() ?: $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'item_name' => $item->item_name,
                    'item_id' => $item->item_id,
                    'model_id' => $item->model_id,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                ])->values()->all(),
            ];
        })->values()->all();

        $totalUnits = array_reduce($aggregatedItems, fn ($sum, $i) => $sum + $i['quantity'], 0);

        return response()->json([
            'date' => Carbon::now($tz)->toDateString(),
            'generated_at' => Carbon::now($tz)->toIso8601String(),
            'total_orders' => $orders->count(),
            'total_skus' => count($aggregatedItems),
            'total_units' => $totalUnits,
            'aggregated_items' => $aggregatedItems,
            'orders' => $orderBreakdown,
        ]);
    }

    private function resolveTrendDays(mixed $raw): int
    {
        $days = (int) $raw;
        if (! in_array($days, self::ALLOWED_TREND_DAYS, true)) {
            return 7;
        }

        return $days;
    }

    private function applyOrderedBetween(Builder $query, Carbon $start, Carbon $end): void
    {
        $query->where(function ($inner) use ($start, $end) {
            $inner->whereBetween('ordered_at', [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->whereNull('ordered_at')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    private function applyOrderedBefore(Builder $query, Carbon $before): void
    {
        $query->where(function ($inner) use ($before) {
            $inner->where('ordered_at', '<', $before)
                ->orWhere(function ($q) use ($before) {
                    $q->whereNull('ordered_at')
                        ->where('created_at', '<', $before);
                });
        });
    }

    private function excludeCancelled(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('order_status')
                ->orWhereNotIn('order_status', self::CANCELLED_STATUSES);
        });
    }

    /**
     * @param  list<int>  $workspaceIds
     * @return list<array{reason:string,label:string,count:int}>
     */
    private function buildCancelReasonBreakdown(
        array $workspaceIds,
        Carbon $rangeStartUtc,
        Carbon $rangeEndUtc,
        ?string $marketplace = null,
        ?int $storeId = null
    ): array {
        $rows = Order::query()
            ->whereHas('store', function ($q) use ($workspaceIds, $marketplace, $storeId) {
                $q->whereIn('workspace_id', $workspaceIds);
                if (in_array($marketplace, ['shopee', 'lazada'], true)) {
                    $q->where('platform', $marketplace);
                }
                if ($storeId) {
                    $q->where('id', $storeId);
                }
            })
            ->whereIn('order_status', self::CANCELLED_STATUSES)
            ->where(function ($query) use ($rangeStartUtc, $rangeEndUtc) {
                $this->applyOrderedBetween($query, $rangeStartUtc, $rangeEndUtc);
            })
            ->get(['cancel_reason']);

        $counts = [];
        foreach ($rows as $row) {
            $raw = is_string($row->cancel_reason) ? trim($row->cancel_reason) : '';
            $reason = $raw === '' ? 'Unknown' : $raw;
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        arsort($counts);

        $result = [];
        foreach ($counts as $reason => $count) {
            $result[] = [
                'reason' => $reason,
                'label' => $this->cancelReasonLabel($reason),
                'count' => (int) $count,
            ];
        }

        return $result;
    }

    private function cancelReasonLabel(string $reason): string
    {
        $map = [
            'Unknown' => 'Unknown',
            'OUT_OF_STOCK' => 'Out of Stock',
            'CUSTOMER_REQUEST' => 'Customer Request',
            'UNDELIVERABLE_AREA' => 'Undeliverable Area',
            'COD_NOT_SUPPORTED' => 'COD Not Supported',
            'Out of stock' => 'Out of stock',
            'Wrong price' => 'Wrong price',
            'Seller out of stock' => 'Seller out of stock',
            'Unable to deliver' => 'Unable to deliver',
        ];

        if (isset($map[$reason])) {
            return $map[$reason];
        }

        return str_replace('_', ' ', $reason);
    }

    /**
     * @param  list<int>  $workspaceIds
     * @return Collection<int, Order>
     */
    private function fetchNonCancelledOrders(
        array $workspaceIds,
        Carbon $rangeStartUtc,
        Carbon $rangeEndUtc,
        ?string $marketplace = null,
        ?int $storeId = null
    ): Collection {
        return Order::query()
            ->whereHas('store', function ($q) use ($workspaceIds, $marketplace, $storeId) {
                $q->whereIn('workspace_id', $workspaceIds);
                if (in_array($marketplace, ['shopee', 'lazada'], true)) {
                    $q->where('platform', $marketplace);
                }
                if ($storeId) {
                    $q->where('id', $storeId);
                }
            })
            ->tap(fn (Builder $q) => $this->excludeCancelled($q))
            ->where(function ($query) use ($rangeStartUtc, $rangeEndUtc) {
                $this->applyOrderedBetween($query, $rangeStartUtc, $rangeEndUtc);
            })
            ->get(['store_id', 'ordered_at', 'created_at', 'total_amount']);
    }

    /**
     * @return list<array{store_id:int,shop_name:?string,platform:string,count:int}>
     */
    private function ordersByStore(Builder $query): array
    {
        $rows = (clone $query)
            ->select('store_id', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('store_id')
            ->orderByDesc('aggregate_count')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $stores = ShopeeStore::whereIn('id', $rows->pluck('store_id'))
            ->get(['id', 'shop_name', 'platform'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($stores) {
            $store = $stores->get($row->store_id);

            return [
                'store_id' => (int) $row->store_id,
                'shop_name' => $store?->shop_name,
                'platform' => $store?->platform ?: 'shopee',
                'count' => (int) $row->aggregate_count,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{store_id:int,shop_name:?string,platform:string,count:int,gmv:float}>
     */
    private function gmvByStore(Builder $query): array
    {
        $rows = (clone $query)
            ->select(
                'store_id',
                DB::raw('COUNT(*) as aggregate_count'),
                DB::raw('SUM(total_amount) as aggregate_gmv')
            )
            ->groupBy('store_id')
            ->orderByDesc('aggregate_gmv')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $stores = ShopeeStore::whereIn('id', $rows->pluck('store_id'))
            ->get(['id', 'shop_name', 'platform'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($stores) {
            $store = $stores->get($row->store_id);

            return [
                'store_id' => (int) $row->store_id,
                'shop_name' => $store?->shop_name,
                'platform' => $store?->platform ?: 'shopee',
                'count' => (int) $row->aggregate_count,
                'gmv' => round((float) $row->aggregate_gmv, 2),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Order>  $rows
     * @return list<array{date:string,orders:int,gmv:float,by_store:list<array{store_id:int,shop_name:?string,platform:string,orders:int,gmv:float}>}>
     */
    private function buildSalesTrend(Collection $rows, string $tz, int $days): array
    {
        $buckets = [];
        $cursor = Carbon::today($tz)->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->toDateString();
            $buckets[$key] = [
                'date' => $key,
                'orders' => 0,
                'gmv' => 0.0,
                'stores' => [],
            ];
            $cursor = $cursor->copy()->addDay();
        }

        foreach ($rows as $row) {
            $at = $row->ordered_at ?? $row->created_at;
            if (! $at) {
                continue;
            }
            $day = Carbon::parse($at)->timezone($tz)->toDateString();
            if (! isset($buckets[$day])) {
                continue;
            }
            $storeId = (int) $row->store_id;
            $amount = (float) $row->total_amount;
            $buckets[$day]['orders']++;
            $buckets[$day]['gmv'] += $amount;
            if (! isset($buckets[$day]['stores'][$storeId])) {
                $buckets[$day]['stores'][$storeId] = ['orders' => 0, 'gmv' => 0.0];
            }
            $buckets[$day]['stores'][$storeId]['orders']++;
            $buckets[$day]['stores'][$storeId]['gmv'] += $amount;
        }

        $storeIds = collect($buckets)
            ->flatMap(fn ($b) => array_keys($b['stores']))
            ->unique()
            ->values()
            ->all();
        $stores = $storeIds === []
            ? collect()
            : ShopeeStore::whereIn('id', $storeIds)->get(['id', 'shop_name', 'platform'])->keyBy('id');

        return array_values(array_map(function (array $bucket) use ($stores) {
            $byStore = collect($bucket['stores'])
                ->map(function ($agg, $storeId) use ($stores) {
                    $store = $stores->get((int) $storeId);

                    return [
                        'store_id' => (int) $storeId,
                        'shop_name' => $store?->shop_name,
                        'platform' => $store?->platform ?: 'shopee',
                        'orders' => (int) $agg['orders'],
                        'gmv' => round((float) $agg['gmv'], 2),
                    ];
                })
                ->sortByDesc('gmv')
                ->values()
                ->all();

            return [
                'date' => $bucket['date'],
                'orders' => (int) $bucket['orders'],
                'gmv' => round((float) $bucket['gmv'], 2),
                'by_store' => $byStore,
            ];
        }, $buckets));
    }

    /**
     * @param  Collection<int, Order>  $rows
     * @return array{
     *   today_vs_yesterday: array{sales_pct: ?float, orders_delta: int, sales_today: float, sales_yesterday: float, orders_today: int, orders_yesterday: int},
     *   period_vs_prior: array{sales_pct: ?float, orders_delta: int, sales_period: float, sales_prior: float, orders_period: int, orders_prior: int}
     * }
     */
    private function buildSalesGrowth(
        Collection $rows,
        Carbon $todayStart,
        Carbon $todayEnd,
        Carbon $yesterdayStart,
        Carbon $yesterdayEnd,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $priorStart,
        Carbon $priorEnd,
    ): array {
        $today = $this->sumOrdersInUtcRange($rows, $todayStart, $todayEnd);
        $yesterday = $this->sumOrdersInUtcRange($rows, $yesterdayStart, $yesterdayEnd);
        $period = $this->sumOrdersInUtcRange($rows, $periodStart, $periodEnd);
        $prior = $this->sumOrdersInUtcRange($rows, $priorStart, $priorEnd);

        return [
            'today_vs_yesterday' => [
                'sales_pct' => $this->pctChange($today['sales'], $yesterday['sales']),
                'orders_delta' => $today['orders'] - $yesterday['orders'],
                'sales_today' => $today['sales'],
                'sales_yesterday' => $yesterday['sales'],
                'orders_today' => $today['orders'],
                'orders_yesterday' => $yesterday['orders'],
            ],
            'period_vs_prior' => [
                'sales_pct' => $this->pctChange($period['sales'], $prior['sales']),
                'orders_delta' => $period['orders'] - $prior['orders'],
                'sales_period' => $period['sales'],
                'sales_prior' => $prior['sales'],
                'orders_period' => $period['orders'],
                'orders_prior' => $prior['orders'],
            ],
        ];
    }

    /**
     * @param  Collection<int, Order>  $rows
     * @return array{orders:int,sales:float}
     */
    private function sumOrdersInUtcRange(Collection $rows, Carbon $startUtc, Carbon $endUtc): array
    {
        $orders = 0;
        $sales = 0.0;
        foreach ($rows as $row) {
            $at = $row->ordered_at ?? $row->created_at;
            if (! $at) {
                continue;
            }
            $carbon = Carbon::parse($at);
            if ($carbon->lt($startUtc) || $carbon->gt($endUtc)) {
                continue;
            }
            $orders++;
            $sales += (float) $row->total_amount;
        }

        return [
            'orders' => $orders,
            'sales' => round($sales, 2),
        ];
    }

    private function pctChange(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
