<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaOrderService;
use App\Services\LazadaService;
use App\Services\ShopeeService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class LazadaOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_shopee_sync_command_skips_lazada_stores(): void
    {
        $user = User::factory()->create();
        $this->makeStore($user, 'lazada', 9101, 'Lazada Only');

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('forStore');
        $this->app->instance(ShopeeService::class, $shopee);

        $exit = Artisan::call('shopee:sync-orders');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No active Shopee stores', Artisan::output());
    }

    public function test_orders_index_filters_by_platform(): void
    {
        $user = User::factory()->create();
        $shopeeStore = $this->makeStore($user, 'shopee', 9102, 'Shopee Shop');
        $lazadaStore = $this->makeStore($user, 'lazada', 9103, 'Lazada Shop');

        Order::create([
            'store_id' => $shopeeStore->id,
            'order_sn' => 'SHP-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $lazadaStore->id,
            'order_sn' => '9103001',
            'order_status' => 'pending',
            'total_amount' => 20,
            'ordered_at' => now(),
        ]);

        $shopee = $this->actingAs($user)->getJson('/api/orders?platform=shopee');
        $shopee->assertOk();
        $this->assertCount(1, $shopee->json('data'));
        $this->assertSame('SHP-1', $shopee->json('data.0.order_sn'));

        $lazada = $this->actingAs($user)->getJson('/api/orders?platform=lazada');
        $lazada->assertOk();
        $this->assertCount(1, $lazada->json('data'));
        $this->assertSame('9103001', $lazada->json('data.0.order_sn'));
        $this->assertSame('lazada', $lazada->json('data.0.store.platform'));
    }

    public function test_lazada_upsert_maps_items_and_status(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9104, 'Lazada Map');

        $service = app(LazadaOrderService::class);
        $order = $service->upsertLazadaOrder($store, [
            'order_id' => 555001,
            'order_number' => 555001,
            'price' => '45.50',
            'customer_first_name' => 'Ali',
            'customer_last_name' => 'Baba',
            'created_at' => now()->toIso8601String(),
            'promised_shipping_times' => now()->addDay()->toIso8601String(),
            'address_shipping' => [
                'first_name' => 'Ali',
                'last_name' => 'Baba',
                'address1' => 'Jalan 1',
                'city' => 'KL',
                'country' => 'Malaysia',
                'phone' => '012',
            ],
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777001,
                'product_id' => 1001,
                'name' => 'Plant Pot',
                'status' => 'pending',
                'item_price' => 45.50,
            ],
        ]);

        $this->assertSame('555001', $order->order_sn);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('Ali Baba', $order->buyer_username);
        $this->assertCount(1, $order->items);
        $this->assertSame(777001, (int) $order->items->first()->model_id);
    }

    public function test_lazada_pack_updates_local_status_after_success(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9105, 'Lazada Pack');
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '666001',
            'order_status' => 'pending',
            'total_amount' => 12,
            'ordered_at' => now(),
            'raw_response' => ['order' => [], 'items' => [], 'packages' => []],
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 1,
            'model_id' => 888001,
            'item_name' => 'SKU',
            'quantity' => 1,
            'price' => 12,
        ]);

        $lazada = Mockery::mock(LazadaService::class)->makePartial();
        $lazada->shouldReceive('credentialsForStore')->andReturn([
            'access_token' => 'tok',
            'region' => 'my',
        ]);
        $lazada->shouldReceive('getShipmentProviders')->once()->andReturn([
            'code' => '0',
            'result' => [
                'data' => [
                    'platform_default' => 1,
                    'shipment_providers' => [],
                ],
                'success' => true,
            ],
        ]);
        $lazada->shouldReceive('packOrder')->once()->andReturn([
            'code' => '0',
            'result' => [
                'data' => [
                    'pack_order_list' => [[
                        'order_id' => 666001,
                        'order_item_list' => [[
                            'order_item_id' => 888001,
                            'item_err_code' => '0',
                            'package_id' => 'FPTEST123',
                            'tracking_number' => 'TRK1',
                            'msg' => 'success',
                        ]],
                    ]],
                ],
                'success' => true,
            ],
        ]);
        $lazada->shouldReceive('getOrder')->andReturn([
            'code' => '0',
            'data' => [
                'order_id' => 666001,
                'price' => '12',
                'statuses' => ['packed'],
                'created_at' => now()->toIso8601String(),
            ],
        ]);
        $lazada->shouldReceive('getOrderItems')->andReturn([
            'code' => '0',
            'data' => [[
                'order_item_id' => 888001,
                'product_id' => 1,
                'name' => 'SKU',
                'status' => 'packed',
                'item_price' => 12,
                'package_id' => 'FPTEST123',
            ]],
        ]);
        $this->app->instance(LazadaService::class, $lazada);

        $response = $this->actingAs($user)->postJson("/api/lazada/orders/{$order->id}/pack");
        $response->assertOk();
        $this->assertSame('packed', $response->json('order.order_status'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'packed',
        ]);
    }

    public function test_manual_sync_defaults_to_shopee_platform(): void
    {
        $user = User::factory()->create();
        $this->makeStore($user, 'lazada', 9106, 'Should Skip');

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('forStore');
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->postJson('/api/orders/sync');
        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_lazada_sync_ignores_poisoned_watermark_when_no_local_orders(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9107, 'Poisoned Watermark');
        $store->update(['orders_synced_at' => now()]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('credentialsForStore')->andReturn([
            'access_token' => 'tok',
            'region' => 'my',
        ]);
        $lazada->shouldReceive('resolveShopName')->andReturn('Melur Lazada Shop');
        $lazada->shouldReceive('getOrdersList')
            ->once()
            ->withArgs(function ($token, $region, $params) {
                $this->assertSame('tok', $token);
                $this->assertSame('my', $region);
                $after = $params['update_after'] ?? '';
                $this->assertNotSame('', $after);
                // Must look back ~14 days, not the poisoned "now" watermark.
                $this->assertTrue(now()->parse($after)->lte(now()->subDays(13)));

                return true;
            })
            ->andReturn([
                'code' => '0',
                'data' => ['orders' => [], 'count' => 0, 'countTotal' => 0],
            ]);
        $this->app->instance(LazadaService::class, $lazada);

        app(LazadaOrderService::class)->syncForStore($store->fresh('token'));
        $this->assertSame('Melur Lazada Shop', $store->fresh()->shop_name);
    }

    public function test_lazada_sync_for_store_heals_stale_ready_to_ship_when_list_is_empty(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9117, 'Stale RTS');
        $store->update(['orders_synced_at' => now()->subHour()]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '555016',
            'order_status' => 'ready_to_ship',
            'total_amount' => 13,
            'ordered_at' => now()->subDays(11),
            'synced_at' => now()->subDays(10),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('credentialsForStore')->andReturn([
            'access_token' => 'tok',
            'region' => 'my',
        ]);
        $lazada->shouldReceive('resolveShopName')->andReturn(null);
        $lazada->shouldReceive('getOrdersList')
            ->once()
            ->andReturn([
                'code' => '0',
                'data' => ['orders' => [], 'count' => 0, 'countTotal' => 0],
            ]);
        $lazada->shouldReceive('getOrder')
            ->once()
            ->with('tok', 'my', '555016')
            ->andReturn([
                'code' => '0',
                'data' => [
                    'order_id' => 555016,
                    'order_number' => 555016,
                    'price' => '13.00',
                    'created_at' => now()->subDays(11)->toIso8601String(),
                    'statuses' => ['confirmed'],
                ],
            ]);
        $lazada->shouldReceive('getOrderItems')
            ->once()
            ->with('tok', 'my', '555016')
            ->andReturn([
                'code' => '0',
                'data' => [
                    [
                        'order_item_id' => 777016,
                        'product_id' => 1016,
                        'name' => 'Peria bulan',
                        'status' => 'confirmed',
                        'item_price' => 13,
                    ],
                ],
            ]);
        $this->app->instance(LazadaService::class, $lazada);

        app(LazadaOrderService::class)->syncForStore($store->fresh('token'));

        $this->assertSame('confirmed', Order::where('order_sn', '555016')->value('order_status'));
    }

    public function test_lazada_sync_orders_command_is_scheduled_every_fifteen_minutes(): void
    {
        $schedule = app(Schedule::class);
        $scheduled = false;

        foreach ($schedule->events() as $event) {
            $cmd = (string) ($event->command ?? '');
            if (str_contains($cmd, 'lazada:sync-orders')) {
                $this->assertSame('*/15 * * * *', $event->expression);
                $scheduled = true;
            }
        }

        $this->assertTrue($scheduled, 'lazada:sync-orders should be scheduled every 15 minutes');
    }

    public function test_lazada_upsert_keeps_confirmed_as_confirmed(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9108, 'Confirmed Map');

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555002,
            'order_number' => 555002,
            'price' => '12.00',
            'created_at' => now()->toIso8601String(),
            'statuses' => ['confirmed'],
        ], [
            [
                'order_item_id' => 777002,
                'product_id' => 1002,
                'name' => 'Seed Pack',
                'status' => 'confirmed',
                'item_price' => 12,
            ],
        ]);

        $this->assertSame('confirmed', $order->order_status);
    }

    public function test_lazada_upsert_maps_all_delivered_to_delivered(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9112, 'Delivered Map');

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555010,
            'order_number' => 555010,
            'price' => '30.00',
            'created_at' => now()->subDays(5)->toIso8601String(),
            'statuses' => ['delivered'],
        ], [
            [
                'order_item_id' => 777010,
                'product_id' => 1010,
                'name' => 'Plant',
                'status' => 'delivered',
                'item_price' => 30,
            ],
        ]);

        $this->assertSame('delivered', $order->order_status);
    }

    public function test_lazada_upsert_does_not_map_confirmed_to_pending_when_existing_was_pending(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9114, 'Confirmed Upgrade');

        app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555020,
            'order_number' => 555020,
            'price' => '22.00',
            'created_at' => now()->subDays(10)->toIso8601String(),
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777020,
                'product_id' => 1020,
                'name' => 'Liberica',
                'status' => 'pending',
                'item_price' => 22,
            ],
        ]);

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555020,
            'order_number' => 555020,
            'price' => '22.00',
            'created_at' => now()->subDays(10)->toIso8601String(),
            'statuses' => ['confirmed'],
        ], [
            [
                'order_item_id' => 777020,
                'product_id' => 1020,
                'name' => 'Liberica',
                'status' => 'confirmed',
                'item_price' => 22,
            ],
        ]);

        $this->assertSame('confirmed', $order->order_status);
    }

    public function test_lazada_upsert_mixed_pending_and_delivered_stays_pending(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9113, 'Mixed Map');

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555011,
            'order_number' => 555011,
            'price' => '40.00',
            'created_at' => now()->toIso8601String(),
            'statuses' => ['pending', 'delivered'],
        ], [
            [
                'order_item_id' => 777011,
                'product_id' => 1011,
                'name' => 'A',
                'status' => 'pending',
                'item_price' => 20,
            ],
            [
                'order_item_id' => 777012,
                'product_id' => 1012,
                'name' => 'B',
                'status' => 'delivered',
                'item_price' => 20,
            ],
        ]);

        $this->assertSame('pending', $order->order_status);
    }

    public function test_lazada_upsert_empty_statuses_preserves_existing_delivered(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9114, 'Preserve Delivered');
        $service = app(LazadaOrderService::class);

        $service->upsertLazadaOrder($store, [
            'order_id' => 555012,
            'order_number' => 555012,
            'price' => '15.00',
            'created_at' => now()->subDays(3)->toIso8601String(),
            'statuses' => ['delivered'],
        ], [
            [
                'order_item_id' => 777013,
                'product_id' => 1013,
                'name' => 'Pot',
                'status' => 'delivered',
                'item_price' => 15,
            ],
        ]);

        $updated = $service->upsertLazadaOrder($store, [
            'order_id' => 555012,
            'order_number' => 555012,
            'price' => '15.00',
            'created_at' => now()->subDays(3)->toIso8601String(),
            'statuses' => [],
        ], [
            [
                'order_item_id' => 777013,
                'product_id' => 1013,
                'name' => 'Pot',
                'item_price' => 15,
            ],
        ]);

        $this->assertSame('delivered', $updated->order_status);
    }

    public function test_lazada_upsert_maps_failed_delivery_to_failed(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9115, 'Failed Delivery');

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555013,
            'order_number' => 555013,
            'price' => '11.00',
            'created_at' => now()->toIso8601String(),
            'statuses' => ['failed_delivery'],
        ], [
            [
                'order_item_id' => 777014,
                'product_id' => 1014,
                'name' => 'Seed',
                'status' => 'failed_delivery',
                'item_price' => 11,
            ],
        ]);

        $this->assertSame('failed', $order->order_status);
    }

    public function test_lazada_resync_stale_actionable_orders_refetches_by_id(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9116, 'Stale Resync');

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '555014',
            'order_status' => 'pending',
            'total_amount' => 22,
            'ordered_at' => now()->subDays(5),
            'synced_at' => now()->subDays(4),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => '555015',
            'order_status' => 'pending',
            'total_amount' => 8,
            'ordered_at' => now()->subHours(6),
            'synced_at' => now()->subHour(),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('credentialsForStore')->andReturn([
            'access_token' => 'tok',
            'region' => 'my',
        ]);
        $lazada->shouldReceive('getOrder')
            ->once()
            ->with('tok', 'my', '555014')
            ->andReturn([
                'code' => '0',
                'data' => [
                    'order_id' => 555014,
                    'order_number' => 555014,
                    'price' => '22.00',
                    'created_at' => now()->subDays(5)->toIso8601String(),
                    'statuses' => ['delivered'],
                ],
            ]);
        $lazada->shouldReceive('getOrderItems')
            ->once()
            ->with('tok', 'my', '555014')
            ->andReturn([
                'code' => '0',
                'data' => [
                    [
                        'order_item_id' => 777015,
                        'product_id' => 1015,
                        'name' => 'Tree',
                        'status' => 'delivered',
                        'item_price' => 22,
                    ],
                ],
            ]);
        $this->app->instance(LazadaService::class, $lazada);

        $result = app(LazadaOrderService::class)->resyncStaleActionableOrders($store->fresh('token'), 2);

        $this->assertSame(1, $result['attempted']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('delivered', Order::where('order_sn', '555014')->value('order_status'));
        $this->assertSame('pending', Order::where('order_sn', '555015')->value('order_status'));
    }

    public function test_lazada_upsert_uses_item_promised_when_order_field_empty(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9109, 'Ship By Item');
        $itemShipBy = now()->addDays(2)->startOfHour();

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555003,
            'order_number' => 555003,
            'price' => '10.00',
            'created_at' => now()->toIso8601String(),
            'promised_shipping_times' => '',
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777003,
                'product_id' => 1003,
                'name' => 'Pot',
                'status' => 'pending',
                'item_price' => 10,
                'promised_shipping_time' => $itemShipBy->toIso8601String(),
            ],
        ]);

        $this->assertNotNull($order->ship_by_date);
        $this->assertSame($itemShipBy->format('Y-m-d H:i:s'), $order->ship_by_date->format('Y-m-d H:i:s'));
    }

    public function test_lazada_upsert_uses_item_sla_time_stamp(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9110, 'Ship By SLA');
        $sla = now()->addDays(3)->startOfHour();

        $order = app(LazadaOrderService::class)->upsertLazadaOrder($store, [
            'order_id' => 555004,
            'order_number' => 555004,
            'price' => '8.00',
            'created_at' => now()->toIso8601String(),
            'promised_shipping_times' => '',
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777004,
                'product_id' => 1004,
                'name' => 'Soil',
                'status' => 'pending',
                'item_price' => 8,
                'promised_shipping_time' => '',
                'sla_time_stamp' => $sla->toIso8601String(),
            ],
        ]);

        $this->assertNotNull($order->ship_by_date);
        $this->assertSame($sla->format('Y-m-d H:i:s'), $order->ship_by_date->format('Y-m-d H:i:s'));
    }

    public function test_lazada_upsert_preserves_ship_by_when_api_empty(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9111, 'Ship By Preserve');
        $kept = now()->addDay()->startOfHour();

        $service = app(LazadaOrderService::class);
        $service->upsertLazadaOrder($store, [
            'order_id' => 555005,
            'order_number' => 555005,
            'price' => '9.00',
            'created_at' => now()->toIso8601String(),
            'promised_shipping_times' => $kept->toIso8601String(),
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777005,
                'product_id' => 1005,
                'name' => 'Seed',
                'status' => 'pending',
                'item_price' => 9,
            ],
        ]);

        $updated = $service->upsertLazadaOrder($store, [
            'order_id' => 555005,
            'order_number' => 555005,
            'price' => '9.00',
            'created_at' => now()->toIso8601String(),
            'promised_shipping_times' => '',
            'statuses' => ['pending'],
        ], [
            [
                'order_item_id' => 777005,
                'product_id' => 1005,
                'name' => 'Seed',
                'status' => 'pending',
                'item_price' => 9,
                'promised_shipping_time' => '',
                'sla_time_stamp' => '',
            ],
        ]);

        $this->assertNotNull($updated->ship_by_date);
        $this->assertSame($kept->format('Y-m-d H:i:s'), $updated->ship_by_date->format('Y-m-d H:i:s'));
    }

    private function makeStore(User $user, string $platform, int $shopId, string $name): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'shop_id' => $shopId,
            'shop_name' => $name,
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
