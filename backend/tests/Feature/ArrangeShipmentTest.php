<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LogisticsService;
use App\Services\PackingListPdfService;
use App\Services\ShopeeService;
use EcomPHP\Shopee\Client;
use EcomPHP\Shopee\Errors\ShopeeException;
use GuzzleHttp\RequestOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ArrangeShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_shipping_parameter_omits_empty_package_number(): void
    {
        $store = $this->makeStore();
        $client = $this->recordingClient([
            'info_needed' => ['pickup' => ['address_id', 'pickup_time_id']],
            'pickup' => ['address_list' => [['address_id' => 1]]],
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);

        $service = new LogisticsService($shopee, app(PackingListPdfService::class));
        $response = $service->getShippingParameter($store, '260728READYORDER1');

        $this->assertSame(['pickup' => ['address_id', 'pickup_time_id']], $response['info_needed']);
        $this->assertCount(1, $client->calls);
        [$method, $api, $options] = $client->calls[0];
        $this->assertSame('GET', $method);
        $this->assertSame('logistics/get_shipping_parameter', $api);
        $this->assertSame(['order_sn' => '260728READYORDER1'], $options[RequestOptions::QUERY]);
        $this->assertArrayNotHasKey('package_number', $options[RequestOptions::QUERY]);
    }

    public function test_ship_order_sends_only_selected_mode_without_empty_package_number(): void
    {
        $store = $this->makeStore();
        $client = $this->recordingClient([]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);

        $service = new LogisticsService($shopee, app(PackingListPdfService::class));
        $service->shipOrder($store, '260728READYORDER1', [
            'pickup' => ['address_id' => 42, 'pickup_time_id' => 'slot-1'],
        ]);

        $this->assertCount(1, $client->calls);
        [$method, $api, $options] = $client->calls[0];
        $this->assertSame('POST', $method);
        $this->assertSame('logistics/ship_order', $api);
        $this->assertSame([
            'order_sn' => '260728READYORDER1',
            'pickup' => ['address_id' => 42, 'pickup_time_id' => 'slot-1'],
        ], $options[RequestOptions::JSON]);
        $this->assertArrayNotHasKey('package_number', $options[RequestOptions::JSON]);
        $this->assertArrayNotHasKey('dropoff', $options[RequestOptions::JSON]);
        $this->assertArrayNotHasKey('non_integrated', $options[RequestOptions::JSON]);
    }

    public function test_ship_order_encodes_empty_dropoff_as_json_object(): void
    {
        $store = $this->makeStore();
        $client = $this->recordingClient([]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);

        $service = new LogisticsService($shopee, app(PackingListPdfService::class));
        $service->shipOrder($store, '260728READYORDER1', [
            'dropoff' => [],
        ]);

        $body = $client->calls[0][2][RequestOptions::JSON];
        $this->assertSame('260728READYORDER1', $body['order_sn']);
        $this->assertInstanceOf(\stdClass::class, $body['dropoff']);
        $this->assertSame('{}', json_encode($body['dropoff']));
    }

    public function test_shipping_parameters_maps_shopee_exception_to_400(): void
    {
        [$user, $order] = $this->makeReadyOrder();

        $shopee = Mockery::mock(ShopeeService::class);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('call')
            ->once()
            ->andThrow(new ShopeeException('Package does not exist', 'logistics.package_not_exist'));
        $shopee->shouldReceive('forStore')->once()->andReturn($client);
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->getJson("/api/orders/{$order->id}/shipping-parameters");

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Package does not exist']);
    }

    public function test_ship_accepts_empty_dropoff_object(): void
    {
        [$user, $order] = $this->makeReadyOrder();

        $logistics = Mockery::mock(LogisticsService::class);
        $logistics->shouldReceive('shipOrder')
            ->once()
            ->withArgs(function ($store, $orderSn, $params) use ($order) {
                return $store->id === $order->store_id
                    && $orderSn === $order->order_sn
                    && array_key_exists('dropoff', $params)
                    && $params['dropoff'] === [];
            })
            ->andReturn([]);
        $this->app->instance(LogisticsService::class, $logistics);

        $orderService = Mockery::mock(\App\Services\OrderService::class)->makePartial();
        $orderService->shouldReceive('getOrder')->once()->andReturn($order->load('store.token'));
        $processed = $order->replicate();
        $processed->id = $order->id;
        $processed->order_status = 'PROCESSED';
        $processed->setRelation('items', collect());
        $processed->setRelation('store', $order->store);
        $orderService->shouldReceive('syncAfterShip')
            ->once()
            ->andReturn($processed);
        $this->app->instance(\App\Services\OrderService::class, $orderService);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/ship", [
            'dropoff' => (object) [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.order_status', 'PROCESSED');
        $response->assertJsonPath('message', 'Shipment arranged successfully.');
    }

    public function test_ship_rejects_body_without_mode_keys(): void
    {
        [$user, $order] = $this->makeReadyOrder();

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/ship", []);

        $response->assertStatus(422);
    }

    public function test_sync_after_ship_retries_until_processed(): void
    {
        $store = $this->makeStore();
        $ready = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728RETRYORDER1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
            'synced_at' => now(),
        ]);

        $orderApi = new class {
            private int $calls = 0;

            public function getOrderDetail($ids, $params = [])
            {
                $this->calls++;
                $status = $this->calls === 1 ? 'READY_TO_SHIP' : 'PROCESSED';

                return [
                    'order_list' => [[
                        'order_sn' => is_array($ids) ? $ids[0] : $ids,
                        'order_status' => $status,
                        'total_amount' => 10,
                        'currency' => 'MYR',
                        'item_list' => [],
                    ]],
                ];
            }
        };

        $client = new class($orderApi) extends Client {
            public function __construct(private object $orderApi)
            {
                parent::__construct(1, 'test-key');
            }

            public function __get($resourceName)
            {
                if ($resourceName === 'Order') {
                    return $this->orderApi;
                }

                return parent::__get($resourceName);
            }
        };

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->andReturn($client);
        $service = new \App\Services\OrderService(
            $shopee,
            app(\App\Services\WorkspaceAccess::class),
            app(\App\Services\PickListPhotoCleanupService::class),
            app(\App\Services\WorkspaceNotificationService::class),
        );

        $updated = $service->syncAfterShip($store, $ready->order_sn, 3, 0);

        $this->assertSame('PROCESSED', $updated->order_status);
        $this->assertDatabaseHas('orders', [
            'order_sn' => '260728RETRYORDER1',
            'order_status' => 'PROCESSED',
        ]);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeReadyOrder(): array
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 659429417,
            'shop_name' => 'The Plant Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addYear(),
        ]);
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728READYORDER1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 13.26,
            'currency' => 'MYR',
            'synced_at' => now(),
        ]);

        return [$user, $order];
    }

    private function makeStore(): ShopeeStore
    {
        $user = User::factory()->create();

        return ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 659429418,
            'shop_name' => 'Test Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);
    }

    private function recordingClient(array $return): object
    {
        return new class($return) extends Client {
            /** @var list<array{0:string,1:string,2:array}> */
            public array $calls = [];

            public function __construct(private array $returnPayload)
            {
                parent::__construct(1, 'test-key');
            }

            public function call($method, $api, $options = [])
            {
                $this->calls[] = [$method, $api, $options];

                return $this->returnPayload;
            }
        };
    }
}
