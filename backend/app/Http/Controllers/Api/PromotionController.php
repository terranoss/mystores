<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\StoreNotActiveException;
use App\Http\Controllers\Controller;
use App\Services\LazadaPromotionService;
use App\Services\ShopeePromotionService;
use App\Services\WorkspaceActivityLogger;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function __construct(
        private readonly ShopeePromotionService $shopeePromotions,
        private readonly LazadaPromotionService $lazadaPromotions,
        private readonly WorkspaceActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|in:shopee,lazada,all',
            'store_id' => 'nullable|integer|min:1',
            'type' => 'nullable|in:discount,voucher',
            'status' => 'nullable|string|max:40',
        ]);
        $platform = $validated['platform'] ?? 'all';
        $storeId = isset($validated['store_id']) ? (int) $validated['store_id'] : null;
        $type = $validated['type'] ?? null;
        $status = $validated['status'] ?? null;
        $items = [];
        try {
            if ($platform === 'all' || $platform === 'shopee') {
                $items = array_merge(
                    $items,
                    $this->shopeePromotions->listPromotions($request->user(), $storeId, $type, $status)
                );
            }
            if (($platform === 'all' || $platform === 'lazada') && ($type === null || $type === 'voucher')) {
                $items = array_merge(
                    $items,
                    $this->lazadaPromotions->listPromotions($request->user(), $storeId, $status)
                );
            }
        } catch (StoreNotActiveException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ShopeeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'request_id' => method_exists($e, 'getRequestId') ? $e->getRequestId() : null,
            ], 422);
        }

        usort($items, function (array $a, array $b): int {
            $ka = LazadaPromotionService::statusSortKey((string) ($a['status'] ?? ''));
            $kb = LazadaPromotionService::statusSortKey((string) ($b['status'] ?? ''));
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }
            $ta = strtotime((string) ($a['start_at'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['start_at'] ?? '')) ?: 0;

            return $tb <=> $ta;
        });

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, string $platform, string $type, string $remoteId): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_type' => 'nullable|string|max:64',
        ]);
        try {
            if ($platform === 'shopee') {
                $promo = $this->shopeePromotions->getPromotion(
                    $request->user(),
                    (int) $validated['store_id'],
                    $type,
                    $remoteId
                );
            } elseif ($platform === 'lazada') {
                if ($type !== 'voucher') {
                    throw ValidationException::withMessages(['type' => 'Lazada only supports voucher.']);
                }
                $promo = $this->lazadaPromotions->getPromotion(
                    $request->user(),
                    (int) $validated['store_id'],
                    $remoteId,
                    $validated['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'
                );
            } else {
                return response()->json(['message' => 'Unknown platform.'], 404);
            }
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden.'], 403);
        } catch (StoreNotActiveException | ValidationException $e) {
            throw $e;
        } catch (ShopeeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['promotion' => $promo]);
    }

    public function createShopeeDiscount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'discount_name' => 'required|string|max:100',
            'start_time' => 'required|integer',
            'end_time' => 'required|integer',
        ]);

        return $this->wrapMutation(function () use ($request, $validated) {
            $result = $this->shopeePromotions->createDiscount(
                $request->user(),
                (int) $validated['store_id'],
                $validated
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.create_discount',
                'Created Shopee discount '.$validated['discount_name'],
                null,
                ['platform' => 'shopee', 'discount_id' => $result['discount_id'] ?? null]
            );

            return response()->json(['message' => 'Discount created.', 'result' => $result], 201);
        });
    }

    public function addShopeeDiscountItems(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'item_list' => 'required|array|min:1',
        ]);

        return $this->wrapMutation(function () use ($request, $validated, $id) {
            $result = $this->shopeePromotions->addDiscountItems(
                $request->user(),
                (int) $validated['store_id'],
                $id,
                $validated['item_list']
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.add_discount_items',
                "Added items to Shopee discount {$id}",
                null,
                ['platform' => 'shopee', 'discount_id' => $id]
            );

            return response()->json(['message' => 'Discount items added.', 'result' => $result]);
        });
    }

    public function updateShopeeDiscount(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'action' => 'nullable|in:update,end,delete',
            'discount_name' => 'nullable|string|max:100',
            'start_time' => 'nullable|integer',
            'end_time' => 'nullable|integer',
            'item_list' => 'nullable|array',
        ]);

        return $this->wrapMutation(function () use ($request, $validated, $id) {
            $result = $this->shopeePromotions->updateDiscount(
                $request->user(),
                (int) $validated['store_id'],
                $id,
                $validated
            );
            if (!empty($validated['item_list'])) {
                $this->shopeePromotions->updateDiscountItems(
                    $request->user(),
                    (int) $validated['store_id'],
                    $id,
                    $validated['item_list']
                );
            }
            $this->activityLogger->log(
                $request->user(),
                'promotion.update_discount',
                'Updated Shopee discount '.$id,
                null,
                ['platform' => 'shopee', 'discount_id' => $id, 'action' => $validated['action'] ?? 'update']
            );

            return response()->json(['message' => 'Discount updated.', 'result' => $result]);
        });
    }

    public function createShopeeVoucher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_name' => 'required|string|max:100',
            'voucher_code' => 'nullable|string|max:100',
            'voucher_type' => 'required|integer',
            'reward_type' => 'required|integer',
            'discount_amount' => 'nullable|numeric',
            'percentage' => 'nullable|numeric',
            'max_price' => 'nullable|numeric',
            'usage_quantity' => 'required|integer|min:1',
            'min_basket_price' => 'required|numeric|min:0',
            'start_time' => 'required|integer',
            'end_time' => 'required|integer',
            'display_start_time' => 'nullable|integer',
            'display_channel_list' => 'nullable|array',
            'item_id_list' => 'nullable|array',
            'item_id_list.*' => 'integer',
        ]);
        if ((int) $validated['voucher_type'] === 2) {
            $request->validate([
                'item_id_list' => 'required|array|min:1',
                'item_id_list.*' => 'integer',
            ]);
            $validated['item_id_list'] = $request->input('item_id_list');
        } else {
            unset($validated['item_id_list']);
        }

        return $this->wrapMutation(function () use ($request, $validated) {
            $result = $this->shopeePromotions->createVoucher(
                $request->user(),
                (int) $validated['store_id'],
                $validated
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.create_voucher',
                'Created Shopee voucher '.$validated['voucher_name'],
                null,
                ['platform' => 'shopee', 'voucher_id' => $result['voucher_id'] ?? null]
            );

            return response()->json(['message' => 'Voucher created.', 'result' => $result], 201);
        });
    }

    public function updateShopeeVoucher(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'action' => 'nullable|in:update,end,delete',
        ]);
        $payload = array_merge($request->all(), $validated);

        return $this->wrapMutation(function () use ($request, $payload, $id) {
            $result = $this->shopeePromotions->updateVoucher(
                $request->user(),
                (int) $payload['store_id'],
                $id,
                $payload
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.update_voucher',
                'Updated Shopee voucher '.$id,
                null,
                ['platform' => 'shopee', 'voucher_id' => $id, 'action' => $payload['action'] ?? 'update']
            );

            return response()->json(['message' => 'Voucher updated.', 'result' => $result]);
        });
    }

    public function createLazadaVoucher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_type' => 'required|string|max:64',
            'apply' => 'required|string|max:64',
            'display_area' => 'required|string|max:64',
            'period_start_time' => 'required|integer',
            'period_end_time' => 'required|integer',
            'voucher_discount_type' => 'required|string|max:64',
            'criteria_over_money' => 'required',
            'limit' => 'required|integer|min:1',
            'issued' => 'required|integer|min:1',
            'offering_money_value_off' => 'nullable',
            'offering_percentage_discount_off' => 'nullable',
            'max_discount_offering_money_value' => 'nullable',
            'collect_start' => 'nullable|integer',
            'voucher_name' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'sku_ids' => 'nullable|array',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|min:1',
        ]);
        if (($validated['apply'] ?? '') === 'SPECIFIC_PRODUCTS') {
            $skuIds = $request->input('sku_ids', []);
            $productIds = $request->input('product_ids', []);
            if ((!is_array($skuIds) || $skuIds === []) && (!is_array($productIds) || $productIds === [])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sku_ids' => 'Provide sku_ids and/or product_ids for SPECIFIC_PRODUCTS vouchers.',
                ]);
            }
            if (is_array($skuIds) && $skuIds !== []) {
                $validated['sku_ids'] = $skuIds;
            }
            if (is_array($productIds) && $productIds !== []) {
                $validated['product_ids'] = $productIds;
            }
        } else {
            unset($validated['sku_ids'], $validated['product_ids']);
        }

        return $this->wrapMutation(function () use ($request, $validated) {
            $result = $this->lazadaPromotions->createVoucher(
                $request->user(),
                (int) $validated['store_id'],
                $validated
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.create_voucher',
                'Created Lazada voucher',
                null,
                ['platform' => 'lazada', 'id' => $result['id'] ?? null]
            );

            return response()->json(['message' => 'Lazada voucher created.', 'result' => $result], 201);
        });
    }

    public function updateLazadaVoucher(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_type' => 'nullable|string|max:64',
        ]);
        $payload = array_merge($request->all(), $validated);

        return $this->wrapMutation(function () use ($request, $payload, $id) {
            $result = $this->lazadaPromotions->updateVoucher(
                $request->user(),
                (int) $payload['store_id'],
                $id,
                $payload
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.update_voucher',
                'Updated Lazada voucher '.$id,
                null,
                ['platform' => 'lazada', 'id' => $id]
            );

            return response()->json(['message' => 'Lazada voucher updated.', 'result' => $result]);
        });
    }

    public function activateLazadaVoucher(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_type' => 'nullable|string|max:64',
        ]);

        return $this->wrapMutation(function () use ($request, $validated, $id) {
            $result = $this->lazadaPromotions->activateVoucher(
                $request->user(),
                (int) $validated['store_id'],
                $id,
                $validated['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.activate_voucher',
                'Activated Lazada voucher '.$id,
                null,
                ['platform' => 'lazada', 'id' => $id]
            );

            return response()->json(['message' => 'Lazada voucher activated.', 'result' => $result]);
        });
    }

    public function deactivateLazadaVoucher(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'voucher_type' => 'nullable|string|max:64',
        ]);

        return $this->wrapMutation(function () use ($request, $validated, $id) {
            $result = $this->lazadaPromotions->deactivateVoucher(
                $request->user(),
                (int) $validated['store_id'],
                $id,
                $validated['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.deactivate_voucher',
                'Deactivated Lazada voucher '.$id,
                null,
                ['platform' => 'lazada', 'id' => $id]
            );

            return response()->json(['message' => 'Lazada voucher deactivated.', 'result' => $result]);
        });
    }

    public function mutateLazadaVoucherSkus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|min:1',
            'action' => 'required|in:add,remove',
            'sku_ids' => 'required|array|min:1',
            'voucher_type' => 'nullable|string|max:64',
        ]);

        return $this->wrapMutation(function () use ($request, $validated, $id) {
            $result = $this->lazadaPromotions->mutateVoucherSkus(
                $request->user(),
                (int) $validated['store_id'],
                $id,
                $validated['action'],
                $validated['sku_ids'],
                $validated['voucher_type'] ?? 'COLLECTIBLE_VOUCHER'
            );
            $this->activityLogger->log(
                $request->user(),
                'promotion.mutate_voucher_skus',
                ucfirst($validated['action']).' SKUs on Lazada voucher '.$id,
                null,
                ['platform' => 'lazada', 'id' => $id, 'action' => $validated['action']]
            );

            return response()->json(['message' => 'Lazada voucher SKUs updated.', 'result' => $result]);
        });
    }

    /**
     * @param  callable(): JsonResponse  $callback
     */
    private function wrapMutation(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (AuthorizationException $e) {
            return response()->json(['message' => 'Forbidden.'], 403);
        } catch (ValidationException $e) {
            throw $e;
        } catch (StoreNotActiveException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ShopeeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'request_id' => method_exists($e, 'getRequestId') ? $e->getRequestId() : null,
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
