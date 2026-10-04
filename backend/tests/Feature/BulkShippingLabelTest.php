<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BulkShippingLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bulk_shipping_labels_rejects_empty_order_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders/shipping-labels', [
            'order_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_shipping_labels_returns_documents_and_skips_foreign_orders(): void
    {
        [$user, $orderA, $orderB] = $this->makeTwoPrintableOrders();
        $foreign = $this->makeForeignOrder();

        $logistics = Mockery::mock(LogisticsService::class);
        $logistics->shouldReceive('downloadShippingLabel')
            ->twice()
            ->andReturnUsing(function ($store, Order $order) {
                return [
                    'awb' => '%PDF-awb-' . $order->order_sn,
                    'packing_list' => '%PDF-pack-' . $order->order_sn,
                ];
            });
        $this->app->instance(LogisticsService::class, $logistics);

        $response = $this->actingAs($user)->postJson('/api/orders/shipping-labels', [
            'order_ids' => [$orderA->id, $foreign->id, $orderB->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('filename', 'awb-packing-bulk.pdf');
        $response->assertJsonCount(2, 'documents');
        $response->assertJsonCount(1, 'errors');

        $documents = $response->json('documents');
        $this->assertSame($orderA->id, $documents[0]['order_id']);
        $this->assertSame($orderA->order_sn, $documents[0]['order_sn']);
        $this->assertSame(base64_encode('%PDF-awb-' . $orderA->order_sn), $documents[0]['awb_pdf']);
        $this->assertSame(base64_encode('%PDF-pack-' . $orderA->order_sn), $documents[0]['packing_list_pdf']);
        $this->assertSame($orderB->id, $documents[1]['order_id']);

        $errors = $response->json('errors');
        $this->assertSame($foreign->id, $errors[0]['order_id']);
    }

    public function test_bulk_shipping_labels_records_error_for_non_printable_status(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStoreFor($user);
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728READYONLY1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 10,
            'currency' => 'MYR',
        ]);

        $logistics = Mockery::mock(LogisticsService::class);
        $logistics->shouldReceive('downloadShippingLabel')->never();
        $this->app->instance(LogisticsService::class, $logistics);

        $response = $this->actingAs($user)->postJson('/api/orders/shipping-labels', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'documents');
        $response->assertJsonCount(1, 'errors');
        $response->assertJsonFragment([
            'order_id' => $order->id,
            'message' => 'Order must be PROCESSED or SHIPPED to print a shipping label.',
        ]);
    }

    /**
     * @return array{0: User, 1: Order, 2: Order}
     */
    private function makeTwoPrintableOrders(): array
    {
        $user = User::factory()->create();
        $store = $this->makeStoreFor($user);

        $orderA = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728BULKA0001',
            'order_status' => 'PROCESSED',
            'total_amount' => 12.5,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_a',
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'item_id' => 1,
            'item_name' => 'Item A',
            'quantity' => 1,
            'price' => 12.5,
        ]);

        $orderB = Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728BULKB0002',
            'order_status' => 'SHIPPED',
            'total_amount' => 9.9,
            'currency' => 'MYR',
            'buyer_username' => 'buyer_b',
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'item_id' => 2,
            'item_name' => 'Item B',
            'quantity' => 1,
            'price' => 9.9,
        ]);

        return [$user, $orderA, $orderB];
    }

    private function makeForeignOrder(): Order
    {
        $other = User::factory()->create();
        $store = $this->makeStoreFor($other, 999888777);

        return Order::create([
            'store_id' => $store->id,
            'order_sn' => '260728FOREIGN01',
            'order_status' => 'PROCESSED',
            'total_amount' => 1,
            'currency' => 'MYR',
        ]);
    }

    private function makeStoreFor(User $user, int $shopId = 659429417): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'shop_name' => 'The Plant Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addDay(),
            'auth_expires_at' => now()->addYear(),
        ]);

        return $store;
    }
}
