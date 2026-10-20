<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_stats_including_cancelled_orders_today(): void
    {
        $user = User::factory()->create();

        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10001,
            'shop_name' => 'Test Store',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Order 1: Placed today, status CANCELLED
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'ORD-TODAY-CANCELLED',
            'order_status' => 'CANCELLED',
            'total_amount' => 50.00,
            'ordered_at' => now(),
        ]);

        // Order 2: Placed today, status READY_TO_SHIP
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'ORD-TODAY-RTS',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 100.00,
            'ordered_at' => now(),
        ]);

        // Order 3: Placed yesterday, status COMPLETED
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'ORD-YESTERDAY',
            'order_status' => 'COMPLETED',
            'total_amount' => 75.00,
            'ordered_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'stats' => [
                    'stores_count' => 1,
                    'orders_today' => 2, // Includes both CANCELLED and RTS from today
                    'pending_orders_count' => 0, // Today's RTS is Orders Today only, not Pending
                    'gmv_today' => 100, // Excludes CANCELLED 50
                    'orders_today_by_store' => [
                        [
                            'store_id' => $store->id,
                            'shop_name' => 'Test Store',
                            'platform' => 'shopee',
                            'count' => 2,
                        ],
                    ],
                    'pending_orders_by_store' => [],
                    'gmv_today_by_store' => [
                        [
                            'store_id' => $store->id,
                            'shop_name' => 'Test Store',
                            'platform' => 'shopee',
                            'count' => 1,
                            'gmv' => 100,
                        ],
                    ],
                ],
            ]);

        $trend = $response->json('stats.sales_trend');
        $this->assertCount(7, $trend);
        // yesterday COMPLETED 75 + today RTS 100 (cancelled excluded)
        $this->assertSame(2, collect($trend)->sum('orders'));
        $this->assertEquals(175.0, collect($trend)->sum('gmv'));
    }

    public function test_dashboard_gmv_by_store_excludes_cancelled_and_sums_to_total(): void
    {
        $user = User::factory()->create();
        $shopee = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 30001,
            'shop_name' => 'GMV Shopee',
            'is_active' => true,
            'linked_at' => now(),
        ]);
        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 30002,
            'shop_name' => 'GMV Lazada',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        Order::create([
            'store_id' => $shopee->id,
            'order_sn' => 'GMV-SP-1',
            'order_status' => 'COMPLETED',
            'total_amount' => 100,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $shopee->id,
            'order_sn' => 'GMV-SP-CANCEL',
            'order_status' => 'CANCELLED',
            'total_amount' => 999,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'GMV-LZ-1',
            'order_status' => 'canceled',
            'total_amount' => 50,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'GMV-LZ-2',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 25.5,
            'ordered_at' => now(),
        ]);

        // 3 days ago — should land in trend, not gmv_today
        Order::create([
            'store_id' => $shopee->id,
            'order_sn' => 'GMV-SP-OLD',
            'order_status' => 'COMPLETED',
            'total_amount' => 40,
            'ordered_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk();
        $stats = $response->json('stats');

        $this->assertEquals(125.5, $stats['gmv_today']);
        $byStore = collect($stats['gmv_today_by_store']);
        $this->assertEquals(125.5, $byStore->sum('gmv'));
        $this->assertEquals(100.0, $byStore->firstWhere('store_id', $shopee->id)['gmv']);
        $this->assertEquals(25.5, $byStore->firstWhere('store_id', $lazada->id)['gmv']);
        $this->assertSame($shopee->id, $byStore->first()['store_id']); // sorted by gmv desc

        $trend = collect($stats['sales_trend']);
        $this->assertCount(7, $trend);
        $this->assertSame(7, $stats['trend_days']);
        $this->assertEquals(165.5, $trend->sum('gmv')); // 125.5 today + 40 three days ago
        $this->assertSame(3, $trend->sum('orders'));

        $tz = config('app.dashboard_timezone', 'Asia/Kuala_Lumpur');
        $oldDay = now()->subDays(3)->timezone($tz)->toDateString();
        $this->assertEquals(40.0, $trend->firstWhere('date', $oldDay)['gmv']);
        $this->assertSame(1, $trend->firstWhere('date', $oldDay)['orders']);
        $oldByStore = $trend->firstWhere('date', $oldDay)['by_store'];
        $this->assertCount(1, $oldByStore);
        $this->assertSame($shopee->id, $oldByStore[0]['store_id']);
        $this->assertEquals(40.0, $oldByStore[0]['gmv']);

        $growth = $stats['sales_growth']['today_vs_yesterday'];
        $this->assertEquals(125.5, $growth['sales_today']);
        $this->assertEquals(0.0, $growth['sales_yesterday']);
        $this->assertNull($growth['sales_pct']); // no prior sales
        $this->assertSame(2, $growth['orders_today']);
        $this->assertSame(0, $growth['orders_yesterday']);
        $this->assertSame(2, $growth['orders_delta']);
    }

    public function test_dashboard_sales_growth_today_vs_yesterday_and_week(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 40001,
            'shop_name' => 'Growth Shop',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'GROW-TODAY',
            'order_status' => 'COMPLETED',
            'total_amount' => 120,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'GROW-YDAY',
            'order_status' => 'COMPLETED',
            'total_amount' => 100,
            'ordered_at' => now()->subDay(),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'GROW-PRIOR-WEEK',
            'order_status' => 'COMPLETED',
            'total_amount' => 200,
            'ordered_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk();
        $growth = $response->json('stats.sales_growth');

        $this->assertEquals(20.0, $growth['today_vs_yesterday']['sales_pct']);
        $this->assertSame(0, $growth['today_vs_yesterday']['orders_delta']);
        $this->assertEquals(120.0, $growth['today_vs_yesterday']['sales_today']);
        $this->assertEquals(100.0, $growth['today_vs_yesterday']['sales_yesterday']);

        // Current 7d: today 120 + yesterday 100 = 220; prior 7d: day-10 = 200
        $this->assertEquals(220.0, $growth['period_vs_prior']['sales_period']);
        $this->assertEquals(200.0, $growth['period_vs_prior']['sales_prior']);
        $this->assertEquals(10.0, $growth['period_vs_prior']['sales_pct']);
        $this->assertSame(1, $growth['period_vs_prior']['orders_delta']);
    }

    public function test_dashboard_sales_trend_days_presets_and_invalid_fallback(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 40002,
            'shop_name' => 'Trend Range Shop',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'TREND-TODAY',
            'order_status' => 'COMPLETED',
            'total_amount' => 50,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'TREND-OLD',
            'order_status' => 'COMPLETED',
            'total_amount' => 80,
            'ordered_at' => now()->subDays(20),
        ]);

        $default = $this->actingAs($user)->getJson('/api/dashboard');
        $default->assertOk();
        $this->assertSame(7, $default->json('stats.trend_days'));
        $this->assertCount(7, $default->json('stats.sales_trend'));

        $fourteen = $this->actingAs($user)->getJson('/api/dashboard?trend_days=14');
        $fourteen->assertOk();
        $this->assertSame(14, $fourteen->json('stats.trend_days'));
        $this->assertCount(14, $fourteen->json('stats.sales_trend'));
        $this->assertEquals(50.0, collect($fourteen->json('stats.sales_trend'))->sum('gmv'));

        $thirty = $this->actingAs($user)->getJson('/api/dashboard?trend_days=30');
        $thirty->assertOk();
        $this->assertSame(30, $thirty->json('stats.trend_days'));
        $this->assertCount(30, $thirty->json('stats.sales_trend'));
        $this->assertEquals(130.0, collect($thirty->json('stats.sales_trend'))->sum('gmv'));

        $invalid = $this->actingAs($user)->getJson('/api/dashboard?trend_days=99');
        $invalid->assertOk();
        $this->assertSame(7, $invalid->json('stats.trend_days'));
        $this->assertCount(7, $invalid->json('stats.sales_trend'));
    }

    public function test_dashboard_order_breakdowns_sum_to_totals_across_stores(): void
    {
        $user = User::factory()->create();

        $shopee = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 20001,
            'shop_name' => 'Shopee Shop',
            'is_active' => true,
            'linked_at' => now(),
        ]);
        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 20002,
            'shop_name' => 'Lazada Shop',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Today: 2 Shopee + 1 Lazada
        Order::create([
            'store_id' => $shopee->id,
            'order_sn' => 'SP-TODAY-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $shopee->id,
            'order_sn' => 'SP-TODAY-2',
            'order_status' => 'COMPLETED',
            'total_amount' => 20,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-TODAY-1',
            'order_status' => 'UNPAID',
            'total_amount' => 30,
            'ordered_at' => now(),
        ]);

        // Pending only (not today): Lazada PROCESSED from yesterday
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-PENDING-OLD',
            'order_status' => 'PROCESSED',
            'total_amount' => 40,
            'ordered_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk();

        $stats = $response->json('stats');
        $this->assertSame(3, $stats['orders_today']);
        $this->assertSame(1, $stats['pending_orders_count']); // Only LZ PROCESSED from yesterday

        $todayByStore = collect($stats['orders_today_by_store']);
        $this->assertSame(3, $todayByStore->sum('count'));
        $this->assertSame(2, $todayByStore->firstWhere('store_id', $shopee->id)['count']);
        $this->assertSame(1, $todayByStore->firstWhere('store_id', $lazada->id)['count']);
        $this->assertSame('Shopee Shop', $todayByStore->firstWhere('store_id', $shopee->id)['shop_name']);
        $this->assertSame('lazada', $todayByStore->firstWhere('store_id', $lazada->id)['platform']);
        // Sorted by count desc — Shopee (2) before Lazada (1)
        $this->assertSame($shopee->id, $todayByStore->first()['store_id']);

        $pendingByStore = collect($stats['pending_orders_by_store']);
        $this->assertSame(1, $pendingByStore->sum('count'));
        $this->assertNull($pendingByStore->firstWhere('store_id', $shopee->id));
        $this->assertSame(1, $pendingByStore->firstWhere('store_id', $lazada->id)['count']);
    }

    public function test_dashboard_pending_orders_excludes_delivered_and_confirmed(): void
    {
        $user = User::factory()->create();

        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 20003,
            'shop_name' => 'Lazada Pending Card',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-TO-PACK',
            'order_status' => 'pending',
            'total_amount' => 10,
            'ordered_at' => now()->subDay(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-PACKED',
            'order_status' => 'packed',
            'total_amount' => 11,
            'ordered_at' => now()->subDays(2),
        ]);
        // Same pending status today — counted in Orders Today, not Pending
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-PENDING-TODAY',
            'order_status' => 'pending',
            'total_amount' => 13,
            'ordered_at' => now(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-DELIVERED',
            'order_status' => 'delivered',
            'total_amount' => 20,
            'ordered_at' => now()->subDay(),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-CONFIRMED',
            'order_status' => 'confirmed',
            'total_amount' => 22,
            'ordered_at' => now()->subDays(2),
        ]);
        Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-SHIPPED',
            'order_status' => 'shipped',
            'total_amount' => 15,
            'ordered_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard');
        $response->assertOk();

        $this->assertSame(1, $response->json('stats.orders_today'));
        $this->assertSame(2, $response->json('stats.pending_orders_count'));
        $pendingByStore = collect($response->json('stats.pending_orders_by_store'));
        $this->assertSame(2, $pendingByStore->firstWhere('store_id', $lazada->id)['count']);
    }

    public function test_dashboard_pending_card_and_pending_picklist_use_needs_fulfillment(): void
    {
        $user = User::factory()->create();

        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 20004,
            'shop_name' => 'Lazada Needs Fulfillment',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        $readyToShip = Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-NF-RTS',
            'order_status' => 'ready_to_ship',
            'total_amount' => 20,
            'ordered_at' => now()->subDay(),
        ]);
        $readyToShip->items()->create([
            'item_id' => 301,
            'model_id' => 401,
            'item_name' => 'Ready Item',
            'quantity' => 2,
            'price' => 10,
        ]);

        // Unpaid is resync work, not fulfilment work: never "pending".
        $unpaid = Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-NF-UNPAID',
            'order_status' => 'unpaid',
            'total_amount' => 50,
            'ordered_at' => now()->subDay(),
        ]);
        $unpaid->items()->create([
            'item_id' => 302,
            'model_id' => 402,
            'item_name' => 'Unpaid Item',
            'quantity' => 5,
            'price' => 10,
        ]);

        // Completed is terminal: never "pending".
        $completed = Order::create([
            'store_id' => $lazada->id,
            'order_sn' => 'LZ-NF-COMPLETED',
            'order_status' => 'completed',
            'total_amount' => 30,
            'ordered_at' => now()->subDay(),
        ]);
        $completed->items()->create([
            'item_id' => 303,
            'model_id' => 403,
            'item_name' => 'Completed Item',
            'quantity' => 3,
            'price' => 10,
        ]);

        $dashboard = $this->actingAs($user)->getJson('/api/dashboard');
        $dashboard->assertOk();
        $this->assertSame(1, $dashboard->json('stats.pending_orders_count'));
        $pendingByStore = collect($dashboard->json('stats.pending_orders_by_store'));
        $this->assertSame(1, $pendingByStore->firstWhere('store_id', $lazada->id)['count']);

        $pickList = $this->actingAs($user)->getJson('/api/dashboard/pending-picklist');
        $pickList->assertOk();
        $pickList->assertJsonPath('total_orders', 1);
        $pickList->assertJsonPath('total_units', 2);
        $pickList->assertJsonPath('aggregated_items.0.item_name', 'Ready Item');
        $pickList->assertJsonPath('orders.0.order_sn', 'LZ-NF-RTS');
    }

    public function test_today_picklist_returns_aggregated_items_and_orders(): void
    {
        $user = User::factory()->create();

        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 20001,
            'shop_name' => 'PickList Store',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        $order1 = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-ORD-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 100.00,
            'ordered_at' => now(),
        ]);
        $order1->items()->create([
            'item_id' => 101,
            'model_id' => 201,
            'item_name' => 'Ergonomic Mouse',
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $order2 = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-ORD-2',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 50.00,
            'ordered_at' => now(),
        ]);
        $order2->items()->create([
            'item_id' => 101,
            'model_id' => 201,
            'item_name' => 'Ergonomic Mouse',
            'quantity' => 3,
            'price' => 50.00,
        ]);

        // Cancelled order should be excluded from pick list
        $cancelledOrder = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-ORD-CANCELLED',
            'order_status' => 'CANCELLED',
            'total_amount' => 50.00,
            'ordered_at' => now(),
        ]);
        $cancelledOrder->items()->create([
            'item_id' => 999,
            'model_id' => 999,
            'item_name' => 'Cancelled Item',
            'quantity' => 10,
            'price' => 50.00,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/today-picklist');

        $response->assertStatus(200)
            ->assertJson([
                'total_orders' => 2,
                'total_skus' => 1,
                'total_units' => 5,
            ]);

        $items = $response->json('aggregated_items');
        $this->assertCount(1, $items);
        $this->assertSame('Ergonomic Mouse', $items[0]['item_name']);
        $this->assertSame(5, $items[0]['quantity']);
    }

    public function test_pending_picklist_returns_aggregated_items_for_pending_orders(): void
    {
        $user = User::factory()->create();

        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 20002,
            'shop_name' => 'Pending PickList Store',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        // Older pending order (placed 3 days ago)
        $oldPending = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-PENDING-OLD',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 100.00,
            'ordered_at' => now()->subDays(3),
        ]);
        $oldPending->items()->create([
            'item_id' => 202,
            'model_id' => 302,
            'item_name' => 'Mechanical Keyboard',
            'quantity' => 2,
            'price' => 50.00,
        ]);

        // Today's pending order (should be excluded — belongs to Orders Today)
        $todayPending = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-PENDING-TODAY',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 75.00,
            'ordered_at' => now(),
        ]);
        $todayPending->items()->create([
            'item_id' => 203,
            'model_id' => 303,
            'item_name' => 'Today Mouse',
            'quantity' => 4,
            'price' => 25.00,
        ]);

        // Completed order (should be excluded)
        $completed = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-COMPLETED',
            'order_status' => 'COMPLETED',
            'total_amount' => 50.00,
            'ordered_at' => now()->subDays(1),
        ]);
        $completed->items()->create([
            'item_id' => 999,
            'model_id' => 999,
            'item_name' => 'Completed Item',
            'quantity' => 10,
            'price' => 50.00,
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/pending-picklist');

        $response->assertStatus(200)
            ->assertJson([
                'total_orders' => 1,
                'total_skus' => 1,
                'total_units' => 2,
            ]);

        $items = $response->json('aggregated_items');
        $this->assertCount(1, $items);
        $this->assertSame('Mechanical Keyboard', $items[0]['item_name']);
    }

    public function test_dashboard_cancel_reasons_breakdown_for_trend_window(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 50001,
            'shop_name' => 'Cancel Analytics Shop',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-OOS-1',
            'order_status' => 'CANCELLED',
            'cancel_reason' => 'OUT_OF_STOCK',
            'total_amount' => 10,
            'ordered_at' => now()->subDays(1),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-OOS-2',
            'order_status' => 'CANCELLED',
            'cancel_reason' => 'OUT_OF_STOCK',
            'total_amount' => 12,
            'ordered_at' => now()->subDays(2),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-UNKNOWN',
            'order_status' => 'cancelled',
            'cancel_reason' => null,
            'total_amount' => 8,
            'ordered_at' => now()->subDays(3),
        ]);
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-LAZADA',
            'order_status' => 'CANCELLED',
            'cancel_reason' => 'Out of stock',
            'total_amount' => 9,
            'ordered_at' => now()->subDay(),
        ]);
        // Outside window
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-OLD',
            'order_status' => 'CANCELLED',
            'cancel_reason' => 'CUSTOMER_REQUEST',
            'total_amount' => 5,
            'ordered_at' => now()->subDays(20),
        ]);
        // Not cancelled
        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'CXL-OK',
            'order_status' => 'COMPLETED',
            'cancel_reason' => 'OUT_OF_STOCK',
            'total_amount' => 15,
            'ordered_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard?trend_days=7');
        $response->assertOk();

        $this->assertSame(4, $response->json('stats.cancel_reasons_total'));
        $reasons = collect($response->json('stats.cancel_reasons'));
        $this->assertSame(2, $reasons->firstWhere('reason', 'OUT_OF_STOCK')['count']);
        $this->assertSame('Out of Stock', $reasons->firstWhere('reason', 'OUT_OF_STOCK')['label']);
        $this->assertSame(1, $reasons->firstWhere('reason', 'Unknown')['count']);
        $this->assertSame(1, $reasons->firstWhere('reason', 'Out of stock')['count']);
        $this->assertNull($reasons->firstWhere('reason', 'CUSTOMER_REQUEST'));
        $this->assertSame('OUT_OF_STOCK', $reasons->first()['reason']);
    }
}

