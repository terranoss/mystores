<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LogisticsService;
use App\Services\OrderService;
use App\Services\WorkspaceActivityLogger;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly LogisticsService $logisticsService,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    /**
     * List orders for the user's stores.
     */
    public function index(Request $request): JsonResponse
    {
        $statusIn = $request->input('status_in');
        if (is_string($statusIn) && $statusIn !== '') {
            $statusIn = array_values(array_filter(array_map('trim', explode(',', $statusIn))));
        } elseif (!is_array($statusIn)) {
            $statusIn = null;
        }

        $storeId = $request->filled('store_id') ? $request->integer('store_id') : null;
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();
        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'desc')->toString();
        $urgency = $request->string('urgency')->toString();
        $orderedFrom = $request->string('ordered_from')->toString();
        $orderedTo = $request->string('ordered_to')->toString();
        $platform = $request->string('platform')->toString();

        $orders = $this->orderService->getOrders(
            $request->user(),
            $storeId,
            $status !== '' ? $status : null,
            $search !== '' ? $search : null,
            $sortBy !== '' ? $sortBy : null,
            $sortDir !== '' ? $sortDir : 'desc',
            $statusIn,
            $urgency !== '' ? $urgency : null,
            $orderedFrom !== '' ? $orderedFrom : null,
            $orderedTo !== '' ? $orderedTo : null,
            $platform !== '' ? $platform : null,
        );

        return response()->json($orders);
    }

    /**
     * Get a single order with items.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($request->user(), $id);

        return response()->json(['order' => $order]);
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancel_reason' => 'required|string|in:OUT_OF_STOCK,CUSTOMER_REQUEST,UNDELIVERABLE_AREA,COD_NOT_SUPPORTED',
        ]);

        $order = $this->orderService->cancelOrder(
            $request->user(),
            $id,
            $validated['cancel_reason']
        );

        $this->activityLogger->log(
            $request->user(),
            'order.cancel',
            "Cancelled order {$order->order_sn}",
            $order,
            ['cancel_reason' => $validated['cancel_reason'], 'order_sn' => $order->order_sn]
        );

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'order' => $order,
        ]);
    }

    /**
     * Manually trigger order sync for the user's stores.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:shopee,lazada',
        ]);
        $platform = $validated['platform'] ?? 'shopee';

        $results = $this->orderService->syncForUser($request->user(), $platform);

        $this->activityLogger->log(
            $request->user(),
            'order.sync',
            $platform === 'lazada' ? 'Synced orders from Lazada' : 'Synced orders from Shopee',
            null,
            ['platform' => $platform, 'results_count' => count($results)]
        );

        return response()->json([
            'message' => 'Sync completed.',
            'results' => $results,
        ]);
    }

    /**
     * Get shipping parameters (pickup / dropoff options) for a READY_TO_SHIP order.
     */
    public function shippingParameters(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($request->user(), $id);

        if ($order->order_status !== 'READY_TO_SHIP') {
            abort(400, 'Order must be in READY_TO_SHIP status to fetch shipping parameters.');
        }

        $store = $order->store;
        $store->loadMissing('token');

        if (!$store->token || !$store->is_active) {
            abort(400, 'Store token missing or inactive.');
        }

        try {
            $params = $this->logisticsService->getShippingParameter($store, $order->order_sn);
        } catch (ShopeeException $e) {
            abort(400, $e->getMessage() ?: 'Failed to load shipping parameters.');
        }

        return response()->json(['params' => $params]);
    }

    /**
     * Arrange shipment for a READY_TO_SHIP order.
     * Body must contain exactly one of: pickup, dropoff, or non_integrated.
     * Empty objects (e.g. dropoff: {}) are valid when Shopee info_needed lists that mode with no fields.
     */
    public function ship(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'pickup'         => 'sometimes|array',
            'dropoff'        => 'sometimes|array',
            'non_integrated' => 'sometimes|array',
        ]);

        $modes = array_values(array_intersect(
            array_keys($validated),
            ['pickup', 'dropoff', 'non_integrated']
        ));
        if (count($modes) !== 1) {
            abort(422, 'Exactly one of pickup, dropoff, or non_integrated must be provided.');
        }

        $order = $this->orderService->getOrder($request->user(), $id);

        if ($order->order_status !== 'READY_TO_SHIP') {
            abort(400, 'Order must be in READY_TO_SHIP status to arrange shipment.');
        }

        $store = $order->store;
        $store->loadMissing('token');

        if (!$store->token || !$store->is_active) {
            abort(400, 'Store token missing or inactive.');
        }

        try {
            $this->logisticsService->shipOrder($store, $order->order_sn, $validated);
        } catch (ShopeeException $e) {
            abort(400, $e->getMessage() ?: 'Failed to arrange shipment.');
        }

        $updatedOrder = $this->orderService->syncAfterShip($store, $order->order_sn);

        $this->activityLogger->log(
            $request->user(),
            'order.ship',
            "Arranged shipment for order {$order->order_sn}",
            $updatedOrder,
            ['order_sn' => $order->order_sn, 'mode' => $modes[0]]
        );

        return response()->json([
            'message' => 'Shipment arranged successfully.',
            'order'   => $updatedOrder->load(['items', 'store:id,shop_name,shop_id,region']),
        ]);
    }

    /**
     * Return Air Waybill + Packing List PDFs (base64) for a PROCESSED or SHIPPED order.
     * Frontend merges them with pdf-lib (Shopee AWBs are not parseable by free FPDI).
     */
    public function shippingLabel(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->getOrder($request->user(), $id);
        $order->loadMissing(['items', 'store']);

        if (!in_array($order->order_status, ['PROCESSED', 'SHIPPED'])) {
            abort(400, 'Order must be PROCESSED or SHIPPED to print a shipping label.');
        }

        $store = $order->store;
        $store->loadMissing('token');

        if (!$store->token || !$store->is_active) {
            abort(400, 'Store token missing or inactive.');
        }

        $docs = $this->logisticsService->downloadShippingLabel($store, $order);

        $this->activityLogger->log(
            $request->user(),
            'order.print_label',
            "Printed shipping label for order {$order->order_sn}",
            $order,
            ['order_sn' => $order->order_sn]
        );

        return response()->json([
            'awb_pdf' => base64_encode($docs['awb']),
            'packing_list_pdf' => base64_encode($docs['packing_list']),
            'filename' => 'awb-packing-' . $order->order_sn . '.pdf',
        ]);
    }

    /**
     * Bulk Air Waybill + Packing List PDFs for selected orders.
     * Processes sequentially; returns successes and per-order errors.
     */
    public function shippingLabelsBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => 'required|array|min:1|max:15',
            'order_ids.*' => 'integer|distinct',
        ]);

        set_time_limit(180);

        $user = $request->user();
        $orderIds = array_map('intval', $validated['order_ids']);
        $documents = [];
        $errors = [];

        foreach ($orderIds as $orderId) {
            $order = null;
            try {
                $order = $this->orderService->getOrder($user, $orderId);
                $order->loadMissing(['items', 'store.token']);

                if (!in_array($order->order_status, ['PROCESSED', 'SHIPPED'], true)) {
                    $errors[] = [
                        'order_id' => $order->id,
                        'order_sn' => $order->order_sn,
                        'message' => 'Order must be PROCESSED or SHIPPED to print a shipping label.',
                    ];
                    continue;
                }

                $store = $order->store;
                if (!$store->token || !$store->is_active) {
                    $errors[] = [
                        'order_id' => $order->id,
                        'order_sn' => $order->order_sn,
                        'message' => 'Store token missing or inactive.',
                    ];
                    continue;
                }

                $docs = $this->logisticsService->downloadShippingLabel($store, $order);
                $documents[] = [
                    'order_id' => $order->id,
                    'order_sn' => $order->order_sn,
                    'awb_pdf' => base64_encode($docs['awb']),
                    'packing_list_pdf' => base64_encode($docs['packing_list']),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'order_sn' => $order?->order_sn,
                    'message' => $e->getMessage() ?: 'Failed to generate shipping documents.',
                ];
            }
        }

        if ($documents !== []) {
            $sns = array_column($documents, 'order_sn');
            $this->activityLogger->log(
                $user,
                'order.print_label_bulk',
                'Printed shipping labels for '.count($documents).' order(s)',
                null,
                ['order_sns' => $sns, 'error_count' => count($errors)]
            );
        }

        return response()->json([
            'documents' => $documents,
            'errors' => $errors,
            'filename' => 'awb-packing-bulk.pdf',
        ]);
    }
}
