<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopeeStore;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class LogisticsService
{
    private const PREFERRED_DOCUMENT_TYPE = 'NORMAL_AIR_WAYBILL';

    private const FALLBACK_DOCUMENT_TYPE = 'THERMAL_AIR_WAYBILL';

    /** Max seconds to poll Shopee for document readiness. */
    private const MAX_POLL_SECONDS = 12;

    /** Seconds between each poll attempt. */
    private const POLL_INTERVAL_SECONDS = 2;

    private readonly PackingListPdfService $packingListPdf;

    public function __construct(
        private readonly ShopeeService $shopee,
        ?PackingListPdfService $packingListPdf = null,
    ) {
        $this->packingListPdf = $packingListPdf ?? app(PackingListPdfService::class);
    }

    /**
     * Fetch the shipping parameters for an order.
     * Returns the raw Shopee response (info_needed, pickup/dropoff options etc.).
     *
     * Note: do not send package_number as an empty string — Shopee returns
     * logistics.package_not_exist for non-split orders.
     */
    public function getShippingParameter(ShopeeStore $store, string $orderSn, string $packageNumber = ''): array
    {
        $client = $this->shopee->forStore($store);

        $query = ['order_sn' => $orderSn];
        if ($packageNumber !== '') {
            $query['package_number'] = $packageNumber;
        }

        $response = $client->call('GET', 'logistics/get_shipping_parameter', [
            RequestOptions::QUERY => $query,
        ]);

        Log::debug('LogisticsService::getShippingParameter', [
            'store_id'  => $store->id,
            'order_sn'  => $orderSn,
            'info_keys' => array_keys($response['info_needed'] ?? []),
        ]);

        return is_array($response) ? $response : [];
    }

    /**
     * Arrange shipment for an order.
     * $params should contain exactly ONE of: 'pickup', 'dropoff', or 'non_integrated'.
     */
    public function shipOrder(ShopeeStore $store, string $orderSn, array $params, string $packageNumber = ''): array
    {
        $client = $this->shopee->forStore($store);

        $body = ['order_sn' => $orderSn];
        if ($packageNumber !== '') {
            $body['package_number'] = $packageNumber;
        }

        if (isset($params['pickup'])) {
            $body['pickup'] = $this->asJsonObject($params['pickup']);
        } elseif (isset($params['dropoff'])) {
            $body['dropoff'] = $this->asJsonObject($params['dropoff']);
        } elseif (isset($params['non_integrated'])) {
            $body['non_integrated'] = $this->asJsonObject($params['non_integrated']);
        }

        $response = $client->call('POST', 'logistics/ship_order', [
            RequestOptions::JSON => $body,
        ]);

        Log::info('LogisticsService::shipOrder success', [
            'store_id' => $store->id,
            'order_sn' => $orderSn,
            'mode'     => array_key_first($params),
        ]);

        return is_array($response) ? $response : [];
    }

    /**
     * Shopee expects mode payloads as JSON objects. PHP empty arrays encode as []
     * which triggers "field dropoff type error".
     *
     * @return array<string, mixed>|\stdClass
     */
    private function asJsonObject(array $payload): array|\stdClass
    {
        return $payload === [] ? new \stdClass() : $payload;
    }

    /**
     * Retrieve the tracking number assigned to an order.
     */
    public function getTrackingNumber(ShopeeStore $store, string $orderSn): string
    {
        $client = $this->shopee->forStore($store);

        $response = $client->Logistic->getTrackingNumber($orderSn);

        $trackingNumber = $response['tracking_number'] ?? '';

        if (empty($trackingNumber)) {
            throw new \RuntimeException("No tracking number found for order {$orderSn}. The shipment may not have been arranged yet.");
        }

        return $trackingNumber;
    }

    /**
     * Prefer NORMAL_AIR_WAYBILL when selectable; else Shopee suggestion; else thermal.
     *
     * @param  list<string>  $selectable
     */
    public function resolveDocumentType(array $selectable, ?string $suggested = null): string
    {
        $selectable = array_values(array_filter($selectable, fn ($t) => is_string($t) && $t !== ''));

        if (in_array(self::PREFERRED_DOCUMENT_TYPE, $selectable, true)) {
            return self::PREFERRED_DOCUMENT_TYPE;
        }

        if ($suggested !== null && $suggested !== '' && ($selectable === [] || in_array($suggested, $selectable, true))) {
            return $suggested;
        }

        if ($selectable !== []) {
            return $selectable[0];
        }

        return self::FALLBACK_DOCUMENT_TYPE;
    }

    /**
     * Full AWB + packing list workflow:
     *   1. Get tracking number
     *   2. Resolve document type via get_shipping_document_parameter
     *   3. Create shipping document task
     *   4. Poll until READY (up to MAX_POLL_SECONDS)
     *   5. Download AWB PDF
     *   6. Generate packing list PDF
     *
     * Returns both PDFs for the frontend to merge (Shopee AWBs use compressed
     * xref streams that free FPDI cannot parse).
     *
     * @return array{awb: string, packing_list: string}
     *
     * @throws \RuntimeException on Shopee error or timeout
     */
    public function downloadShippingLabel(ShopeeStore $store, Order $order): array
    {
        $orderSn = $order->order_sn;
        $client = $this->shopee->forStore($store);

        $trackingNumber = $this->getTrackingNumber($store, $orderSn);

        $orderEntry = [
            'order_sn'        => $orderSn,
            'tracking_number' => $trackingNumber,
        ];

        $documentType = $this->resolveDocumentTypeFromShopee($client, $orderSn);

        $client->Logistic->createShippingDocument([
            array_merge($orderEntry, ['shipping_document_type' => $documentType]),
        ]);

        Log::info('LogisticsService: shipping document task created', [
            'store_id'               => $store->id,
            'order_sn'               => $orderSn,
            'tracking_number'        => $trackingNumber,
            'shipping_document_type' => $documentType,
        ]);

        $pollEntries = [['order_sn' => $orderSn, 'tracking_number' => $trackingNumber]];
        $maxAttempts = (int) ceil(self::MAX_POLL_SECONDS / self::POLL_INTERVAL_SECONDS);
        $status      = null;
        $failReason  = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                sleep(self::POLL_INTERVAL_SECONDS);
            }

            $result = $client->Logistic->getShippingDocumentResult($pollEntries);

            $first      = $result['result_list'][0] ?? [];
            $status     = $first['status']      ?? null;
            $failReason = $first['fail_reason'] ?? null;

            Log::debug('LogisticsService: polling document status', [
                'store_id' => $store->id,
                'order_sn' => $orderSn,
                'attempt'  => $attempt + 1,
                'status'   => $status,
            ]);

            if ($status === 'READY') {
                break;
            }

            if ($status === 'FAILED') {
                throw new \RuntimeException(
                    'Shipping document generation failed' . ($failReason ? ": {$failReason}" : '.')
                );
            }
        }

        if ($status !== 'READY') {
            throw new \RuntimeException(
                'Shipping document is not ready yet. Please try again in a few seconds.'
            );
        }

        $awbPdf = $client->Logistic->downloadShippingDocument(
            [$orderEntry],
            $documentType
        );

        if (empty($awbPdf) || !is_string($awbPdf)) {
            throw new \RuntimeException('Shopee returned an empty shipping document.');
        }

        Log::info('LogisticsService: shipping document downloaded', [
            'store_id'               => $store->id,
            'order_sn'               => $orderSn,
            'shipping_document_type' => $documentType,
            'bytes'                  => strlen($awbPdf),
        ]);

        $packingListPdf = $this->packingListPdf->generate($order);

        Log::info('LogisticsService: AWB + packing list ready for client merge', [
            'store_id' => $store->id,
            'order_sn' => $orderSn,
            'awb_bytes' => strlen($awbPdf),
            'packing_bytes' => strlen($packingListPdf),
        ]);

        return [
            'awb' => $awbPdf,
            'packing_list' => $packingListPdf,
        ];
    }

    /**
     * Call Shopee get_shipping_document_parameter and pick a document type.
     */
    private function resolveDocumentTypeFromShopee(object $client, string $orderSn): string
    {
        try {
            $response = $client->Logistic->getShippingDocumentParameter([
                ['order_sn' => $orderSn],
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogisticsService: get_shipping_document_parameter failed; using fallback', [
                'order_sn' => $orderSn,
                'error'    => $e->getMessage(),
            ]);

            return self::FALLBACK_DOCUMENT_TYPE;
        }

        $first = $response['result_list'][0] ?? [];
        $selectable = $first['selectable_shipping_document_type'] ?? [];
        if (!is_array($selectable)) {
            $selectable = [];
        }

        $suggested = $first['suggested_shipping_document_type']
            ?? $first['suggest_shipping_document_type']
            ?? null;

        $type = $this->resolveDocumentType($selectable, is_string($suggested) ? $suggested : null);

        Log::debug('LogisticsService: resolved document type', [
            'order_sn'   => $orderSn,
            'selectable' => $selectable,
            'suggested'  => $suggested,
            'resolved'   => $type,
        ]);

        return $type;
    }
}
