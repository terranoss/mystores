<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_order_creation()
    {
        // Create test user and store
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 123456,
            'shop_name' => 'Test Store',
            'region' => 'my',
            'is_active' => true,
        ]);

        // Create an order
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'DEBUG123',
            'order_status' => 'completed',
            'total_amount' => 100.00,
            'currency' => 'MYR',
            'buyer_username' => 'Test User',
            'shipping_address' => '123 Test St',
            'created_at' => Carbon::now()->subDays(100),
        ]);

        $this->assertNotNull($order->id);
        $this->assertEquals('Test User', $order->buyer_username);
        $this->assertEquals('123 Test St', $order->shipping_address);

        // Try to refresh and see if it still has the data
        $order->refresh();
        $this->assertEquals('Test User', $order->buyer_username);
        $this->assertEquals('123 Test St', $order->shipping_address);
    }
}