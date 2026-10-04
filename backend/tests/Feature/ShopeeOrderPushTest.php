<?php

namespace Tests\Feature;

use App\Jobs\SyncShopeeOrderJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShopeeService;
use EcomPHP\Shopee\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ShopeeOrderPushTest extends TestCase
{
    use RefreshDatabase;

    private string $partnerKey = 'test-partner-key';

    private string $webhookUrl = 'https://example.test/api/shopee/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopee.partner_id' => 12345,
            'shopee.partner_key' => $this->partnerKey,
            'shopee.webhook_url' => $this->webhookUrl,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_order_status_push_without_event_id_dispatches_sync_job(): void
    {
        Bus::fake();
        $store = $this->makeStore(shopId: 727720655);

        // Real Shopee pushes carry no event_id.
        $payload = [
            'code' => 3,
            'shop_id' => $store->shop_id,
            'timestamp' => 1660123127,
            'data' => [
                'ordersn' => '220810QSK8S7BX',
                'status' => 'PROCESSED',
                'update_time' => 1660123127,
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        Bus::assertDispatched(SyncShopeeOrderJob::class, function (SyncShopeeOrderJob $job) use ($store) {
            $reflection = new \ReflectionClass($job);
            $storeId = $reflection->getProperty('storeId');
            $storeId->setAccessible(true);
            $orderSn = $reflection->getProperty('orderSn');
            $orderSn->setAccessible(true);

            return $storeId->getValue($job) === $store->id
                && $orderSn->getValue($job) === '220810QSK8S7BX';
        });
        $this->assertSame('', $response->getContent());
    }

    public function test_verification_ping_without_event_id_returns_empty_200(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/shopee/webhook', [
            'code' => 0,
            'shop_id' => 0,
            'data' => new \stdClass(),
            'timestamp' => time(),
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        Bus::assertNothingDispatched();
    }

    public function test_order_status_push_with_unknown_shop_dispatches_nothing(): void
    {
        Bus::fake();

        $payload = [
            'code' => 3,
            'shop_id' => 999999001,
            'data' => [
                'ordersn' => '220810UNKNOWN',
                'status' => 'READY_TO_SHIP',
            ],
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        Bus::assertNotDispatched(SyncShopeeOrderJob::class);
    }

    public function test_duplicate_body_dispatches_only_once(): void
    {
        Bus::fake();
        $store = $this->makeStore(shopId: 727720656);

        // No event_id: dedupe relies on the body hash.
        $payload = [
            'code' => 3,
            'shop_id' => $store->shop_id,
            'timestamp' => 1660123200,
            'data' => [
                'ordersn' => '220810DUPORDER',
                'status' => 'READY_TO_SHIP',
                'update_time' => 1660123200,
            ],
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        Bus::assertDispatchedTimes(SyncShopeeOrderJob::class, 1);
    }

    public function test_two_status_changes_for_same_order_both_dispatch(): void
    {
        Bus::fake();
        $store = $this->makeStore(shopId: 727720659);

        // Same order, different status/update_time -> different body hash -> both process.
        $first = [
            'code' => 3,
            'shop_id' => $store->shop_id,
            'timestamp' => 1660123300,
            'data' => [
                'ordersn' => '220810SAMEORDER',
                'status' => 'READY_TO_SHIP',
                'update_time' => 1660123300,
            ],
        ];
        $second = $first;
        $second['data']['status'] = 'SHIPPED';
        $second['data']['update_time'] = 1660123900;

        $this->postSignedWebhook($first)->assertOk();
        $this->postSignedWebhook($second)->assertOk();

        Bus::assertDispatchedTimes(SyncShopeeOrderJob::class, 2);
    }

    public function test_invalid_signature_returns_401_and_dispatches_nothing(): void
    {
        Bus::fake();
        $store = $this->makeStore(shopId: 727720660);

        $payload = [
            'code' => 3,
            'shop_id' => $store->shop_id,
            'data' => [
                'ordersn' => '220810BADSIG',
                'status' => 'READY_TO_SHIP',
            ],
        ];

        $this->postJson('/api/shopee/webhook', $payload, [
            'Authorization' => 'invalid-signature',
        ])->assertStatus(401);

        Bus::assertNotDispatched(SyncShopeeOrderJob::class);
    }

    public function test_stock_push_code_8_updates_stock_without_order_job(): void
    {
        Bus::fake();
        $store = $this->makeStore(shopId: 727720657);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 111222333,
            'item_name' => 'Stock Push Product',
            'stock' => 5,
            'item_sku' => null,
        ]);

        $payload = [
            'code' => 8,
            'shop_id' => $store->shop_id,
            'data' => [
                'item_id' => 111222333,
                'stock' => 42,
            ],
        ];

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(42, $product->fresh()->stock);
        Bus::assertNotDispatched(SyncShopeeOrderJob::class);
    }

    public function test_cancel_order_uses_positional_sdk_arguments(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore(shopId: 727720658, user: $user);
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '220810CANCELME',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 123456,
            'model_id' => 789,
            'item_name' => 'Unavailable Item',
            'quantity' => 1,
            'price' => 10,
        ]);

        $orderApi = new class {
            /** @var array<int, array{0: string, 1: string, 2: array}> */
            public array $calls = [];

            public function cancelOrder($order_sn, $cancel_reason, $item_list = [])
            {
                $this->calls[] = [$order_sn, $cancel_reason, $item_list];

                return ['success' => true];
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
        $shopee->shouldReceive('forStore')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id))
            ->andReturn($client);

        $service = new OrderService($shopee);
        $updated = $service->cancelOrder($user, $order->id, 'OUT_OF_STOCK');

        $this->assertSame([[
            '220810CANCELME',
            'OUT_OF_STOCK',
            [['item_id' => 123456, 'model_id' => 789]],
        ]], $orderApi->calls);
        $this->assertSame('CANCELLED', $updated->order_status);
        $this->assertSame('OUT_OF_STOCK', $updated->cancel_reason);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $this->webhookUrl.'|'.$body, $this->partnerKey);

        return $this->call(
            'POST',
            '/api/shopee/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $signature,
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );
    }

    private function makeStore(int $shopId, ?User $user = null): ShopeeStore
    {
        $user ??= User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'shop_name' => 'Push Test Store',
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now()->subDay(),
            'refresh_failure_count' => 0,
        ]);

        return $store->fresh(['token']);
    }
}
