<?php

namespace Tests\Feature;

use App\Jobs\UpdateShopeeStockJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaService;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class LazadaProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_lazada_stock_update_uses_lazada_transport_and_updates_local_product(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9001, 'Lazada MY');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 10001,
            'item_name' => 'Lazada Simple Item',
            'item_sku' => 'LAZ-001',
            'price' => 19.90,
            'stock' => 4,
            'raw_response' => [
                'skus' => [
                    ['SkuId' => 55001],
                ],
            ],
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->withArgs(function (string $accessToken, string $region, string $payloadXml) {
                $this->assertSame('test-access-token', $accessToken);
                $this->assertSame('my', strtolower($region));
                $this->assertStringContainsString('<SellerSku>LAZ-001</SellerSku>', $payloadXml);
                $this->assertStringContainsString('<Quantity>12</Quantity>', $payloadXml);
                return true;
            })
            ->andReturn(['code' => '0']);
        $this->app->instance(LazadaService::class, $lazada);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('forStore');
        $shopee->shouldNotReceive('clearStoreCache');
        $shopee->shouldNotReceive('refreshStoreToken');
        $shopee->shouldNotReceive('invalidateStore');
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock", [
            'stock_list' => [
                ['seller_stock' => [['stock' => 12]]],
            ],
        ]);

        $response->assertOk()->assertJsonPath('product.stock', 12);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 12,
        ]);
    }

    public function test_lazada_variant_price_update_persists_only_after_success(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9002, 'Lazada SG', 'sg');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 10002,
            'item_name' => 'Lazada Variant Item',
            'item_sku' => 'LAZ-VAR',
            'price' => 12.00,
            'stock' => 7,
            'has_variants' => true,
        ]);
        $variantA = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 88001,
            'name' => 'Blue',
            'model_sku' => 'LAZ-VAR-BLUE',
            'price' => 12.00,
            'stock' => 3,
        ]);
        $variantB = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 88002,
            'name' => 'Red',
            'model_sku' => 'LAZ-VAR-RED',
            'price' => 14.00,
            'stock' => 4,
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->withArgs(function (string $accessToken, string $region, string $payloadXml) {
                $this->assertSame('test-access-token', $accessToken);
                $this->assertSame('sg', strtolower($region));
                $this->assertStringContainsString('<SkuId>88001</SkuId>', $payloadXml);
                $this->assertStringContainsString('<Price>21.50</Price>', $payloadXml);
                $this->assertStringContainsString('<SkuId>88002</SkuId>', $payloadXml);
                $this->assertStringContainsString('<Price>25.75</Price>', $payloadXml);
                return true;
            })
            ->andReturn(['code' => '0']);
        $this->app->instance(LazadaService::class, $lazada);
        $this->app->instance(ShopeeService::class, Mockery::mock(ShopeeService::class));

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/price", [
            'price_list' => [
                ['model_id' => $variantA->model_id, 'original_price' => 21.50],
                ['model_id' => $variantB->model_id, 'original_price' => 25.75],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantA->id,
            'price' => 21.50,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variantB->id,
            'price' => 25.75,
        ]);
        $product->refresh();
        $this->assertSame('21.50', (string) $product->price);
        $this->assertSame(7, $product->stock);
    }

    public function test_lazada_variant_price_update_does_not_touch_local_db_when_api_fails(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9003, 'Lazada Failure');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 10003,
            'item_name' => 'Failing Variant Item',
            'item_sku' => 'LAZ-FAIL',
            'price' => 18.00,
            'stock' => 9,
            'has_variants' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 99001,
            'name' => 'Default',
            'model_sku' => 'LAZ-FAIL-DEFAULT',
            'price' => 18.00,
            'stock' => 9,
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->andThrow(new \RuntimeException('Lazada rejected update'));
        $this->app->instance(LazadaService::class, $lazada);
        $this->app->instance(ShopeeService::class, Mockery::mock(ShopeeService::class));

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/price", [
            'price_list' => [
                ['model_id' => $variant->model_id, 'original_price' => 30.00],
            ],
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'price' => 18.00,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 18.00,
        ]);
    }

    public function test_mixed_marketplace_bulk_stock_update_keeps_lazada_out_of_shopee_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $shopeeStore = $this->makeStore($user, 'shopee', 9004, 'Shopee Main');
        $lazadaStore = $this->makeStore($user, 'lazada', 9005, 'Lazada Main');

        $shopeeProduct = Product::create([
            'store_id' => $shopeeStore->id,
            'item_id' => 10004,
            'item_name' => 'Shared SKU Shopee',
            'item_sku' => 'SHARED-1',
            'price' => 10.00,
            'stock' => 1,
        ]);
        $lazadaProduct = Product::create([
            'store_id' => $lazadaStore->id,
            'item_id' => 10005,
            'item_name' => 'Shared SKU Lazada',
            'item_sku' => 'SHARED-1',
            'price' => 11.00,
            'stock' => 2,
            'raw_response' => [
                'skus' => [
                    ['SkuId' => 775501],
                ],
            ],
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->withArgs(function (string $accessToken, string $region, string $payloadXml) {
                $this->assertSame('test-access-token', $accessToken);
                $this->assertStringContainsString('<SellerSku>SHARED-1</SellerSku>', $payloadXml);
                $this->assertStringContainsString('<Quantity>15</Quantity>', $payloadXml);
                return true;
            })
            ->andReturn(['code' => '0']);
        $this->app->instance(LazadaService::class, $lazada);
        $this->app->instance(ShopeeService::class, Mockery::mock(ShopeeService::class));

        $response = $this->actingAs($user)->putJson('/api/products/stock-by-sku', [
            'sku' => 'SHARED-1',
            'stock' => 15,
        ]);

        $response->assertOk();
        Queue::assertPushed(UpdateShopeeStockJob::class, 1);
        Queue::assertPushed(UpdateShopeeStockJob::class, function (UpdateShopeeStockJob $job) use ($shopeeProduct) {
            $reflection = new \ReflectionClass($job);
            $productId = $reflection->getProperty('productId');
            $productId->setAccessible(true);
            return $productId->getValue($job) === $shopeeProduct->id;
        });
        $this->assertDatabaseHas('products', [
            'id' => $lazadaProduct->id,
            'stock' => 15,
        ]);
    }

    public function test_missing_lazada_credentials_fail_clearly(): void
    {
        config([
            'lazada.app_key' => null,
            'lazada.app_secret' => null,
        ]);

        $service = app(LazadaService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing Lazada configuration: lazada.app_key');

        $service->getAppKey();
    }

    private function makeStore(
        User $user,
        string $platform,
        int $shopId,
        string $shopName,
        string $region = 'my'
    ): ShopeeStore {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $user->workspaceMemberships()->first()?->workspace_id,
            'platform' => $platform,
            'shop_id' => $shopId,
            'shop_name' => $shopName,
            'region' => $region,
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('test-access-token'),
            'refresh_token' => encrypt('test-refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addDays(90),
            'last_refreshed_at' => now(),
        ]);

        return $store->fresh('token');
    }
}
