<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShopeeService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShopeeOrderQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function orderService(): OrderService
    {
        return new OrderService(
            Mockery::mock(ShopeeService::class),
            Mockery::mock(WorkspaceAccess::class),
        );
    }

    public function test_upsert_maps_model_quantity_to_order_item_quantity(): void
    {
        $store = $this->makeStore();
        $service = $this->orderService();

        $order = $service->upsertOrderFromDetail($store, [
            'order_sn' => '260728UPK72MFT',
            'order_status' => 'PROCESSED',
            'total_amount' => 9,
            'currency' => 'MYR',
            'create_time' => now()->timestamp,
            'item_list' => [
                [
                    'item_id' => 1001,
                    'model_id' => 2001,
                    'item_name' => 'LVP - Keratan batang melur memanjat',
                    'model_quantity' => 5,
                    'model_discounted_price' => 1.8,
                ],
            ],
        ]);

        $this->assertCount(1, $order->items);
        $this->assertSame(5, (int) $order->items->first()->quantity);
    }

    public function test_upsert_falls_back_to_model_quantity_purchased(): void
    {
        $store = $this->makeStore();
        $service = $this->orderService();

        $order = $service->upsertOrderFromDetail($store, [
            'order_sn' => '260728QTYPURCH01',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 20,
            'currency' => 'MYR',
            'create_time' => now()->timestamp,
            'item_list' => [
                [
                    'item_id' => 1002,
                    'item_name' => 'Seed',
                    'model_quantity_purchased' => 3,
                    'model_discounted_price' => 5,
                ],
            ],
        ]);

        $this->assertSame(3, (int) $order->items->first()->quantity);
    }

    public function test_backfill_quantities_from_raw_response_fixes_wrong_ones(): void
    {
        $store = $this->makeStore();
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728BACKFILLQTY',
            'order_status' => 'PROCESSED',
            'total_amount' => 9,
            'currency' => 'MYR',
            'ordered_at' => now(),
            'raw_response' => [
                'order_sn' => '260728BACKFILLQTY',
                'item_list' => [
                    [
                        'item_id' => 1003,
                        'item_name' => 'Cutting',
                        'model_quantity' => 5,
                        'model_discounted_price' => 1.8,
                    ],
                ],
            ],
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 1003,
            'item_name' => 'Cutting',
            'quantity' => 1,
            'price' => 1.8,
        ]);

        $result = $this->orderService()->backfillQuantitiesFromRaw();

        $this->assertSame(1, $result['updated']);
        $this->assertSame(5, (int) OrderItem::where('order_id', $order->id)->value('quantity'));
    }

    private function makeStore(): ShopeeStore
    {
        $user = User::factory()->create();

        return ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 659429417,
            'shop_name' => 'The Plant Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);
    }
}
