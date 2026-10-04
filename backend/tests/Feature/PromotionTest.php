<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\LazadaPromotionService;
use App\Services\ShopeePromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_promotions_index_requires_auth(): void
    {
        $this->getJson('/api/promotions')->assertUnauthorized();
    }

    public function test_promotions_index_returns_normalized_list(): void
    {
        $user = User::factory()->create();

        $this->mock(ShopeePromotionService::class, function ($mock) use ($user) {
            $mock->shouldReceive('listPromotions')
                ->once()
                ->withArgs(function ($argUser, $storeId, $type, $status) use ($user) {
                    return $argUser->is($user)
                        && $storeId === null
                        && $type === null
                        && $status === null;
                })
                ->andReturn([
                    [
                        'platform' => 'shopee',
                        'store_id' => 1,
                        'store_name' => 'Shopee Shop',
                        'type' => 'discount',
                        'remote_id' => 1001,
                        'name' => 'Weekend Sale',
                        'status' => 'upcoming',
                        'start_at' => now()->addDay()->toIso8601String(),
                        'end_at' => now()->addDays(3)->toIso8601String(),
                    ],
                ]);
        });

        $this->mock(LazadaPromotionService::class, function ($mock) use ($user) {
            $mock->shouldReceive('listPromotions')
                ->once()
                ->withArgs(function ($argUser, $storeId, $status) use ($user) {
                    return $argUser->is($user) && $storeId === null && $status === null;
                })
                ->andReturn([
                    [
                        'platform' => 'lazada',
                        'store_id' => 2,
                        'store_name' => 'Lazada Shop',
                        'type' => 'voucher',
                        'remote_id' => 2002,
                        'name' => 'RM5 Off',
                        'status' => 'NOT_START',
                        'start_at' => now()->addDay()->toIso8601String(),
                        'end_at' => now()->addDays(7)->toIso8601String(),
                    ],
                ]);
        });

        $response = $this->actingAs($user)->getJson('/api/promotions');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Weekend Sale');
        $response->assertJsonPath('data.1.platform', 'lazada');
    }

    public function test_create_shopee_discount_rejects_start_time_within_one_hour(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 88001,
            'shop_name' => 'Promo Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $start = time() + 600;
        $response = $this->actingAs($user)->postJson('/api/shopee/promotions/discounts', [
            'store_id' => $store->id,
            'discount_name' => 'Too Soon',
            'start_time' => $start,
            'end_time' => $start + 7200,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_time']);
    }

    public function test_create_shopee_discount_forbidden_for_other_users_store(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $other->id,
            'shop_id' => 88002,
            'shop_name' => 'Other Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $start = time() + 7200;
        $response = $this->actingAs($user)->postJson('/api/shopee/promotions/discounts', [
            'store_id' => $store->id,
            'discount_name' => 'Nope',
            'start_time' => $start,
            'end_time' => $start + 7200,
        ]);

        $response->assertForbidden();
    }

    public function test_create_shopee_discount_happy_path(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 88003,
            'shop_name' => 'OK Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $this->mock(ShopeePromotionService::class, function ($mock) use ($user, $store) {
            $mock->shouldReceive('createDiscount')
                ->once()
                ->withArgs(function ($argUser, $storeId, $data) use ($user, $store) {
                    return $argUser->is($user)
                        && $storeId === $store->id
                        && ($data['discount_name'] ?? null) === 'Happy';
                })
                ->andReturn([
                    'discount_id' => 555,
                    'raw' => ['discount_id' => 555],
                    'request_id' => 'req-1',
                ]);
        });

        $start = time() + 7200;
        $response = $this->actingAs($user)->postJson('/api/shopee/promotions/discounts', [
            'store_id' => $store->id,
            'discount_name' => 'Happy',
            'start_time' => $start,
            'end_time' => $start + 86400,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('result.discount_id', 555);
    }

    public function test_create_lazada_voucher_requires_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/lazada/promotions/vouchers', [
            'store_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'voucher_type',
            'apply',
            'display_area',
            'period_start_time',
            'period_end_time',
            'voucher_discount_type',
            'criteria_over_money',
            'limit',
            'issued',
        ]);
    }

    public function test_end_shopee_voucher_rejects_admin_affiliate_voucher(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 88004,
            'shop_name' => 'Affiliate Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $this->mock(ShopeePromotionService::class, function ($mock) use ($user, $store) {
            $mock->shouldReceive('updateVoucher')
                ->once()
                ->withArgs(function ($argUser, $storeId, $voucherId, $data) use ($user, $store) {
                    return $argUser->is($user)
                        && $storeId === $store->id
                        && $voucherId === 1353476161294340
                        && ($data['action'] ?? null) === 'end';
                })
                ->andThrow(\Illuminate\Validation\ValidationException::withMessages([
                    'voucher_id' => 'This is a platform/affiliate voucher. Shopee does not allow ending or editing it via the seller API.',
                ]));
        });

        $response = $this->actingAs($user)->putJson('/api/shopee/promotions/vouchers/1353476161294340', [
            'store_id' => $store->id,
            'action' => 'end',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['voucher_id']);
    }

    public function test_create_shopee_product_voucher_requires_item_id_list(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 88005,
            'shop_name' => 'Product Voucher Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $start = time() + 7200;
        $response = $this->actingAs($user)->postJson('/api/shopee/promotions/vouchers', [
            'store_id' => $store->id,
            'voucher_name' => 'Product Off',
            'voucher_type' => 2,
            'reward_type' => 1,
            'discount_amount' => 5,
            'usage_quantity' => 100,
            'min_basket_price' => 10,
            'start_time' => $start,
            'end_time' => $start + 86400,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_id_list']);
    }

    public function test_add_shopee_discount_items_accepts_model_list(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 88006,
            'shop_name' => 'Discount Items Shop',
            'region' => 'MY',
            'platform' => 'shopee',
            'is_active' => true,
        ]);

        $this->mock(ShopeePromotionService::class, function ($mock) use ($user, $store) {
            $mock->shouldReceive('addDiscountItems')
                ->once()
                ->withArgs(function ($argUser, $storeId, $discountId, $itemList) use ($user, $store) {
                    return $argUser->is($user)
                        && $storeId === $store->id
                        && $discountId === 777
                        && isset($itemList[0]['model_list'][0]['model_id'])
                        && (int) $itemList[0]['model_list'][0]['model_id'] === 99
                        && (float) $itemList[0]['model_list'][0]['model_promotion_price'] === 12.5;
                })
                ->andReturn(['raw' => ['ok' => true]]);
        });

        $response = $this->actingAs($user)->postJson('/api/shopee/promotions/discounts/777/items', [
            'store_id' => $store->id,
            'item_list' => [
                [
                    'item_id' => 1001,
                    'purchase_limit' => 0,
                    'model_list' => [
                        ['model_id' => 99, 'model_promotion_price' => 12.5],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
    }

    public function test_create_lazada_specific_products_requires_sku_or_product_ids(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 99001,
            'shop_name' => 'Lazada Promo',
            'region' => 'MY',
            'platform' => 'lazada',
            'is_active' => true,
        ]);

        $start = (int) (microtime(true) * 1000) + 3_600_000;
        $response = $this->actingAs($user)->postJson('/api/lazada/promotions/vouchers', [
            'store_id' => $store->id,
            'voucher_type' => 'COLLECTIBLE_VOUCHER',
            'apply' => 'SPECIFIC_PRODUCTS',
            'display_area' => 'REGULAR_CHANNEL',
            'period_start_time' => $start,
            'period_end_time' => $start + 86_400_000,
            'voucher_discount_type' => 'MONEY_VALUE_OFF',
            'criteria_over_money' => '30',
            'offering_money_value_off' => '5',
            'limit' => 1,
            'issued' => 100,
            'voucher_name' => 'Specific',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sku_ids']);
    }

    public function test_create_lazada_specific_products_passes_sku_ids_to_service(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 99002,
            'shop_name' => 'Lazada Promo 2',
            'region' => 'MY',
            'platform' => 'lazada',
            'is_active' => true,
        ]);

        $this->mock(LazadaPromotionService::class, function ($mock) use ($user, $store) {
            $mock->shouldReceive('createVoucher')
                ->once()
                ->withArgs(function ($argUser, $storeId, $data) use ($user, $store) {
                    return $argUser->is($user)
                        && $storeId === $store->id
                        && ($data['apply'] ?? null) === 'SPECIFIC_PRODUCTS'
                        && ($data['sku_ids'] ?? null) === [555001, 555002];
                })
                ->andReturn([
                    'id' => '900001',
                    'activated' => true,
                    'skus_attached' => true,
                    'sku_ids' => [555001, 555002],
                    'raw' => [],
                ]);
        });

        $start = (int) (microtime(true) * 1000) + 3_600_000;
        $response = $this->actingAs($user)->postJson('/api/lazada/promotions/vouchers', [
            'store_id' => $store->id,
            'voucher_type' => 'COLLECTIBLE_VOUCHER',
            'apply' => 'SPECIFIC_PRODUCTS',
            'display_area' => 'REGULAR_CHANNEL',
            'period_start_time' => $start,
            'period_end_time' => $start + 86_400_000,
            'voucher_discount_type' => 'MONEY_VALUE_OFF',
            'criteria_over_money' => '30',
            'offering_money_value_off' => '5',
            'limit' => 1,
            'issued' => 100,
            'voucher_name' => 'Specific',
            'sku_ids' => [555001, 555002],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('result.id', '900001');
        $response->assertJsonPath('result.skus_attached', true);
    }

    public function test_lazada_promotion_service_attaches_skus_after_create(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 99003,
            'shop_name' => 'Lazada Attach',
            'region' => 'MY',
            'platform' => 'lazada',
            'is_active' => true,
        ]);

        $lazada = Mockery::mock(\App\Services\LazadaService::class);
        $lazada->shouldReceive('credentialsForStore')
            ->andReturn(['access_token' => 'tok', 'region' => 'MY']);
        $lazada->shouldReceive('createVoucher')
            ->once()
            ->andReturn(['data' => ['id' => '900099']]);
        $lazada->shouldReceive('activateVoucher')
            ->once()
            ->with('tok', 'MY', Mockery::on(fn ($p) => ($p['id'] ?? null) === '900099'))
            ->andReturn(['success' => true]);
        $lazada->shouldReceive('addVoucherSku')
            ->once()
            ->with('tok', 'MY', Mockery::on(function ($p) {
                return ($p['id'] ?? null) === '900099'
                    && ($p['sku_ids'] ?? null) === [777001];
            }))
            ->andReturn(['success' => true]);
        $this->app->instance(\App\Services\LazadaService::class, $lazada);

        $orders = Mockery::mock(\App\Services\LazadaOrderService::class)->makePartial();
        $orders->shouldReceive('ensureFreshToken')->andReturnNull();
        $this->app->instance(\App\Services\LazadaOrderService::class, $orders);

        $result = app(LazadaPromotionService::class)->createVoucher($user, $store->id, [
            'voucher_type' => 'COLLECTIBLE_VOUCHER',
            'apply' => 'SPECIFIC_PRODUCTS',
            'display_area' => 'REGULAR_CHANNEL',
            'period_start_time' => (int) (microtime(true) * 1000) + 3_600_000,
            'period_end_time' => (int) (microtime(true) * 1000) + 90_000_000,
            'voucher_discount_type' => 'MONEY_VALUE_OFF',
            'criteria_over_money' => '30',
            'offering_money_value_off' => '5',
            'limit' => 1,
            'issued' => 100,
            'voucher_name' => 'Attach SKUs',
            'sku_ids' => [777001],
        ]);

        $this->assertSame('900099', $result['id']);
        $this->assertTrue($result['activated']);
        $this->assertTrue($result['skus_attached']);
        $this->assertSame([777001], $result['sku_ids']);
    }
}
