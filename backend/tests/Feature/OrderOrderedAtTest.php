<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OrderOrderedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_maps_create_time_to_ordered_at(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 659429417,
            'shop_name' => 'The Plant Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $service = new OrderService($shopee);

        $createTime = 1720000000;
        $order = $service->upsertOrderFromDetail($store, [
            'order_sn' => '260728TESTORDER01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 25.5,
            'currency' => 'MYR',
            'create_time' => $createTime,
            'pay_time' => $createTime + 60,
            'item_list' => [],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertNotNull($order->ordered_at);
        $this->assertSame(
            date('Y-m-d H:i:s', $createTime),
            $order->ordered_at->format('Y-m-d H:i:s')
        );
    }

    public function test_orders_list_includes_ordered_at(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551099,
            'shop_name' => 'List Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728LISTORDER01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
            'ordered_at' => '2026-07-27 10:00:00',
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk();
        $response->assertJsonPath('data.0.order_sn', '260728LISTORDER01');
        $this->assertNotNull($response->json('data.0.ordered_at'));
    }

    public function test_backfill_ordered_at_from_raw_response(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551100,
            'shop_name' => 'Backfill Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $createTime = 1782900494;
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728BACKFILL01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
            'ordered_at' => null,
            'raw_response' => [
                'order_sn' => '260728BACKFILL01',
                'create_time' => $createTime,
            ],
            'synced_at' => now(),
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $service = new OrderService($shopee);
        $result = $service->backfillOrderedAtFromRaw();

        $this->assertSame(1, $result['updated']);
        $order->refresh();
        $this->assertSame(
            date('Y-m-d H:i:s', $createTime),
            $order->ordered_at->format('Y-m-d H:i:s')
        );
    }

    public function test_upsert_without_create_time_does_not_wipe_ordered_at(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551101,
            'shop_name' => 'Preserve Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $existing = date('Y-m-d H:i:s', 1782900494);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728PRESERVE01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
            'ordered_at' => $existing,
            'synced_at' => now(),
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $service = new OrderService($shopee);
        $order = $service->upsertOrderFromDetail($store, [
            'order_sn' => '260728PRESERVE01',
            'order_status' => 'PROCESSED',
            'total_amount' => 10,
            'currency' => 'MYR',
            'item_list' => [],
        ]);

        $this->assertSame($existing, $order->ordered_at->format('Y-m-d H:i:s'));
    }
}
