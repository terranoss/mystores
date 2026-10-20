<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaService;
use App\Services\MarketplaceProductGateway;
use App\Services\ProductService;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductServiceGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_shopee_stock_push_goes_through_the_injected_gateway(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 71001,
            'item_name' => 'Gateway Shopee Item',
            'item_sku' => 'GW-SHOPEE-1',
            'price' => 10.00,
            'stock' => 10,
            'has_variants' => false,
        ]);

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('pushStock')
            ->once()
            ->with(
                Mockery::on(fn ($s) => $s->id === $store->id),
                Mockery::on(fn ($p) => $p->id === $product->id),
                [['seller_stock' => [['stock' => 18]]]],
            )
            ->andReturn([]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('callWithTokenRetry');

        $service = new ProductService($shopee, null, null, null, $gateway);
        $service->updateStock($user, $product->id, [['seller_stock' => [['stock' => 18]]]]);

        $this->assertSame(18, (int) $product->fresh()->stock);
    }

    public function test_lazada_stock_push_goes_through_the_injected_gateway(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 71002,
            'item_name' => 'Gateway Lazada Item',
            'item_sku' => 'GW-LAZADA-1',
            'price' => 10.00,
            'stock' => 5,
            'has_variants' => false,
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('buildPriceQuantitySkuUpdates')
            ->once()
            ->andReturn([['ItemId' => 71002, 'SellerSku' => 'GW-LAZADA-1', 'Quantity' => 22]]);
        $lazada->shouldNotReceive('updatePriceQuantity');

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('pushSkuUpdates')
            ->once()
            ->with(
                Mockery::on(fn ($s) => $s->id === $store->id),
                [['ItemId' => 71002, 'SellerSku' => 'GW-LAZADA-1', 'Quantity' => 22]],
            )
            ->andReturn(['code' => '0']);

        $service = new ProductService(Mockery::mock(ShopeeService::class), $lazada, null, null, $gateway);
        $service->updateStock($user, $product->id, [['seller_stock' => [['stock' => 22]]]]);

        $this->assertSame(22, (int) $product->fresh()->stock);
    }

    public function test_shopee_product_sync_fetches_through_the_injected_gateway(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('fetchItemList')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id), [
                'offset' => 0,
                'page_size' => 100,
                'item_status' => 'NORMAL',
            ])
            ->andReturn(['item_list' => [['item_id' => 71003]]]);
        $gateway->shouldReceive('fetchItemDetails')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id), [71003])
            ->andReturn(['item_list' => [[
                'item_id' => 71003,
                'item_name' => 'Pulled Through Gateway',
                'item_sku' => 'GW-PULL-1',
                'price' => 9.50,
                'stock' => 4,
            ]]]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('callWithTokenRetry');

        (new ProductService($shopee, null, null, null, $gateway))->syncForStore($store);

        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'item_id' => 71003,
            'item_name' => 'Pulled Through Gateway',
            'stock' => 4,
        ]);
    }

    public function test_shopee_pull_records_one_sync_row_for_a_changed_simple_product(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 71004,
            'item_name' => 'Before Pull',
            'item_sku' => 'GW-PULL-EXISTING',
            'price' => 8.00,
            'stock' => 2,
            'has_variants' => false,
        ]);

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('fetchItemList')->once()->andReturn(['item_list' => [['item_id' => 71004]]]);
        $gateway->shouldReceive('fetchItemDetails')->once()->andReturn(['item_list' => [[
            'item_id' => 71004,
            'item_name' => 'After Pull',
            'item_sku' => 'GW-PULL-EXISTING',
            'price' => 9.50,
            'stock' => 4,
        ]]]);

        (new ProductService(Mockery::mock(ShopeeService::class), null, null, null, $gateway))->syncForStore($store);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('success', $logs[0]->status);
        $this->assertSame('sync', $logs[0]->source);
        $this->assertNull($logs[0]->product_variant_id);
        $this->assertSame(2, (int) $logs[0]->old_stock);
        $this->assertSame(4, (int) $logs[0]->new_stock);
        $this->assertSame(8.0, (float) $logs[0]->old_price);
        $this->assertSame(9.5, (float) $logs[0]->new_price);
    }

    public function test_shopee_pull_records_one_sync_row_per_changed_variant(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 71005,
            'item_name' => 'Variant Before Pull',
            'item_sku' => 'GW-PULL-VAR',
            'price' => 10.00,
            'stock' => 7,
            'has_variants' => true,
        ]);
        $small = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 9101,
            'model_sku' => 'GW-PULL-VAR-S',
            'price' => 10.00,
            'stock' => 3,
        ]);
        $large = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 9102,
            'model_sku' => 'GW-PULL-VAR-L',
            'price' => 12.00,
            'stock' => 4,
        ]);

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('fetchItemList')->once()->andReturn(['item_list' => [['item_id' => 71005]]]);
        $gateway->shouldReceive('fetchItemDetails')->once()->andReturn(['item_list' => [[
            'item_id' => 71005,
            'item_name' => 'Variant After Pull',
            'item_sku' => 'GW-PULL-VAR',
            'price' => 10.00,
            'stock' => 9,
            'has_variants' => true,
        ]]]);
        $gateway->shouldReceive('fetchModels')->once()->andReturn(['model' => [
            ['model_id' => 9101, 'model_sku' => 'GW-PULL-VAR-S', 'price' => 11.00, 'stock' => 5],
            ['model_id' => 9102, 'model_sku' => 'GW-PULL-VAR-L', 'price' => 13.00, 'stock' => 4],
        ]]);

        (new ProductService(Mockery::mock(ShopeeService::class), null, null, null, $gateway))->syncForStore($store);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn (InventoryLog $log) => $log->source === 'sync'));
        $this->assertSame(0, $logs->whereNull('product_variant_id')->count());
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $small->id,
            'old_stock' => 3,
            'new_stock' => 5,
            'old_price' => 10.00,
            'new_price' => 11.00,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $large->id,
            'old_stock' => 4,
            'new_stock' => 4,
            'old_price' => 12.00,
            'new_price' => 13.00,
        ]);
    }

    public function test_lazada_pull_records_one_sync_row_for_a_changed_simple_product(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada');
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 71006,
            'item_name' => 'Lazada Before Pull',
            'item_sku' => 'GW-LAZ-PULL',
            'price' => 7.00,
            'stock' => 3,
            'has_variants' => false,
        ]);

        $gateway = Mockery::mock(MarketplaceProductGateway::class);
        $gateway->shouldReceive('fetchItemList')->once()->andReturn([
            'data' => [
                'products' => [[
                    'item_id' => 71006,
                    'attributes' => ['name' => 'Lazada After Pull'],
                    'skus' => [[
                        'SkuId' => 9201,
                        'SellerSku' => 'GW-LAZ-PULL',
                        'price' => 8.25,
                        'quantity' => 9,
                    ]],
                ]],
                'total_products' => 1,
            ],
        ]);

        (new ProductService(Mockery::mock(ShopeeService::class), Mockery::mock(LazadaService::class), null, null, $gateway))
            ->syncForStore($store);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('sync', $logs[0]->source);
        $this->assertSame(3, (int) $logs[0]->old_stock);
        $this->assertSame(9, (int) $logs[0]->new_stock);
        $this->assertSame(7.0, (float) $logs[0]->old_price);
        $this->assertSame(8.25, (float) $logs[0]->new_price);
    }

    private function makeStore(User $user, string $platform = 'shopee'): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'shop_id' => random_int(60000, 69999),
            'shop_name' => "Gateway {$platform} Store",
            'region' => 'my',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addDays(10),
            'refresh_token_expires_at' => now()->addDays(100),
            'auth_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now(),
        ]);

        return $store->fresh('token');
    }
}
