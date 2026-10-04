<?php

namespace Tests\Feature;

use App\Jobs\UpdateShopeePriceJob;
use App\Jobs\UpdateShopeeStockJob;
use App\Models\PosInventory;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaService;
use App\Services\ProductService;
use App\Services\ShopeeService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ErpPushImmediateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeStore(User $user, string $platform, int $shopId): ShopeeStore
    {
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'shop_id' => $shopId,
            'shop_name' => strtoupper($platform).' Shop',
            'platform' => $platform,
            'is_active' => true,
            'region' => 'my',
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('test-access-token'),
            'refresh_token' => encrypt('test-refresh-token'),
            'access_token_expires_at' => now()->addDay(),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addDays(90),
            'last_refreshed_at' => now(),
        ]);

        return $store->fresh(['token']);
    }

    private function partialProductService(): ProductService
    {
        /** @var ProductService $service */
        $service = Mockery::mock(ProductService::class, [
            app(ShopeeService::class),
            app(LazadaService::class),
            app(WorkspaceAccess::class),
        ])->makePartial();
        $this->app->instance(ProductService::class, $service);

        return $service;
    }

    public function test_update_stock_by_sku_calls_shopee_inline_and_surfaces_api_error(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'shopee', 7001);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 1001,
            'item_name' => 'Shopee Item',
            'item_sku' => 'SKU-PUSH-1',
            'price' => 10,
            'stock' => 3,
        ]);

        $service = $this->partialProductService();
        $service->shouldReceive('updateStock')
            ->once()
            ->withArgs(fn (User $u, int $id) => $u->id === $user->id && $id === $product->id)
            ->andThrow(new \Exception('Shopee API Error: rate limited'));

        $results = $service->updateStockBySku($user, 'SKU-PUSH-1', 9, 'push');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('error: Shopee API Error: rate limited', $results[0]);
        $this->assertStringContainsString('(shopee)', $results[0]);
        $this->assertStringNotContainsString(': updated', $results[0]);
        Bus::assertNotDispatched(UpdateShopeeStockJob::class);
    }

    public function test_update_stock_by_sku_surfaces_lazada_api_error(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 7002);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 2001,
            'item_name' => 'Lazada Item',
            'item_sku' => 'SKU-PUSH-2',
            'price' => 11,
            'stock' => 4,
            'raw_response' => ['skus' => [['SkuId' => 55]]],
        ]);

        $service = $this->partialProductService();
        $service->shouldReceive('updateStock')
            ->once()
            ->andThrow(new \Exception('Lazada API Error: invalid sku'));

        $results = $service->updateStockBySku($user, 'SKU-PUSH-2', 8, 'push');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('error: Lazada API Error: invalid sku', $results[0]);
        $this->assertStringContainsString('(lazada)', $results[0]);
        Bus::assertNotDispatched(UpdateShopeeStockJob::class);
    }

    public function test_update_price_by_sku_does_not_dispatch_shopee_job(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'shopee', 7003);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 3001,
            'item_name' => 'Shopee Price Item',
            'item_sku' => 'SKU-PUSH-3',
            'price' => 15,
            'stock' => 2,
        ]);

        $service = $this->partialProductService();
        $service->shouldReceive('updatePrice')
            ->once()
            ->andReturn($product);

        $results = $service->updatePriceBySku($user, 'SKU-PUSH-3', 19.5, 'push');

        $this->assertSame(["Store #{$store->id} (shopee): updated"], $results);
        Bus::assertNotDispatched(UpdateShopeePriceJob::class);
    }

    public function test_erp_push_selected_reports_error_counts(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);
        $store = $this->makeStore($user, 'shopee', 7004);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 4001,
            'item_name' => 'ERP Item',
            'item_sku' => 'ERP-SKU-1',
            'price' => 20,
            'stock' => 1,
        ]);
        PosInventory::create([
            'workspace_id' => $workspace->id,
            'pos_product_id' => 1,
            'sku' => 'ERP-SKU-1',
            'name' => 'ERP Item',
            'price' => 20,
            'total_stock' => 7,
            'synced_at' => now(),
        ]);

        $service = $this->partialProductService();
        $service->shouldReceive('updateStock')
            ->once()
            ->andThrow(new \Exception('Shopee API Error: boom'));

        $res = $this->actingAs($user)->postJson('/api/erp/push-selected', [
            'skus' => ['ERP-SKU-1'],
            'push_stock' => true,
            'push_price' => false,
        ]);

        $res->assertOk();
        $this->assertSame(0, $res->json('ok_count'));
        $this->assertSame(1, $res->json('error_count'));
        $this->assertStringContainsString('1 failed', $res->json('message'));
        $this->assertStringNotContainsString('async', strtolower($res->json('message')));
        $this->assertStringContainsString('error:', $res->json('results.ERP-SKU-1.stock.0'));
        Bus::assertNotDispatched(UpdateShopeeStockJob::class);
    }
}
