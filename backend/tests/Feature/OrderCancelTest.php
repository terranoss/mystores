<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_canceling_lazada_order_via_generic_cancel_endpoint_returns_400(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 991100,
            'shop_name' => 'Lazada Store Cancel Test',
            'region' => 'my',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(10),
            'refresh_token_expires_at' => now()->addDays(100),
            'auth_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZD-CANCEL-999',
            'order_status' => 'pending',
            'total_amount' => 50.00,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_lzd',
            'ordered_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'cancel_reason' => 'CUSTOMER_REQUEST',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Lazada orders must be cancelled via the Lazada cancellation endpoint.',
        ]);
    }

    public function test_canceling_shopee_order_via_generic_cancel_endpoint_succeeds(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 992200,
            'shop_name' => 'Shopee Store Cancel Test',
            'region' => 'my',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(10),
            'refresh_token_expires_at' => now()->addDays(100),
            'auth_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'SHP-CANCEL-111',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 35.00,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_shp',
            'ordered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 4004,
            'model_id' => 0,
            'item_name' => 'Plant Pot',
            'quantity' => 1,
            'price' => 35.00,
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

        $shopeeMock = Mockery::mock(ShopeeService::class);
        $shopeeMock->shouldReceive('forStore')->once()->andReturn($client);
        $this->app->instance(ShopeeService::class, $shopeeMock);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'cancel_reason' => 'CUSTOMER_REQUEST',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.order_status', 'CANCELLED');
        $this->assertSame('CANCELLED', $order->fresh()->order_status);
    }
}
