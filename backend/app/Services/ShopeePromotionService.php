<?php

namespace App\Services;

use App\Exceptions\StoreNotActiveException;
use App\Models\ShopeeStore;
use App\Models\User;
use EcomPHP\Shopee\Client;
use EcomPHP\Shopee\Errors\AuthorizationException;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Auth\Access\AuthorizationException as AccessAuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ShopeePromotionService
{
    private const MIN_START_OFFSET_SECONDS = 3600;

    private const MIN_DURATION_SECONDS = 3600;

    private const MAX_DURATION_SECONDS = 180 * 86400;

    public function __construct(
        private readonly ShopeeService $shopee,
        private readonly WorkspaceAccess $workspaceAccess,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listPromotions(User $user, ?int $storeId = null, ?string $type = null, ?string $status = null): array
    {
        $stores = $this->accessibleShopeeStores($user, $storeId);
        $items = [];
        foreach ($stores as $store) {
            if ($type === null || $type === 'discount') {
                $items = array_merge($items, $this->listDiscountsForStore($store, $status));
            }
            if ($type === null || $type === 'voucher') {
                $items = array_merge($items, $this->listVouchersForStore($store, $status));
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPromotion(User $user, int $storeId, string $type, int|string $remoteId): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        if ($type === 'discount') {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($remoteId) {
                return $client->Discount->getDiscount((int) $remoteId);
            });

            return $this->normalizeDiscountDetail($store, $this->unwrapShopeeResponse($response));
        }
        if ($type === 'voucher') {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($remoteId) {
                return $client->Voucher->getVoucher((int) $remoteId);
            });

            return $this->normalizeVoucherDetail($store, $this->unwrapShopeeResponse($response));
        }
        throw ValidationException::withMessages(['type' => 'Unsupported Shopee promotion type.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDiscount(User $user, int $storeId, array $data): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        $payload = $this->validateDiscountShell($data);
        $response = $this->callWithTokenRetry($store, function (Client $client) use ($payload) {
            return $client->Discount->addDiscount($payload);
        });
        $body = $this->unwrapShopeeResponse($response);

        return [
            'discount_id' => $body['discount_id'] ?? null,
            'raw' => $body,
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itemList
     * @return array<string, mixed>
     */
    public function addDiscountItems(User $user, int $storeId, int $discountId, array $itemList): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        if ($itemList === []) {
            throw ValidationException::withMessages(['item_list' => 'At least one discount item is required.']);
        }
        $response = $this->callWithTokenRetry($store, function (Client $client) use ($discountId, $itemList) {
            return $client->Discount->addDiscountItem($discountId, $itemList);
        });

        return [
            'raw' => $this->unwrapShopeeResponse($response),
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateDiscount(User $user, int $storeId, int $discountId, array $data): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        $action = (string) ($data['action'] ?? 'update');
        if ($action === 'end') {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($discountId) {
                return $client->Discount->endDiscount($discountId);
            });
        } elseif ($action === 'delete') {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($discountId) {
                return $client->Discount->deleteDiscount($discountId);
            });
        } else {
            unset($data['action'], $data['store_id']);
            if (isset($data['start_time']) || isset($data['end_time'])) {
                $this->validateDiscountShell(array_merge([
                    'discount_name' => $data['discount_name'] ?? 'update',
                    'start_time' => $data['start_time'] ?? (time() + self::MIN_START_OFFSET_SECONDS + 60),
                    'end_time' => $data['end_time'] ?? (time() + self::MIN_START_OFFSET_SECONDS + self::MIN_DURATION_SECONDS + 120),
                ], $data), allowPastStart: true);
            }
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($discountId, $data) {
                return $client->Discount->updateDiscount($discountId, $data);
            });
        }

        return [
            'raw' => $this->unwrapShopeeResponse($response),
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itemList
     * @return array<string, mixed>
     */
    public function updateDiscountItems(User $user, int $storeId, int $discountId, array $itemList): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        $response = $this->callWithTokenRetry($store, function (Client $client) use ($discountId, $itemList) {
            return $client->Discount->updateDiscountItem($discountId, $itemList);
        });

        return [
            'raw' => $this->unwrapShopeeResponse($response),
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createVoucher(User $user, int $storeId, array $data): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        $payload = $this->validateVoucherPayload($data);
        $response = $this->callWithTokenRetry($store, function (Client $client) use ($payload) {
            return $client->Voucher->addVoucher($payload);
        });
        $body = $this->unwrapShopeeResponse($response);

        return [
            'voucher_id' => $body['voucher_id'] ?? null,
            'raw' => $body,
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateVoucher(User $user, int $storeId, int $voucherId, array $data): array
    {
        $store = $this->requireShopeeStore($user, $storeId);
        $action = (string) ($data['action'] ?? 'update');
        $this->assertVoucherMutable($store, $voucherId);
        try {
            if ($action === 'end') {
                $response = $this->callWithTokenRetry($store, function (Client $client) use ($voucherId) {
                    return $client->Voucher->endVoucher($voucherId);
                });
            } elseif ($action === 'delete') {
                $response = $this->callWithTokenRetry($store, function (Client $client) use ($voucherId) {
                    return $client->Voucher->deleteVoucher($voucherId);
                });
            } else {
                unset($data['action'], $data['store_id']);
                $response = $this->callWithTokenRetry($store, function (Client $client) use ($voucherId, $data) {
                    return $client->Voucher->updateVoucher($voucherId, $data);
                });
            }
        } catch (ShopeeException $e) {
            if (str_contains(strtolower($e->getMessage()), 'affiliate')) {
                throw ValidationException::withMessages([
                    'voucher_id' => 'This is an affiliate/platform voucher and cannot be ended or edited from MelurCom.',
                ]);
            }
            throw $e;
        }

        return [
            'raw' => $this->unwrapShopeeResponse($response),
            'request_id' => is_array($response) ? ($response['request_id'] ?? null) : null,
        ];
    }

    /**
     * @return list<ShopeeStore>
     */
    private function accessibleShopeeStores(User $user, ?int $storeId): array
    {
        $ids = $this->workspaceAccess->workspaceIdsFor($user);
        if ($ids === []) {
            $ids = [$this->workspaceAccess->ensurePersonalWorkspace($user)->id];
        }
        $query = ShopeeStore::query()
            ->whereIn('workspace_id', $ids)
            ->where('platform', 'shopee')
            ->where('is_active', true);
        if ($storeId !== null && $storeId > 0) {
            $query->where('id', $storeId);
        }

        return $query->get()->all();
    }

    private function requireShopeeStore(User $user, int $storeId): ShopeeStore
    {
        $store = ShopeeStore::query()->findOrFail($storeId);
        if (!$this->workspaceAccess->canAccessStore($user, $store)) {
            throw new AccessAuthorizationException('Forbidden.');
        }
        if (($store->platform ?? 'shopee') !== 'shopee') {
            throw ValidationException::withMessages(['store_id' => 'Store is not a Shopee store.']);
        }
        if (!$store->is_active) {
            throw new StoreNotActiveException("Store #{$store->id} is inactive. Please re-link from the Dashboard.");
        }

        return $store;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listDiscountsForStore(ShopeeStore $store, ?string $status): array
    {
        $params = [
            'discount_status' => $this->mapDiscountStatus($status),
            'page_no' => 1,
            'page_size' => 50,
        ];
        try {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($params) {
                return $client->Discount->getDiscountList($params);
            });
        } catch (\Throwable $e) {
            return [[
                'platform' => 'shopee',
                'store_id' => $store->id,
                'store_name' => $store->shop_name,
                'type' => 'discount',
                'remote_id' => null,
                'name' => null,
                'status' => 'error',
                'start_at' => null,
                'end_at' => null,
                'error' => $e->getMessage(),
            ]];
        }
        $body = $this->unwrapShopeeResponse($response);
        $list = $body['discount_list'] ?? [];
        $items = [];
        foreach ($list as $row) {
            $items[] = $this->normalizeDiscountSummary($store, is_array($row) ? $row : []);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listVouchersForStore(ShopeeStore $store, ?string $status): array
    {
        $params = [
            'status' => $this->mapVoucherStatus($status),
            'page_no' => 1,
            'page_size' => 50,
        ];
        try {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($params) {
                return $client->Voucher->getVoucherList($params);
            });
        } catch (\Throwable $e) {
            return [[
                'platform' => 'shopee',
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
        $body = $this->unwrapShopeeResponse($response);
        $list = $body['voucher_list'] ?? [];
        $items = [];
        foreach ($list as $row) {
            $items[] = $this->normalizeVoucherSummary($store, is_array($row) ? $row : []);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{discount_name:string,start_time:int,end_time:int}
     */
    private function validateDiscountShell(array $data, bool $allowPastStart = false): array
    {
        $name = trim((string) ($data['discount_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['discount_name' => 'Discount name is required.']);
        }
        $start = (int) ($data['start_time'] ?? 0);
        $end = (int) ($data['end_time'] ?? 0);
        if ($start <= 0 || $end <= 0) {
            throw ValidationException::withMessages([
                'start_time' => 'start_time and end_time (unix timestamps) are required.',
            ]);
        }
        $now = time();
        if (!$allowPastStart && $start < ($now + self::MIN_START_OFFSET_SECONDS)) {
            throw ValidationException::withMessages([
                'start_time' => 'Shopee requires discount start_time at least 1 hour from now.',
            ]);
        }
        if ($end < ($start + self::MIN_DURATION_SECONDS)) {
            throw ValidationException::withMessages([
                'end_time' => 'Discount must run for at least 1 hour.',
            ]);
        }
        if (($end - $start) > self::MAX_DURATION_SECONDS) {
            throw ValidationException::withMessages([
                'end_time' => 'Discount duration must be within 180 days.',
            ]);
        }

        return [
            'discount_name' => $name,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateVoucherPayload(array $data): array
    {
        $required = ['voucher_name', 'voucher_type', 'reward_type', 'usage_quantity', 'min_basket_price', 'start_time', 'end_time'];
        $missing = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                $missing[$field] = "{$field} is required.";
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
        $start = (int) $data['start_time'];
        $end = (int) $data['end_time'];
        if ($end <= $start) {
            throw ValidationException::withMessages(['end_time' => 'end_time must be after start_time.']);
        }
        $voucherType = (int) $data['voucher_type'];
        if ($voucherType === 2) {
            $itemIds = $data['item_id_list'] ?? null;
            if (!is_array($itemIds) || $itemIds === []) {
                throw ValidationException::withMessages([
                    'item_id_list' => 'item_id_list is required for product vouchers (voucher_type=2).',
                ]);
            }
            $data['item_id_list'] = array_values(array_map('intval', $itemIds));
        } else {
            unset($data['item_id_list']);
        }
        unset($data['store_id']);

        return $data;
    }

    private function mapDiscountStatus(?string $status): string
    {
        return match ($status) {
            'upcoming' => 'upcoming',
            'ongoing' => 'ongoing',
            'expired' => 'expired',
            default => 'all',
        };
    }

    private function mapVoucherStatus(?string $status): string
    {
        return match ($status) {
            'upcoming' => 'upcoming',
            'ongoing' => 'ongoing',
            'expired' => 'expired',
            default => 'all',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeDiscountSummary(ShopeeStore $store, array $row): array
    {
        return [
            'platform' => 'shopee',
            'store_id' => $store->id,
            'store_name' => $store->shop_name,
            'type' => 'discount',
            'remote_id' => $row['discount_id'] ?? null,
            'name' => $row['discount_name'] ?? null,
            'status' => (string) ($row['status'] ?? $row['discount_status'] ?? ''),
            'start_at' => $this->unixToIso($row['start_time'] ?? null),
            'end_at' => $this->unixToIso($row['end_time'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeDiscountDetail(ShopeeStore $store, array $row): array
    {
        return array_merge($this->normalizeDiscountSummary($store, $row), [
            'raw' => $row,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeVoucherSummary(ShopeeStore $store, array $row): array
    {
        $isAdmin = (bool) ($row['is_admin'] ?? false);

        return [
            'platform' => 'shopee',
            'store_id' => $store->id,
            'store_name' => $store->shop_name,
            'type' => 'voucher',
            'remote_id' => $row['voucher_id'] ?? null,
            'name' => $row['voucher_name'] ?? null,
            'status' => (string) ($row['status'] ?? ''),
            'start_at' => $this->unixToIso($row['start_time'] ?? null),
            'end_at' => $this->unixToIso($row['end_time'] ?? null),
            'is_admin' => $isAdmin,
            'can_mutate' => !$isAdmin,
            'mutate_blocked_reason' => $isAdmin
                ? 'Platform/affiliate voucher — Shopee does not allow ending it via seller API.'
                : null,
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

    private function unixToIso(mixed $value): ?string
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value)->toIso8601String();
    }

    private function assertVoucherMutable(ShopeeStore $store, int $voucherId): void
    {
        try {
            $response = $this->callWithTokenRetry($store, function (Client $client) use ($voucherId) {
                return $client->Voucher->getVoucher($voucherId);
            });
        } catch (\Throwable) {
            return;
        }
        $body = $this->unwrapShopeeResponse($response);
        if ((bool) ($body['is_admin'] ?? false)) {
            throw ValidationException::withMessages([
                'voucher_id' => 'This is a platform/affiliate voucher. Shopee does not allow ending or editing it via the seller API.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrapShopeeResponse(mixed $response): array
    {
        if (!is_array($response)) {
            return [];
        }
        if (isset($response['response']) && is_array($response['response'])) {
            return $response['response'];
        }

        return $response;
    }

    /**
     * Run a Shopee API call; refresh and retry once on token errors.
     *
     * Delegates to ShopeeService so refresh/retry semantics live in one place.
     */
    private function callWithTokenRetry(ShopeeStore $store, callable $callback): mixed
    {
        return $this->shopee->callWithTokenRetry(
            $store,
            $callback,
            fn (string $message): \Throwable => new StoreNotActiveException($message)
        );
    }
}
