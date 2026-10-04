<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LazadaBulkAwbTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lazada_awb_bulk_rejects_empty_order_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/lazada/orders/awb-bulk', [
            'order_ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_lazada_awb_bulk_returns_documents_and_errors(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $orderA = $this->makePrintableOrder($store, 'LZ-AWB-A');
        $orderB = $this->makePrintableOrder($store, 'LZ-AWB-B');
        $pending = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'LZ-PENDING',
            'order_status' => 'pending',
            'total_amount' => 5,
            'ordered_at' => now(),
            'raw_response' => ['packages' => [['package_id' => 'P-PENDING']]],
        ]);

        $svc = Mockery::mock(LazadaOrderService::class)->makePartial();
        $svc->shouldReceive('downloadAwbPdf')
            ->times(3)
            ->andReturnUsing(function ($authUser, int $orderId) use ($user, $orderA, $orderB) {
                $this->assertSame($user->id, $authUser->id);
                $order = Order::with(['items', 'store'])->findOrFail($orderId);
                if ($order->id === $orderA->id) {
                    return [
                        'pdf' => '%PDF-awb-A',
                        'order' => $order,
                        'packages' => ['P-A'],
                    ];
                }
                if ($order->id === $orderB->id) {
                    return [
                        'pdf' => '%PDF-awb-B',
                        'order' => $order,
                        'packages' => ['P-B'],
                    ];
                }
                abort(400, 'Order must be packed, ready to ship, or shipped to print AWB.');
            });
        $this->app->instance(LazadaOrderService::class, $svc);

        $response = $this->actingAs($user)->postJson('/api/lazada/orders/awb-bulk', [
            'order_ids' => [$orderA->id, $pending->id, $orderB->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('filename', 'lazada-awb-packing-bulk.pdf');
        $response->assertJsonCount(2, 'documents');
        $response->assertJsonCount(1, 'errors');

        $documents = $response->json('documents');
        $this->assertSame($orderA->id, $documents[0]['order_id']);
        $this->assertSame(base64_encode('%PDF-awb-A'), $documents[0]['awb_pdf']);
        $this->assertNotEmpty($documents[0]['packing_list_pdf']);
        $this->assertSame($orderB->id, $documents[1]['order_id']);

        $errors = $response->json('errors');
        $this->assertSame($pending->id, $errors[0]['order_id']);
    }

    public function test_lazada_single_awb_returns_awb_and_packing_list_json(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $order = $this->makePrintableOrder($store, 'LZ-SINGLE-1');

        $svc = Mockery::mock(LazadaOrderService::class)->makePartial();
        $svc->shouldReceive('downloadAwbPdf')
            ->once()
            ->andReturn([
                'pdf' => '%PDF-single-awb',
                'order' => $order,
                'packages' => ['P-SINGLE-1'],
            ]);
        $this->app->instance(LazadaOrderService::class, $svc);

        $response = $this->actingAs($user)->getJson("/api/lazada/orders/{$order->id}/awb");

        $response->assertOk();
        $response->assertJsonStructure([
            'awb_pdf',
            'packing_list_pdf',
            'filename',
        ]);
        $this->assertSame(base64_encode('%PDF-single-awb'), $response->json('awb_pdf'));
        $this->assertNotEmpty($response->json('packing_list_pdf'));
        $this->assertSame('lazada-awb-packing-LZ-SINGLE-1.pdf', $response->json('filename'));
    }

    public function test_lazada_single_awb_supports_raw_pdf_fallback(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $order = $this->makePrintableOrder($store, 'LZ-SINGLE-RAW');

        $svc = Mockery::mock(LazadaOrderService::class)->makePartial();
        $svc->shouldReceive('downloadAwbPdf')
            ->once()
            ->andReturn([
                'pdf' => '%PDF-raw-bytes',
                'order' => $order,
                'packages' => ['P-RAW'],
            ]);
        $this->app->instance(LazadaOrderService::class, $svc);

        $response = $this->actingAs($user)->get("/api/lazada/orders/{$order->id}/awb?raw=1");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame('%PDF-raw-bytes', $response->getContent());
    }

    private function makeStore(User $user): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 9201,
            'shop_name' => 'Lazada Bulk',
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

    private function makePrintableOrder(ShopeeStore $store, string $sn): Order
    {
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => $sn,
            'order_status' => 'packed',
            'total_amount' => 12.5,
            'currency' => 'MYR',
            'ordered_at' => now(),
            'raw_response' => ['packages' => [['package_id' => 'PKG-'.$sn]]],
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => 1,
            'model_id' => 100,
            'item_name' => 'Test item '.$sn,
            'quantity' => 1,
            'price' => 12.5,
        ]);

        return $order->fresh(['items', 'store']);
    }
}
