<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Services\LazadaOrderService;
use App\Services\LazadaProductAdapter;
use App\Services\LazadaService;
use App\Services\MarketplaceProductGateway;
use App\Services\ShopeeProductAdapter;
use App\Services\ShopeeService;
use GuzzleHttp\RequestOptions;
use Mockery;
use Tests\TestCase;

class MarketplaceProductGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_shopee_adapter_pushes_stock_with_the_existing_payload_shape(): void
    {
        $client = $this->fakeShopeeClient(['ok' => true]);
        $adapter = new ShopeeProductAdapter($this->shopeeServiceReturning($client));

        $store = new ShopeeStore(['platform' => 'shopee', 'region' => 'my']);
        $product = new Product(['item_id' => '123', 'item_sku' => 'SKU-1']);

        $response = $adapter->pushStock($store, $product, [
            ['model_id' => '456', 'seller_stock' => [['stock' => '7']]],
        ]);

        $this->assertSame(['ok' => true], $response);
        $this->assertSame([
            [
                'method' => 'POST',
                'path' => 'product/update_stock',
                'options' => [
                    RequestOptions::JSON => [
                        'item_id' => 123,
                        'stock_list' => [
                            ['model_id' => 456, 'seller_stock' => [['stock' => 7]]],
                        ],
                    ],
                ],
            ],
        ], $client->Product->calls);
    }

    public function test_shopee_adapter_pushes_price_with_the_existing_payload_shape(): void
    {
        $client = $this->fakeShopeeClient(['ok' => true]);
        $adapter = new ShopeeProductAdapter($this->shopeeServiceReturning($client));

        $store = new ShopeeStore(['platform' => 'shopee', 'region' => 'my']);
        $product = new Product(['item_id' => '123', 'item_sku' => 'SKU-1']);

        $adapter->pushPrice($store, $product, [
            ['model_id' => '456', 'original_price' => '19.90'],
        ]);

        $this->assertSame([
            [
                'method' => 'POST',
                'path' => 'product/update_price',
                'options' => [
                    RequestOptions::JSON => [
                        'item_id' => 123,
                        'price_list' => [
                            ['model_id' => 456, 'original_price' => 19.90],
                        ],
                    ],
                ],
            ],
        ], $client->Product->calls);
    }

    public function test_shopee_adapter_surfaces_the_api_error_message(): void
    {
        $client = $this->fakeShopeeClient(['error' => 'error_param', 'message' => 'stock rejected']);
        $adapter = new ShopeeProductAdapter($this->shopeeServiceReturning($client));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Shopee API Error: stock rejected');

        $adapter->pushStock(new ShopeeStore(['platform' => 'shopee']), new Product(['item_id' => 1]), [
            ['seller_stock' => [['stock' => 1]]],
        ]);
    }

    public function test_shopee_adapter_fetches_item_lists_details_and_models(): void
    {
        $client = $this->fakeShopeeClient([], [
            'getItemList' => ['item_list' => [['item_id' => 11]]],
            'getItemBaseInfo' => ['item_list' => [['item_id' => 11]]],
            'getModelList' => ['model' => [['model_id' => 22]]],
        ]);
        $adapter = new ShopeeProductAdapter($this->shopeeServiceReturning($client));
        $store = new ShopeeStore(['platform' => 'shopee']);

        $this->assertSame(['item_list' => [['item_id' => 11]]], $adapter->fetchItemList($store, [
            'offset' => 100,
            'page_size' => 50,
            'item_status' => 'NORMAL',
        ]));
        $this->assertSame(['item_list' => [['item_id' => 11]]], $adapter->fetchItemDetails($store, ['11', 12]));
        $this->assertSame(['model' => [['model_id' => 22]]], $adapter->fetchModels($store, 11));

        $this->assertSame([
            ['method' => 'getItemList', 'args' => ['offset' => 100, 'page_size' => 50, 'item_status' => 'NORMAL']],
            ['method' => 'getItemBaseInfo', 'args' => [[11, 12]]],
            ['method' => 'getModelList', 'args' => ['item_id' => 11]],
        ], $client->Product->calls);
    }

    public function test_lazada_adapter_refreshes_the_token_before_pushing_and_uses_the_refreshed_token(): void
    {
        $store = $this->lazadaStore('refreshed-access');
        $orderService = Mockery::mock(LazadaOrderService::class);
        $lazada = Mockery::mock(LazadaService::class)->makePartial();

        $orderService->shouldReceive('ensureFreshToken')
            ->once()
            ->with($store)
            ->ordered();
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->withArgs(function (string $accessToken, string $region, string $payloadXml) {
                $this->assertSame('refreshed-access', $accessToken);
                $this->assertSame('my', $region);
                $this->assertStringContainsString('<SellerSku>LAZ-1</SellerSku>', $payloadXml);
                $this->assertStringContainsString('<Quantity>12</Quantity>', $payloadXml);

                return true;
            })
            ->ordered()
            ->andReturn(['code' => '0']);

        $adapter = new LazadaProductAdapter($lazada, $orderService);
        $product = new Product([
            'item_id' => 10001,
            'item_sku' => 'LAZ-1',
            'has_variants' => false,
            'raw_response' => ['skus' => [['SkuId' => 55001]]],
        ]);

        $response = $adapter->pushStock($store, $product, [
            ['seller_stock' => [['stock' => 12]]],
        ]);

        $this->assertSame(['code' => '0'], $response);
    }

    public function test_lazada_adapter_refreshes_before_fetching_an_item_page(): void
    {
        $store = $this->lazadaStore('page-access');
        $orderService = Mockery::mock(LazadaOrderService::class);
        $lazada = Mockery::mock(LazadaService::class);

        $orderService->shouldReceive('ensureFreshToken')
            ->once()
            ->with($store)
            ->ordered();
        $lazada->shouldReceive('getProducts')
            ->once()
            ->with('page-access', 'my', 100, 50, 'all')
            ->ordered()
            ->andReturn(['data' => ['products' => [['item_id' => 7]]]]);

        $adapter = new LazadaProductAdapter($lazada, $orderService);

        $this->assertSame(
            ['data' => ['products' => [['item_id' => 7]]]],
            $adapter->fetchItemList($store, ['offset' => 100, 'limit' => 50, 'filter' => 'all']),
        );
    }

    public function test_lazada_adapter_fetches_item_details_and_models(): void
    {
        $store = $this->lazadaStore('detail-access');
        $orderService = Mockery::mock(LazadaOrderService::class);
        $lazada = Mockery::mock(LazadaService::class);

        $orderService->shouldReceive('ensureFreshToken')->twice()->with($store);
        $lazada->shouldReceive('getItemDetail')
            ->twice()
            ->with('detail-access', 'my', 7)
            ->andReturn(['data' => ['item_id' => 7, 'skus' => [['SkuId' => 7001]]]]);

        $adapter = new LazadaProductAdapter($lazada, $orderService);

        $this->assertSame(
            [['data' => ['item_id' => 7, 'skus' => [['SkuId' => 7001]]]]],
            $adapter->fetchItemDetails($store, [7]),
        );
        $this->assertSame(
            [['SkuId' => 7001]],
            $adapter->fetchModels($store, 7),
        );
    }

    public function test_lazada_adapter_surfaces_the_api_error_message(): void
    {
        $store = $this->lazadaStore('error-access');
        $orderService = Mockery::mock(LazadaOrderService::class);
        $lazada = Mockery::mock(LazadaService::class);

        $orderService->shouldReceive('ensureFreshToken')->once()->with($store);
        $lazada->shouldReceive('buildPriceQuantitySkuUpdates')
            ->once()
            ->andReturn([['ItemId' => 1, 'SellerSku' => 'SKU-1', 'Quantity' => 3]]);
        $lazada->shouldReceive('buildPriceQuantityPayload')->once()->andReturn('<Request/>');
        $lazada->shouldReceive('updatePriceQuantity')
            ->once()
            ->andReturn(['code' => '500', 'message' => 'sku rejected']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sku rejected');

        (new LazadaProductAdapter($lazada, $orderService))->pushStock(
            $store,
            new Product(['item_id' => 1]),
            [['seller_stock' => [['stock' => 3]]]],
        );
    }

    public function test_gateway_dispatches_to_the_adapter_for_the_store_platform(): void
    {
        $shopee = Mockery::mock(ShopeeProductAdapter::class);
        $lazada = Mockery::mock(LazadaProductAdapter::class);
        $gateway = new MarketplaceProductGateway($shopee, $lazada);

        $product = new Product(['item_id' => 1]);
        $stockList = [['seller_stock' => [['stock' => 2]]]];

        $shopee->shouldReceive('pushStock')
            ->twice()
            ->with(Mockery::on(fn ($s) => $s->platform !== 'lazada'), $product, $stockList)
            ->andReturn(['adapter' => 'shopee']);
        $lazada->shouldReceive('pushStock')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->platform === 'lazada'), $product, $stockList)
            ->andReturn(['adapter' => 'lazada']);

        $this->assertSame(
            ['adapter' => 'shopee'],
            $gateway->pushStock(new ShopeeStore(['platform' => 'shopee']), $product, $stockList),
        );
        $this->assertSame(
            ['adapter' => 'lazada'],
            $gateway->pushStock(new ShopeeStore(['platform' => 'lazada']), $product, $stockList),
        );
        $this->assertSame(
            ['adapter' => 'shopee'],
            $gateway->pushStock(new ShopeeStore(), $product, $stockList),
        );
    }

    public function test_lazada_adapter_does_not_call_the_product_api_when_refresh_fails(): void
    {
        $store = $this->lazadaStore('stale-access');
        $orderService = Mockery::mock(LazadaOrderService::class);
        $lazada = Mockery::mock(LazadaService::class);

        $orderService->shouldReceive('ensureFreshToken')
            ->once()
            ->with($store)
            ->andThrow(new \RuntimeException('refresh rejected'));
        $lazada->shouldNotReceive('updatePriceQuantity');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refresh rejected');

        (new LazadaProductAdapter($lazada, $orderService))->pushSkuUpdates($store, [
            ['ItemId' => 1, 'SellerSku' => 'SKU-1', 'Quantity' => 3],
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, array<string, mixed>>  $fetchResponses
     */
    private function fakeShopeeClient(array $response = [], array $fetchResponses = []): object
    {
        return new class($response, $fetchResponses)
        {
            public object $Product;

            /**
             * @param  array<string, mixed>  $response
             * @param  array<string, array<string, mixed>>  $fetchResponses
             */
            public function __construct(array $response, array $fetchResponses)
            {
                $this->Product = new class($response, $fetchResponses)
                {
                    /** @var list<array<string, mixed>> */
                    public array $calls = [];

                    /**
                     * @param  array<string, mixed>  $response
                     * @param  array<string, array<string, mixed>>  $fetchResponses
                     */
                    public function __construct(
                        private readonly array $response,
                        private readonly array $fetchResponses,
                    ) {}

                    /**
                     * @param  array<string, mixed>  $options
                     * @return array<string, mixed>
                     */
                    public function call(string $method, string $path, array $options = []): array
                    {
                        $this->calls[] = compact('method', 'path', 'options');

                        return $this->response;
                    }

                    /**
                     * @param  array<string, mixed>  $params
                     * @return array<string, mixed>
                     */
                    public function getItemList(array $params): array
                    {
                        $this->calls[] = ['method' => 'getItemList', 'args' => $params];

                        return $this->fetchResponses['getItemList'] ?? [];
                    }

                    /**
                     * @param  list<int>  $itemIds
                     * @return array<string, mixed>
                     */
                    public function getItemBaseInfo(array $itemIds): array
                    {
                        $this->calls[] = ['method' => 'getItemBaseInfo', 'args' => [$itemIds]];

                        return $this->fetchResponses['getItemBaseInfo'] ?? [];
                    }

                    /**
                     * @param  array<string, mixed>  $params
                     * @return array<string, mixed>
                     */
                    public function getModelList(array $params): array
                    {
                        $this->calls[] = ['method' => 'getModelList', 'args' => $params];

                        return $this->fetchResponses['getModelList'] ?? [];
                    }
                };
            }
        };
    }

    private function shopeeServiceReturning(object $client): ShopeeService
    {
        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('callWithTokenRetry')
            ->andReturnUsing(function ($store, callable $callback) use ($client) {
                return $callback($client);
            });

        return $shopee;
    }

    private function lazadaStore(string $accessToken): ShopeeStore
    {
        $store = new ShopeeStore(['platform' => 'lazada', 'region' => 'my']);
        $store->setRelation('token', new ShopeeToken([
            'access_token' => encrypt($accessToken),
            'refresh_token' => encrypt('refresh'),
        ]));

        return $store;
    }
}
