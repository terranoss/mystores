<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_list_includes_store_relation_and_filters_by_order_sn(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551001,
            'shop_name' => 'Alpha Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $otherStore = ShopeeStore::create([
            'user_id' => $otherUser->id,
            'shop_id' => 551002,
            'shop_name' => 'Other Shop',
            'region' => 'SG',
            'is_active' => true,
        ]);

        $match = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260622RA80K43D',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 12.5,
            'currency' => 'SGD',
            'buyer_username' => 'buyer_one',
            'shipping_address' => '1 Test Street',
            'ship_by_date' => now()->addDay(),
            'synced_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $match->id,
            'item_id' => 1001,
            'item_name' => 'Sample Item',
            'quantity' => 2,
            'price' => 6.25,
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260622OTHERORDER',
            'order_status' => 'COMPLETED',
            'total_amount' => 5,
            'currency' => 'SGD',
            'synced_at' => now(),
        ]);

        Order::create([
            'store_id' => $otherStore->id,
            'order_sn' => '260622RA80K43D',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 99,
            'currency' => 'MYR',
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/orders?search=RA80K43D');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.order_sn', '260622RA80K43D');
        $response->assertJsonPath('data.0.store.shop_name', 'Alpha Shop');
        $response->assertJsonPath('data.0.items.0.item_name', 'Sample Item');
        $response->assertJsonPath('data.0.shipping_address', '1 Test Street');
        $response->assertJsonPath('data.0.order_status', 'READY_TO_SHIP');
        $response->assertJsonPath('data.0.canonical_status', 'ready_to_ship');
    }

    public function test_order_show_includes_store_and_items(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551003,
            'shop_name' => 'Detail Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260701DETAIL001',
            'order_status' => 'PROCESSED',
            'total_amount' => 20,
            'currency' => 'MYR',
            'buyer_username' => 'detail_buyer',
            'shipping_address' => '9 Detail Road',
            'synced_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 2002,
            'item_name' => 'Detail Item',
            'quantity' => 1,
            'price' => 20,
        ]);

        $response = $this->actingAs($user)->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('order.store.shop_name', 'Detail Shop');
        $response->assertJsonPath('order.items.0.item_name', 'Detail Item');
        $response->assertJsonPath('order.shipping_address', '9 Detail Road');
        $response->assertJsonPath('order.order_status', 'PROCESSED');
        $response->assertJsonPath('order.canonical_status', 'ready_to_ship');
    }

    public function test_order_payload_canonical_status_is_null_for_unmapped_status_without_a_store_query(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551005,
            'shop_name' => 'Unmapped Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260701UNMAPPED1',
            'order_status' => 'AWAITING_PICKUP',
            'total_amount' => 30,
            'currency' => 'MYR',
            'synced_at' => now(),
        ]);

        $show = $this->actingAs($user)->getJson("/api/orders/{$order->id}");
        $show->assertOk();
        $show->assertJsonPath('order.order_status', 'AWAITING_PICKUP');
        $this->assertArrayHasKey('canonical_status', $show->json('order'));
        $this->assertNull($show->json('order.canonical_status'));

        $list = $this->actingAs($user)->getJson('/api/orders?search=UNMAPPED1');
        $list->assertOk();
        $list->assertJsonPath('data.0.order_status', 'AWAITING_PICKUP');
        $this->assertArrayHasKey('canonical_status', $list->json('data.0'));
        $this->assertNull($list->json('data.0.canonical_status'));

        $fresh = Order::findOrFail($order->id);
        $this->assertFalse($fresh->relationLoaded('store'));

        DB::enableQueryLog();
        $fresh->toArray();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries, 'Deriving canonical_status must not query the database.');
    }

    public function test_cancel_does_not_false_negative_on_active_store_with_token(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551004,
            'shop_name' => 'Cancel Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        \App\Models\ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
            'refresh_failure_count' => 0,
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260622RA80K43D',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'SGD',
            'synced_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 3003,
            'model_id' => 0,
            'item_name' => 'Cancel Item',
            'quantity' => 1,
            'price' => 10,
        ]);

        $orderApi = new class {
            public function cancelOrder($order_sn, $cancel_reason, $item_list = [])
            {
                return ['success' => true];
            }
        };

        $client = new class($orderApi) extends \EcomPHP\Shopee\Client {
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

        $shopee = \Mockery::mock(\App\Services\ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);
        $this->app->instance(\App\Services\ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'cancel_reason' => 'OUT_OF_STOCK',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.order_status', 'CANCELLED');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_status' => 'CANCELLED',
            'cancel_reason' => 'OUT_OF_STOCK',
        ]);
    }

    public function test_order_list_search_matches_buyer_and_item_name(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551010,
            'shop_name' => 'Search Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $byBuyer = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728BUYERONLY1',
            'order_status' => 'PROCESSED',
            'total_amount' => 8,
            'currency' => 'MYR',
            'buyer_username' => 'plant_lover',
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);

        $byItem = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728ITEMONLY01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 15,
            'currency' => 'MYR',
            'buyer_username' => 'someone_else',
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $byItem->id,
            'item_id' => 11,
            'item_name' => 'Monstera Deliciosa',
            'quantity' => 1,
            'price' => 15,
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728NOMATCH0001',
            'order_status' => 'COMPLETED',
            'total_amount' => 1,
            'currency' => 'MYR',
            'buyer_username' => 'zzz',
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);

        $buyerHit = $this->actingAs($user)->getJson('/api/orders?search=plant_lover');
        $buyerHit->assertOk();
        $buyerHit->assertJsonPath('total', 1);
        $buyerHit->assertJsonPath('data.0.id', $byBuyer->id);

        $itemHit = $this->actingAs($user)->getJson('/api/orders?search=Monstera');
        $itemHit->assertOk();
        $itemHit->assertJsonPath('total', 1);
        $itemHit->assertJsonPath('data.0.id', $byItem->id);
    }

    public function test_order_list_filters_by_urgency_and_status_in(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551011,
            'shop_name' => 'Urgency Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $overdue = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728OVERDUE001',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
            'ship_by_date' => now()->subDay()->startOfDay()->addHours(12),
            'ordered_at' => now()->subDays(2),
            'synced_at' => now(),
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728DUETODAY01',
            'order_status' => 'PROCESSED',
            'total_amount' => 11,
            'currency' => 'MYR',
            'ship_by_date' => now()->startOfDay()->addHours(15),
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728SHIPPED001',
            'order_status' => 'SHIPPED',
            'total_amount' => 12,
            'currency' => 'MYR',
            'ship_by_date' => now()->subDay(),
            'ordered_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $urgency = $this->actingAs($user)->getJson('/api/orders?urgency=overdue');
        $urgency->assertOk();
        $urgency->assertJsonPath('total', 1);
        $urgency->assertJsonPath('data.0.id', $overdue->id);

        $statusIn = $this->actingAs($user)->getJson('/api/orders?status_in[]=SHIPPED&status_in[]=TO_CONFIRM_RECEIVE');
        $statusIn->assertOk();
        $statusIn->assertJsonPath('total', 1);
        $statusIn->assertJsonPath('data.0.order_sn', '260728SHIPPED001');
    }

    public function test_order_list_includes_lazada_ready_to_ship_in_every_urgency_bucket(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 551013,
            'shop_name' => 'Lazada Urgency Shop',
            'region' => 'my',
            'is_active' => true,
        ]);

        $overdue = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZ-URGENT-OVERDUE',
            'order_status' => 'ready_to_ship',
            'total_amount' => 10,
            'currency' => 'MYR',
            'ship_by_date' => now()->subDay()->startOfDay()->addHours(12),
            'ordered_at' => now()->subDays(2),
            'synced_at' => now(),
        ]);

        $dueToday = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZ-URGENT-TODAY',
            'order_status' => 'ready_to_ship',
            'total_amount' => 11,
            'currency' => 'MYR',
            'ship_by_date' => now()->startOfDay()->addHours(15),
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);

        $dueLater = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZ-URGENT-48H',
            'order_status' => 'ready_to_ship',
            'total_amount' => 12,
            'currency' => 'MYR',
            'ship_by_date' => now()->addDay()->startOfDay()->addHours(10),
            'ordered_at' => now(),
            'synced_at' => now(),
        ]);

        // Unpaid is not fulfilment work, so it never belongs in an urgency bucket.
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZ-URGENT-UNPAID',
            'order_status' => 'unpaid',
            'total_amount' => 13,
            'currency' => 'MYR',
            'ship_by_date' => now()->subDay(),
            'ordered_at' => now()->subDay(),
            'synced_at' => now(),
        ]);

        $overdueResponse = $this->actingAs($user)->getJson('/api/orders?urgency=overdue');
        $overdueResponse->assertOk();
        $overdueResponse->assertJsonPath('total', 1);
        $overdueResponse->assertJsonPath('data.0.id', $overdue->id);

        $dueTodayResponse = $this->actingAs($user)->getJson('/api/orders?urgency=due_today');
        $dueTodayResponse->assertOk();
        $dueTodayResponse->assertJsonPath('total', 1);
        $dueTodayResponse->assertJsonPath('data.0.id', $dueToday->id);

        $due48Response = $this->actingAs($user)->getJson('/api/orders?urgency=due_48h');
        $due48Response->assertOk();
        $due48Response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing(
            [$dueToday->id, $dueLater->id],
            array_column($due48Response->json('data'), 'id'),
        );
    }

    public function test_order_list_filters_by_ordered_date_range_and_sort(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 551012,
            'shop_name' => 'Date Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $old = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260720OLDORDER01',
            'order_status' => 'COMPLETED',
            'total_amount' => 5,
            'currency' => 'MYR',
            'ordered_at' => now()->subDays(10),
            'synced_at' => now(),
        ]);

        $recent = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728NEWORDER01',
            'order_status' => 'PROCESSED',
            'total_amount' => 50,
            'currency' => 'MYR',
            'ordered_at' => now()->subDay(),
            'ship_by_date' => now()->addDay(),
            'synced_at' => now(),
        ]);

        $from = now()->subDays(3)->toDateString();
        $to = now()->toDateString();
        $range = $this->actingAs($user)->getJson("/api/orders?ordered_from={$from}&ordered_to={$to}");
        $range->assertOk();
        $range->assertJsonPath('total', 1);
        $range->assertJsonPath('data.0.id', $recent->id);

        $sorted = $this->actingAs($user)->getJson('/api/orders?sort_by=total_amount&sort_dir=desc');
        $sorted->assertOk();
        $sorted->assertJsonPath('data.0.id', $recent->id);
        $sorted->assertJsonPath('data.1.id', $old->id);
    }
}
