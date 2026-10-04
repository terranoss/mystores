<?php

namespace App\Services;

use App\Exceptions\StoreNotActiveException;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException as AccessAuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LazadaPromotionService
{
    public function __construct(
        private readonly LazadaService $lazada,
        private readonly LazadaOrderService $lazadaOrders,
        private readonly WorkspaceAccess $workspaceAccess,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listPromotions(User $user, ?int $storeId = null, ?string $status = null): array
    {
        $stores = $this->accessibleLazadaStores($user, $storeId);
        $items = [];
        foreach ($stores as $store) {
            $items = array_merge($items, $this->listVouchersForStore($store, $status));
        }

        return $items;
    }

    /**
     * Rank statuses so actionable/upcoming promos appear before finished ones.
     */
    public static function statusSortKey(string $status): int
    {
        $normalized = strtoupper(trim($status));

        return match ($normalized) {
            'ONGOING' => 0,
            'NOT_START', 'UPCOMING' => 1,
            'SUSPEND' => 2,
            'ERROR' => 3,
            'FINISH', 'EXPIRED' => 9,
            default => 5,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getPromotion(User $user, int $storeId, int|string $remoteId, string $voucherType = 'COLLECTIBLE_VOUCHER'): array
    {
        $store = $this->requireLazadaStore($user, $storeId);
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->getVoucher($creds['access_token'], $creds['region'], [
            'id' => $remoteId,
            'voucher_type' => $voucherType,
        ]);
        $data = $response['data'] ?? $response;

        return $this->normalizeVoucherDetail($store, is_array($data) ? $data : []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVoucher(User $user, int $storeId, array $data): array
    {
        $store = $this->requireLazadaStore($user, $storeId);
        $skuIdsInput = $data['sku_ids'] ?? [];
        $productIdsInput = $data['product_ids'] ?? [];
        unset($data['sku_ids'], $data['product_ids']);
        $payload = $this->validateCreatePayload($data);
        $apply = (string) ($payload['apply'] ?? '');
        $resolvedSkuIds = $this->resolveSkuIds(
            $store,
            is_array($skuIdsInput) ? $skuIdsInput : [],
            is_array($productIdsInput) ? $productIdsInput : []
        );
        if ($apply === 'SPECIFIC_PRODUCTS' && $resolvedSkuIds === []) {
            throw ValidationException::withMessages([
                'sku_ids' => 'At least one product or SKU is required for SPECIFIC_PRODUCTS vouchers.',
            ]);
        }
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->createVoucher($creds['access_token'], $creds['region'], $payload);
        $id = data_get($response, 'data.id')
            ?? data_get($response, 'data.voucher_id')
            ?? data_get($response, 'id');
        $activated = false;
        $activateError = null;
        $skusAttached = false;
        $skuAttachError = null;
        if ($id !== null && $id !== '') {
            try {
                $this->lazada->activateVoucher($creds['access_token'], $creds['region'], [
                    'id' => $id,
                    'voucher_type' => (string) ($payload['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'),
                ]);
                $activated = true;
            } catch (\Throwable $e) {
                $activateError = $e->getMessage();
            }
            if ($resolvedSkuIds !== []) {
                try {
                    $this->lazada->addVoucherSku($creds['access_token'], $creds['region'], [
                        'id' => $id,
                        'voucher_type' => (string) ($payload['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'),
                        'sku_ids' => $resolvedSkuIds,
                    ]);
                    $skusAttached = true;
                } catch (\Throwable $e) {
                    $skuAttachError = $e->getMessage();
                }
            }
        }

        return [
            'id' => $id,
            'activated' => $activated,
            'activate_error' => $activateError,
            'skus_attached' => $skusAttached,
            'sku_attach_error' => $skuAttachError,
            'sku_ids' => $resolvedSkuIds,
            'raw' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVoucher(User $user, int $storeId, int|string $remoteId, array $data): array
    {
        $store = $this->requireLazadaStore($user, $storeId);
        unset($data['store_id'], $data['action']);
        $data['id'] = $remoteId;
        if (!isset($data['voucher_type'])) {
            $data['voucher_type'] = 'COLLECTIBLE_VOUCHER';
        }
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->updateVoucher($creds['access_token'], $creds['region'], $data);

        return ['raw' => $response];
    }

    /**
     * @return array<string, mixed>
     */
    public function activateVoucher(User $user, int $storeId, int|string $remoteId, string $voucherType = 'COLLECTIBLE_VOUCHER'): array
    {
        $store = $this->requireLazadaStore($user, $storeId);
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->activateVoucher($creds['access_token'], $creds['region'], [
            'id' => $remoteId,
            'voucher_type' => $voucherType,
        ]);

        return ['raw' => $response];
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivateVoucher(User $user, int $storeId, int|string $remoteId, string $voucherType = 'COLLECTIBLE_VOUCHER'): array
    {
        $store = $this->requireLazadaStore($user, $storeId);
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $response = $this->lazada->deactivateVoucher($creds['access_token'], $creds['region'], [
            'id' => $remoteId,
            'voucher_type' => $voucherType,
        ]);

        return ['raw' => $response];
    }

    /**
     * @param  list<int|string>  $skuIds
     * @return array<string, mixed>
     */
    public function mutateVoucherSkus(
        User $user,
        int $storeId,
        int|string $remoteId,
        string $action,
        array $skuIds,
        string $voucherType = 'COLLECTIBLE_VOUCHER',
    ): array {
        $store = $this->requireLazadaStore($user, $storeId);
        if ($skuIds === []) {
            throw ValidationException::withMessages(['sku_ids' => 'At least one SKU id is required.']);
        }
        $this->lazadaOrders->ensureFreshToken($store);
        $creds = $this->lazada->credentialsForStore($store);
        $params = [
            'id' => $remoteId,
            'voucher_type' => $voucherType,
            'sku_ids' => $skuIds,
        ];
        if ($action === 'remove') {
            $response = $this->lazada->removeVoucherSku($creds['access_token'], $creds['region'], $params);
        } elseif ($action === 'add') {
            $response = $this->lazada->addVoucherSku($creds['access_token'], $creds['region'], $params);
        } else {
            throw ValidationException::withMessages(['action' => 'action must be add or remove.']);
        }

        return ['raw' => $response];
    }

    /**
     * @return list<ShopeeStore>
     */
    private function accessibleLazadaStores(User $user, ?int $storeId): array
    {
        $ids = $this->workspaceAccess->workspaceIdsFor($user);
        if ($ids === []) {
            $ids = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }
        $query = ShopeeStore::query()
            ->whereIn('workspace_id', $ids)
            ->where('platform', 'lazada')
            ->where('is_active', true);
        if ($storeId !== null && $storeId > 0) {
            $query->where('id', $storeId);
        }

        return $query->get()->all();
    }

    private function requireLazadaStore(User $user, int $storeId): ShopeeStore
    {
        $store = ShopeeStore::query()->findOrFail($storeId);
        if (!$this->workspaceAccess->canAccessStore($user, $store)) {
            throw new AccessAuthorizationException('Forbidden.');
        }
        if (($store->platform ?? '') !== 'lazada') {
            throw ValidationException::withMessages(['store_id' => 'Store is not a Lazada store.']);
        }
        if (!$store->is_active) {
            throw new StoreNotActiveException("Store #{$store->id} is inactive. Please reconnect Lazada.");
        }

        return $store;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listVouchersForStore(ShopeeStore $store, ?string $status): array
    {
        try {
            $this->lazadaOrders->ensureFreshToken($store);
            $creds = $this->lazada->credentialsForStore($store);
            $params = [
                'voucher_type' => 'COLLECTIBLE_VOUCHER',
                'cur_page' => 1,
                'page_size' => 50,
            ];
            if ($status !== null && $status !== '') {
                $params['status'] = $this->mapStatus($status);
            }
            $response = $this->lazada->getVoucherList($creds['access_token'], $creds['region'], $params);
        } catch (\Throwable $e) {
            return [[
                'platform' => 'lazada',
                'store_id' => $store->id,
                'store_name' => $store->shop_name,
                'type' => 'voucher',
                'remote_id' => null,
                'name' => null,
                'status' => 'error',
                'start_at' => null,
                'end_at' => null,
                'error' => $e->getMessage(),
            ]];
        }
        $data = $response['data'] ?? $response;
        $list = [];
        if (is_array($data)) {
            $list = $data['data_list'] ?? $data['voucher_list'] ?? $data['list'] ?? [];
            if ($list === [] && array_is_list($data) && isset($data[0]) && is_array($data[0])) {
                $list = $data;
            }
        }
        $items = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                $items[] = $this->normalizeVoucherSummary($store, $row);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateCreatePayload(array $data): array
    {
        $required = [
            'voucher_type',
            'apply',
            'display_area',
            'period_start_time',
            'period_end_time',
            'voucher_discount_type',
            'criteria_over_money',
            'limit',
            'issued',
        ];
        $missing = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                $missing[$field] = "{$field} is required.";
            }
        }
        $discountType = (string) ($data['voucher_discount_type'] ?? '');
        if ($discountType === 'MONEY_VALUE_OFF' && empty($data['offering_money_value_off'])) {
            $missing['offering_money_value_off'] = 'offering_money_value_off is required for MONEY_VALUE_OFF.';
        }
        if ($discountType === 'PERCENTAGE_DISCOUNT_OFF' && empty($data['offering_percentage_discount_off'])) {
            $missing['offering_percentage_discount_off'] = 'offering_percentage_discount_off is required for PERCENTAGE_DISCOUNT_OFF.';
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
        $start = (int) $data['period_start_time'];
        $end = (int) $data['period_end_time'];
        if ($end <= $start) {
            throw ValidationException::withMessages(['period_end_time' => 'period_end_time must be after period_start_time.']);
        }
        unset($data['store_id']);
        unset($data['sku_ids'], $data['product_ids']);
        if (!isset($data['voucher_type'])) {
            $data['voucher_type'] = 'COLLECTIBLE_VOUCHER';
        }

        return $data;
    }

    /**
     * @param  list<mixed>  $skuIds
     * @param  list<mixed>  $productIds
     * @return list<int|string>
     */
    private function resolveSkuIds(ShopeeStore $store, array $skuIds, array $productIds): array
    {
        $resolved = [];
        foreach ($skuIds as $skuId) {
            if ($skuId === null || $skuId === '') {
                continue;
            }
            $resolved[] = is_numeric($skuId) ? (0 + $skuId) : (string) $skuId;
        }
        if ($productIds !== []) {
            $ids = array_values(array_unique(array_map('intval', $productIds)));
            $products = Product::query()
                ->with('variants')
                ->where('store_id', $store->id)
                ->whereIn('id', $ids)
                ->get();
            foreach ($products as $product) {
                if ($product->variants->isNotEmpty()) {
                    foreach ($product->variants as $variant) {
                        if ($variant->model_id !== null && $variant->model_id !== '') {
                            $resolved[] = is_numeric($variant->model_id)
                                ? (0 + $variant->model_id)
                                : (string) $variant->model_id;
                        }
                    }
                    continue;
                }
                $rawSkuId = data_get($product->raw_response, 'skus.0.SkuId');
                if ($rawSkuId !== null && $rawSkuId !== '') {
                    $resolved[] = is_numeric($rawSkuId) ? (0 + $rawSkuId) : (string) $rawSkuId;
                }
            }
        }
        $unique = [];
        $seen = [];
        foreach ($resolved as $id) {
            $key = (string) $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $id;
        }

        return $unique;
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'upcoming', 'not_start' => 'NOT_START',
            'ongoing' => 'ONGOING',
            'suspend' => 'SUSPEND',
            'expired', 'finish' => 'FINISH',
            default => strtoupper($status),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeVoucherSummary(ShopeeStore $store, array $row): array
    {
        $id = $row['id'] ?? $row['voucher_id'] ?? null;
        $start = $row['period_start_time'] ?? $row['start_time'] ?? null;
        $end = $row['period_end_time'] ?? $row['end_time'] ?? null;

        return [
            'platform' => 'lazada',
            'store_id' => $store->id,
            'store_name' => $store->shop_name,
            'type' => 'voucher',
            'remote_id' => $id,
            'name' => $row['voucher_name'] ?? $row['name'] ?? null,
            'status' => (string) ($row['status'] ?? ''),
            'start_at' => $this->msOrUnixToIso($start),
            'end_at' => $this->msOrUnixToIso($end),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeVoucherDetail(ShopeeStore $store, array $row): array
    {
        return array_merge($this->normalizeVoucherSummary($store, $row), [
            'raw' => $row,
        ]);
    }

    private function msOrUnixToIso(mixed $value): ?string
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        $int = (int) $value;
        // Lazada often uses milliseconds
        if ($int > 10_000_000_000) {
            return Carbon::createFromTimestampMs($int)->toIso8601String();
        }

        return Carbon::createFromTimestamp($int)->toIso8601String();
    }
}
