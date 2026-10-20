<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaService;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InventoryAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function bindFakeShopeeClient(): void
    {
        $client = new class
        {
            public object $Product;

            public function __construct()
            {
                $this->Product = new class
                {
                    public function call(string $method, string $path, array $options = []): array
                    {
                        return [];
                    }
                };
            }
        };

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('callWithTokenRetry')
            ->andReturnUsing(function ($store, callable $callback, ?callable $failureFactory = null) use ($client) {
                return $callback($client);
            });
        $this->app->instance(ShopeeService::class, $shopee);
    }

    private function makeStore(User $user, string $platform = 'shopee', int $shopId = 9001, string $region = 'my'): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $user->workspaceMemberships()->first()?->workspace_id,
            'platform' => $platform,
            'shop_id' => $shopId,
            'shop_name' => "Test {$platform} Store",
            'country' => 'MY',
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

        return $store;
    }

    public function test_local_staged_simple_stock_and_price_creates_inventory_logs(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 70001,
            'item_name' => 'Simple Plant',
            'item_sku' => 'PLANT-SIMPLE-1',
            'price' => 25.00,
            'stock' => 10,
            'has_variants' => false,
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock-price", [
            'stock_list' => [
                ['seller_stock' => [['stock' => 18]]],
            ],
            'price_list' => [
                ['original_price' => 29.50],
            ],
            'mode' => 'local',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'sku' => 'PLANT-SIMPLE-1',
            'old_stock' => 10,
            'new_stock' => 18,
            'source' => 'local_staged',
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'sku' => 'PLANT-SIMPLE-1',
            'old_price' => 25.00,
            'new_price' => 29.50,
            'source' => 'local_staged',
            'status' => 'success',
        ]);
    }

    public function test_local_staged_variant_stock_and_price_creates_inventory_logs(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 70002,
            'item_name' => 'Variant Plant',
            'item_sku' => 'PLANT-VAR-PARENT',
            'price' => 15.00,
            'stock' => 20,
            'has_variants' => true,
        ]);

        $variant1 = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 8801,
            'name' => 'Small',
            'model_sku' => 'PLANT-VAR-S',
            'price' => 15.00,
            'stock' => 8,
        ]);

        $variant2 = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 8802,
            'name' => 'Large',
            'model_sku' => 'PLANT-VAR-L',
            'price' => 20.00,
            'stock' => 12,
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock-price", [
            'stock_list' => [
                ['model_id' => 8801, 'seller_stock' => [['stock' => 14]]],
                ['model_id' => 8802, 'seller_stock' => [['stock' => 16]]],
            ],
            'price_list' => [
                ['model_id' => 8801, 'original_price' => 18.00],
                ['model_id' => 8802, 'original_price' => 24.00],
            ],
            'mode' => 'local',
        ]);

        $response->assertOk();

        // Check variant 1 stock log
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $variant1->id,
            'sku' => 'PLANT-VAR-S',
            'old_stock' => 8,
            'new_stock' => 14,
            'source' => 'local_staged',
            'status' => 'success',
        ]);

        // Check variant 1 price log
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $variant1->id,
            'sku' => 'PLANT-VAR-S',
            'old_price' => 15.00,
            'new_price' => 18.00,
            'source' => 'local_staged',
            'status' => 'success',
        ]);

        // Check variant 2 stock log
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $variant2->id,
            'sku' => 'PLANT-VAR-L',
            'old_stock' => 12,
            'new_stock' => 16,
            'source' => 'local_staged',
            'status' => 'success',
        ]);

        // Check variant 2 price log
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $variant2->id,
            'sku' => 'PLANT-VAR-L',
            'old_price' => 20.00,
            'new_price' => 24.00,
            'source' => 'local_staged',
            'status' => 'success',
        ]);
    }

    public function test_direct_shopee_stock_push_creates_one_success_log(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 70004,
            'item_name' => 'Direct Push Plant',
            'item_sku' => 'PLANT-DIRECT-1',
            'price' => 10.00,
            'stock' => 10,
            'has_variants' => false,
            'needs_push' => true,
        ]);

        $this->bindFakeShopeeClient();

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock", [
            'stock_list' => [
                ['seller_stock' => [['stock' => 18]]],
            ],
        ]);

        $response->assertOk();

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('success', $logs[0]->status);
        $this->assertSame('PLANT-DIRECT-1', $logs[0]->sku);
        $this->assertSame(10, (int) $logs[0]->old_stock);
        $this->assertSame(18, (int) $logs[0]->new_stock);
        $this->assertSame('user', $logs[0]->source);
        $this->assertFalse($product->fresh()->needs_push);
    }

    public function test_queued_shopee_stock_push_leaves_one_success_row(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'shopee', 9006);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 70005,
            'item_name' => 'Queued Push Plant',
            'item_sku' => 'PLANT-QUEUED-1',
            'price' => 10.00,
            'stock' => 10,
            'has_variants' => false,
            'needs_push' => true,
        ]);

        $this->bindFakeShopeeClient();

        // QUEUE_CONNECTION=sync, so UpdateShopeeStockJob runs inline.
        $response = $this->actingAs($user)->putJson('/api/products/stock-by-sku', [
            'sku' => 'PLANT-QUEUED-1',
            'stock' => 18,
        ]);

        $response->assertOk();

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('success', $logs[0]->status);
        $this->assertSame(10, (int) $logs[0]->old_stock);
        $this->assertSame(18, (int) $logs[0]->new_stock);
        $this->assertSame(18, (int) $product->fresh()->stock);
    }

    public function test_lazada_product_stock_and_price_update_creates_inventory_logs(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9005, 'my');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 70003,
            'item_name' => 'Lazada Simple Audit Item',
            'item_sku' => 'LAZ-AUDIT-1',
            'price' => 30.00,
            'stock' => 5,
            'has_variants' => false,
            'raw_response' => [
                'skus' => [
                    ['SkuId' => 66001],
                ],
            ],
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->andReturn(['code' => '0']);
        $this->app->instance(LazadaService::class, $lazada);

        $shopee = Mockery::mock(ShopeeService::class);
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock-price", [
            'stock_list' => [
                ['seller_stock' => [['stock' => 22]]],
            ],
            'price_list' => [
                ['original_price' => 35.00],
            ],
            'mode' => 'push',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'sku' => 'LAZ-AUDIT-1',
            'old_stock' => 5,
            'new_stock' => 22,
            'old_price' => 30.00,
            'new_price' => 35.00,
            'source' => 'user',
            'status' => 'success',
        ]);
    }
}
