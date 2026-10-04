<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LazadaOrderService;
use App\Services\OrderService;
use App\Services\PackingListPdfService;
use App\Services\WorkspaceActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LazadaOrderController extends Controller
{
    public function __construct(
        private readonly LazadaOrderService $lazadaOrders,
        private readonly OrderService $orderService,
        private readonly PackingListPdfService $packingListPdf,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    public function sync(Request $request): JsonResponse
    {
        $results = $this->orderService->syncForUser($request->user(), 'lazada');

        $this->activityLogger->log(
            $request->user(),
            'order.sync',
            'Synced orders from Lazada',
            null,
            ['platform' => 'lazada', 'results_count' => count($results)]
        );

        return response()->json([
            'message' => 'Lazada sync completed.',
            'results' => $results,
        ]);
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'reason_id' => 'required|string|max:100',
            'reason_detail' => 'nullable|string|max:500',
        ]);

        $updated = $this->lazadaOrders->cancelOrder(
            $request->user(),
            $order,
            $validated['reason_id'],
            $validated['reason_detail'] ?? null
        );

        $this->activityLogger->log(
            $request->user(),
            'order.cancel',
            "Cancelled Lazada order {$updated->order_sn}",
            $updated,
            ['platform' => 'lazada', 'reason_id' => $validated['reason_id'], 'order_sn' => $updated->order_sn]
        );

        return response()->json([
            'message' => 'Lazada order cancelled successfully.',
            'order' => $updated,
        ]);
    }

    public function fulfillmentOptions(Request $request, int $order): JsonResponse
    {
        $options = $this->lazadaOrders->fulfillmentOptions($request->user(), $order);

        return response()->json([
            'shipping_allocate_type' => $options['shipping_allocate_type'],
            'shipment_provider_code' => $options['shipment_provider_code'],
            'order_item_ids' => $options['order_item_ids'],
            'providers' => $options['providers'],
            'platform_default' => $options['platform_default'],
            'order' => $options['order'],
        ]);
    }

    public function pack(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'shipping_allocate_type' => 'nullable|string|max:50',
            'shipment_provider_code' => 'nullable|string|max:100',
        ]);

        $updated = $this->lazadaOrders->packOrder(
            $request->user(),
            $order,
            $validated['shipping_allocate_type'] ?? null,
            $validated['shipment_provider_code'] ?? null
        );

        $this->activityLogger->log(
            $request->user(),
            'order.pack',
            "Packed Lazada order {$updated->order_sn}",
            $updated,
            ['platform' => 'lazada', 'order_sn' => $updated->order_sn]
        );

        return response()->json([
            'message' => 'Lazada order packed successfully.',
            'order' => $updated,
        ]);
    }

    public function awb(Request $request, int $order)
    {
        $result = $this->lazadaOrders->downloadAwbPdf($request->user(), $order);
        $orderModel = $result['order'];

        if ($request->query('raw') || (! $request->wantsJson() && $request->header('Accept') === 'application/pdf')) {
            $this->activityLogger->log(
                $request->user(),
                'order.print_label',
                'Printed Lazada AWB',
                $orderModel,
                ['platform' => 'lazada', 'order_id' => $order, 'packages' => $result['packages']]
            );

            return response($result['pdf'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="lazada-awb-'.$order.'.pdf"',
            ]);
        }

        $packingList = $this->packingListPdf->generate($orderModel);

        $this->activityLogger->log(
            $request->user(),
            'order.print_label',
            'Printed Lazada AWB + Packing List',
            $orderModel,
            ['platform' => 'lazada', 'order_id' => $order, 'packages' => $result['packages']]
        );

        return response()->json([
            'awb_pdf' => base64_encode($result['pdf']),
            'packing_list_pdf' => base64_encode($packingList),
            'filename' => 'lazada-awb-packing-'.$orderModel->order_sn.'.pdf',
        ]);
    }

    public function awbBulk(Request $request): JsonResponse
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
            $orderSn = null;
            try {
                $result = $this->lazadaOrders->downloadAwbPdf($user, $orderId);
                $order = $result['order'];
                $orderSn = $order->order_sn;
                $packingList = $this->packingListPdf->generate($order);
                $documents[] = [
                    'order_id' => $order->id,
                    'order_sn' => $order->order_sn,
                    'awb_pdf' => base64_encode($result['pdf']),
                    'packing_list_pdf' => base64_encode($packingList),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'order_sn' => $orderSn,
                    'message' => $e->getMessage() ?: 'Failed to generate Lazada shipping documents.',
                ];
            }
        }

        if ($documents !== []) {
            $this->activityLogger->log(
                $user,
                'order.print_label_bulk',
                'Printed Lazada AWBs for '.count($documents).' order(s)',
                null,
                [
                    'platform' => 'lazada',
                    'order_sns' => array_column($documents, 'order_sn'),
                    'error_count' => count($errors),
                ]
            );
        }

        return response()->json([
            'documents' => $documents,
            'errors' => $errors,
            'filename' => 'lazada-awb-packing-bulk.pdf',
        ]);
    }

    public function readyToShip(Request $request, int $order): JsonResponse
    {
        $updated = $this->lazadaOrders->readyToShip($request->user(), $order);

        $this->activityLogger->log(
            $request->user(),
            'order.ship',
            "Marked Lazada order {$updated->order_sn} ready to ship",
            $updated,
            ['platform' => 'lazada', 'order_sn' => $updated->order_sn]
        );

        return response()->json([
            'message' => 'Lazada order marked ready to ship.',
            'order' => $updated,
        ]);
    }
}
