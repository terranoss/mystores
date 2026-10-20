<?php

namespace Tests\Feature;

use App\Models\Product;
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
