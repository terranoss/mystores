<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\LogisticsService;
use App\Services\PackingListPdfService;
use App\Services\ShopeeService;
use Dompdf\Dompdf;
use EcomPHP\Shopee\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShippingLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_document_type_prefers_normal_air_waybill(): void
    {
        $service = $this->app->make(LogisticsService::class);

        $this->assertSame(
            'NORMAL_AIR_WAYBILL',
            $service->resolveDocumentType(
                ['THERMAL_AIR_WAYBILL', 'NORMAL_AIR_WAYBILL'],
                'THERMAL_AIR_WAYBILL'
            )
        );
    }

    public function test_resolve_document_type_uses_suggestion_when_normal_unavailable(): void
    {
        $service = $this->app->make(LogisticsService::class);

        $this->assertSame(
            'THERMAL_AIR_WAYBILL',
            $service->resolveDocumentType(
                ['THERMAL_AIR_WAYBILL', 'THERMAL_JOB_AIR_WAYBILL'],
                'THERMAL_AIR_WAYBILL'
            )
        );
    }

    public function test_resolve_document_type_falls_back_to_thermal(): void
    {
        $service = $this->app->make(LogisticsService::class);

        $this->assertSame('THERMAL_AIR_WAYBILL', $service->resolveDocumentType([], null));
    }

    public function test_packing_list_pdf_includes_item_lines(): void
    {
        $store = $this->makeStore();
        $order = $this->makeOrder($store);

        $pdf = $this->app->make(PackingListPdfService::class)->generate($order);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
        // Dompdf embeds text as UTF-16BE hex pairs inside the content stream
        $this->assertTrue(
            str_contains($pdf, 'Monstera') || str_contains($pdf, "\x00M\x00o\x00n\x00s\x00t\x00e\x00r\x00a"),
            'Packing list PDF should include the order item name'
        );
        $this->assertTrue(
            str_contains($pdf, '260727QEBVBD8F') || str_contains($pdf, "\x002\x006\x000\x007\x002\x007\x00Q\x00E\x00B\x00V\x00B\x00D\x008\x00F"),
            'Packing list PDF should include the order SN'
        );
    }

    public function test_packing_list_pdf_renders_real_sku_when_present(): void
    {
        $store = $this->makeStore();
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'ORD-REAL-SKU-TEST',
            'order_status' => 'PROCESSED',
            'total_amount' => 50.00,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_two',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 9999,
            'model_id' => 8888,
            'item_name' => 'Fiddle Leaf Fig',
            'sku' => 'FLF-PREMIUM-M',
            'quantity' => 1,
            'price' => 50.00,
        ]);

        $service = $this->app->make(PackingListPdfService::class);
        $order->load(['items', 'store']);
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($service, $order);

        $this->assertStringContainsString('FLF-PREMIUM-M', $html);

        $pdf = $service->generate($order);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    public function test_download_shipping_label_returns_awb_and_packing_list(): void
    {
        $store = $this->makeStore();
        $order = $this->makeOrder($store);
        $calls = [];
        $awbPdf = $this->minimalPdf('AWB PAGE');

        $logistic = new class($calls, $awbPdf) {
            public function __construct(private array &$calls, private string $awbPdf) {}

            public function getTrackingNumber($orderSn, $params = [])
            {
                $this->calls[] = ['getTrackingNumber', [$orderSn, $params]];

                return ['tracking_number' => 'MYTRACK123'];
            }

            public function getShippingDocumentParameter($orderList = [])
            {
                $this->calls[] = ['getShippingDocumentParameter', [$orderList]];

                return [
                    'result_list' => [[
                        'order_sn' => $orderList[0]['order_sn'] ?? null,
                        'selectable_shipping_document_type' => [
                            'THERMAL_AIR_WAYBILL',
                            'NORMAL_AIR_WAYBILL',
                        ],
                        'suggested_shipping_document_type' => 'THERMAL_AIR_WAYBILL',
                    ]],
                ];
            }

            public function createShippingDocument($orderList = [])
            {
                $this->calls[] = ['createShippingDocument', [$orderList]];

                return ['result_list' => [['order_sn' => $orderList[0]['order_sn'] ?? null]]];
            }

            public function getShippingDocumentResult($orderList = [])
            {
                $this->calls[] = ['getShippingDocumentResult', [$orderList]];

                return [
                    'result_list' => [[
                        'order_sn' => $orderList[0]['order_sn'] ?? null,
                        'status' => 'READY',
                    ]],
                ];
            }

            public function downloadShippingDocument($orderList = [], $shippingDocumentType = '', $savePath = '')
            {
                $this->calls[] = ['downloadShippingDocument', [$orderList, $shippingDocumentType, $savePath]];

                return $this->awbPdf;
            }
        };

        $client = $this->clientWithLogistic($logistic);
        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->andReturn($client);

        $service = new LogisticsService(
            $shopee,
            $this->app->make(PackingListPdfService::class),
        );
        $docs = $service->downloadShippingLabel($store, $order);

        $this->assertArrayHasKey('awb', $docs);
        $this->assertArrayHasKey('packing_list', $docs);
        $this->assertSame($awbPdf, $docs['awb']);
        $this->assertStringStartsWith('%PDF', $docs['packing_list']);
        $this->assertGreaterThan(500, strlen($docs['packing_list']));

        $this->assertSame('getTrackingNumber', $calls[0][0]);
        $this->assertSame('getShippingDocumentParameter', $calls[1][0]);
        $this->assertSame('createShippingDocument', $calls[2][0]);
        $this->assertSame([
            [
                'order_sn' => '260727QEBVBD8F',
                'tracking_number' => 'MYTRACK123',
                'shipping_document_type' => 'NORMAL_AIR_WAYBILL',
            ],
        ], $calls[2][1][0]);
        $this->assertSame('getShippingDocumentResult', $calls[3][0]);
        $this->assertSame('downloadShippingDocument', $calls[4][0]);
        $this->assertSame('NORMAL_AIR_WAYBILL', $calls[4][1][1]);
    }

    public function test_ship_order_unpacks_pickup_dropoff_non_integrated(): void
    {
        $store = $this->makeStore();
        $client = new class extends Client {
            /** @var list<array{0:string,1:string,2:array}> */
            public array $calls = [];

            public function __construct()
            {
                parent::__construct(1, 'test-key');
            }

            public function call($method, $api, $options = [])
            {
                $this->calls[] = [$method, $api, $options];

                return [];
            }
        };

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);

        $service = new LogisticsService(
            $shopee,
            $this->app->make(PackingListPdfService::class),
        );
        $service->shipOrder($store, '260727QEBVBD8F', [
            'pickup' => ['address_id' => 42, 'pickup_time_id' => 'slot-1'],
        ]);

        $this->assertCount(1, $client->calls);
        [$method, $api, $options] = $client->calls[0];
        $this->assertSame('POST', $method);
        $this->assertSame('logistics/ship_order', $api);
        $this->assertSame([
            'order_sn' => '260727QEBVBD8F',
            'pickup' => ['address_id' => 42, 'pickup_time_id' => 'slot-1'],
        ], $options[\GuzzleHttp\RequestOptions::JSON]);
    }

    public function test_get_shipping_parameter_passes_order_sn_string(): void
    {
        $store = $this->makeStore();
        $client = new class extends Client {
            /** @var list<array{0:string,1:string,2:array}> */
            public array $calls = [];

            public function __construct()
            {
                parent::__construct(1, 'test-key');
            }

            public function call($method, $api, $options = [])
            {
                $this->calls[] = [$method, $api, $options];

                return ['info_needed' => ['pickup' => []]];
            }
        };

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);

        $service = new LogisticsService(
            $shopee,
            $this->app->make(PackingListPdfService::class),
        );
        $response = $service->getShippingParameter($store, '260727QEBVBD8F');

        $this->assertSame(['info_needed' => ['pickup' => []]], $response);
        $this->assertSame('GET', $client->calls[0][0]);
        $this->assertSame('logistics/get_shipping_parameter', $client->calls[0][1]);
        $this->assertSame(
            ['order_sn' => '260727QEBVBD8F'],
            $client->calls[0][2][\GuzzleHttp\RequestOptions::QUERY]
        );
    }

    private function makeStore(): ShopeeStore
    {
        $user = User::factory()->create();

        return ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 659429417,
            'shop_name' => 'The Plant Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);
    }

    private function makeOrder(ShopeeStore $store): Order
    {
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260727QEBVBD8F',
            'order_status' => 'PROCESSED',
            'total_amount' => 45.90,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_one',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 1001,
            'model_id' => 2002,
            'item_name' => 'Monstera Deliciosa Pot',
            'quantity' => 2,
            'price' => 22.95,
        ]);

        return $order->fresh(['items', 'store']);
    }

    private function clientWithLogistic(object $logistic): Client
    {
        return new class($logistic) extends Client {
            public function __construct(private object $logistic)
            {
                parent::__construct(1, 'test-key');
            }

            public function __get($resourceName)
            {
                if ($resourceName === 'Logistic') {
                    return $this->logistic;
                }

                return parent::__get($resourceName);
            }
        };
    }

    private function minimalPdf(string $label): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<html><body><h1>' . htmlspecialchars($label) . '</h1></body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
