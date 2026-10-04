<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\WorkspaceActivityLog;
use App\Services\ErpConnectorService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ErpSalesComparePushTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array{user: User, store: ShopeeStore, lazada: ShopeeStore}
     */
    private function seedStores(User $user): array
    {
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'shop_id' => 77001,
            'shop_name' => 'Shopee Melur',
            'is_active' => true,
        ]);
        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'platform' => 'lazada',
            'shop_id' => 77002,
            'shop_name' => 'Lazada Melur',
            'is_active' => true,
        ]);

        return compact('user', 'store', 'lazada');
    }

    private function makeOrder(ShopeeStore $store, array $overrides = [], array $items = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => $store->id,
            'order_sn' => 'SN-'.uniqid(),
            'order_status' => $store->platform === 'lazada' ? 'shipped' : 'SHIPPED',
            'total_amount' => 25.00,
            'currency' => 'MYR',
            'buyer_username' => 'buyer1',
            'ordered_at' => now()->subDay(),
            'synced_at' => now(),
        ], $overrides));

        $lines = $items !== [] ? $items : [[
            'item_id' => 1001,
            'model_id' => 2001,
            'item_name' => 'Hibiscus Soap',
            'sku' => 'SOAP-01',
            'quantity' => 2,
            'price' => 12.50,
        ]];

        foreach ($lines as $line) {
            OrderItem::create(array_merge(['order_id' => $order->id], $line));
        }

        return $order->fresh(['items', 'store']);
    }

    public function test_compare_marks_missing_matched_and_mismatch(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);

        $missing = $this->makeOrder($store, ['order_sn' => 'MISS-001']);
        $matched = $this->makeOrder($store, ['order_sn' => 'MATCH-001']);
        $mismatch = $this->makeOrder($store, ['order_sn' => 'MISM-001']);
        // Pre-ship — must be excluded
        $this->makeOrder($store, ['order_sn' => 'RTS-001', 'order_status' => 'READY_TO_SHIP']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->once()
            ->andReturnUsing(function (array $invoiceNos) {
                $this->assertContains('MISS-001', $invoiceNos);
                $this->assertContains('MATCH-001', $invoiceNos);
                $this->assertContains('MISM-001', $invoiceNos);
                $this->assertNotContains('RTS-001', $invoiceNos);

                return [
                    'MATCH-001' => [
                        'id' => 10,
                        'invoice_no' => 'MATCH-001',
                        'transaction_date' => now()->toDateTimeString(),
                        'final_total' => 25.0,
                        'status' => 'final',
                        'lines' => [
                            ['sku' => 'SOAP-01', 'product_id' => 1, 'variation_id' => 2, 'quantity' => 2.0, 'unit_price' => 12.50],
                        ],
                    ],
                    'MISM-001' => [
                        'id' => 11,
                        'invoice_no' => 'MISM-001',
                        'transaction_date' => now()->toDateTimeString(),
                        'final_total' => 10.0,
                        'status' => 'final',
                        'lines' => [
                            ['sku' => 'SOAP-01', 'product_id' => 1, 'variation_id' => 2, 'quantity' => 1.0, 'unit_price' => 12.50],
                        ],
                    ],
                ];
            });
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->getJson('/api/erp/sales-compare');

        $response->assertOk();
        $this->assertSame(3, $response->json('stats.total'));
        $this->assertSame(1, $response->json('stats.missing'));
        $this->assertSame(1, $response->json('stats.matched'));
        $this->assertSame(1, $response->json('stats.mismatch'));

        $bySn = collect($response->json('data'))->keyBy('order_sn');
        $this->assertSame('missing', $bySn['MISS-001']['sync_state']);
        $this->assertSame('matched', $bySn['MATCH-001']['sync_state']);
        $this->assertSame('mismatch', $bySn['MISM-001']['sync_state']);
        $this->assertSame($missing->id, $bySn['MISS-001']['id']);
        $this->assertSame($matched->id, $bySn['MATCH-001']['id']);
        $this->assertSame($mismatch->id, $bySn['MISM-001']['id']);
    }

    public function test_compare_filters_by_sync_state_and_platform(): void
    {
        $user = User::factory()->create();
        ['store' => $store, 'lazada' => $lazada] = $this->seedStores($user);

        $this->makeOrder($store, ['order_sn' => 'SHP-MISS']);
        $this->makeOrder($lazada, ['order_sn' => 'LAZ-MISS', 'order_status' => 'delivered']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')->andReturn([]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $shopeeOnly = $this->actingAs($user)->getJson('/api/erp/sales-compare?platform=shopee&sync_state=missing');
        $shopeeOnly->assertOk();
        $this->assertCount(1, $shopeeOnly->json('data'));
        $this->assertSame('SHP-MISS', $shopeeOnly->json('data.0.order_sn'));
        $this->assertSame('shopee', $shopeeOnly->json('data.0.platform'));
    }

    public function test_push_creates_sell_for_missing_order(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $order = $this->makeOrder($store, ['order_sn' => 'PUSH-001']);
        $erp = Mockery::mock(ErpConnectorService::class);
        $confirmedSell = [
            'id' => 777,
            'invoice_no' => 'PUSH-001',
            'final_total' => 25.0,
            'status' => 'final',
            'lines' => [
                [
                    'sku' => 'SOAP-01',
                    'product_id' => 55,
                    'variation_id' => 66,
                    'quantity' => 2.0,
                    'unit_price' => 12.50,
                ],
            ],
        ];
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(['PUSH-001'])
            ->andReturn([], ['PUSH-001' => $confirmedSell]);
        $erp->shouldReceive('resolveVariationBySku')
            ->once()
            ->with('SOAP-01')
            ->andReturn(['product_id' => 55, 'variation_id' => 66, 'sku' => 'SOAP-01']);
        $erp->shouldReceive('buildSellEntry')
            ->once()
            ->andReturnUsing(function ($invoiceNo, $source, $date, $products) {
                $this->assertSame('PUSH-001', $invoiceNo);
                $this->assertSame('shopee', $source);
                $this->assertSame(55, $products[0]['product_id']);
                return [
                    'location_id' => 10,
                    'contact_id' => 40, // ONLINE SALE - SHOPEE (CO0040)
                    'transaction_date' => $date,
                    'invoice_no' => $invoiceNo,
                    'source' => $source,
                    'status' => 'final',
                    'products' => $products,
                    'payments' => [['amount' => 25.0, 'method' => 'other']],
                ];
            });
        $erp->shouldReceive('createSell')
            ->once()
            ->with(Mockery::on(function ($payload) {
                return isset($payload['sells'][0]['invoice_no'])
                    && $payload['sells'][0]['invoice_no'] === 'PUSH-001'
                    && $payload['sells'][0]['status'] === 'final'
                    && ($payload['sells'][0]['source'] ?? null) === 'shopee';
            }))
            ->andReturn(['success' => 1]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('pushed', 1)
            ->assertJsonPath('failed', 0)
            ->assertJsonPath('results.0.ok', true)
            ->assertJsonPath('results.0.order_sn', 'PUSH-001')
            ->assertJsonPath('results.0.pos_sell.id', 777)
            ->assertJsonPath('results.0.sync_state', 'matched');

        $log = WorkspaceActivityLog::query()
            ->where('action', 'erp.sales_push')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('PUSH-001', $log->meta['order_sn'] ?? null);
    }

    public function test_push_creates_sell_for_missing_lazada_order(): void
    {
        $user = User::factory()->create();
        ['lazada' => $lazada] = $this->seedStores($user);
        $order = $this->makeOrder($lazada, [
            'order_sn' => 'LAZ-PUSH-1',
            'order_status' => 'delivered',
        ]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(['LAZ-PUSH-1'])
            ->andReturn([], [
                'LAZ-PUSH-1' => [
                    'id' => 888,
                    'invoice_no' => 'LAZ-PUSH-1',
                    'final_total' => 25.0,
                    'status' => 'final',
                    'lines' => [],
                ],
            ]);
        $erp->shouldReceive('resolveVariationBySku')
            ->once()
            ->with('SOAP-01')
            ->andReturn(['product_id' => 55, 'variation_id' => 66, 'sku' => 'SOAP-01']);
        $erp->shouldReceive('buildSellEntry')
            ->once()
            ->withArgs(function ($invoiceNo, $source) {
                return $invoiceNo === 'LAZ-PUSH-1' && $source === 'lazada';
            })
            ->andReturn([
                'location_id' => 10,
                'contact_id' => 37, // ONLINE SALE - LAZADA (CO0037)
                'transaction_date' => now()->toDateTimeString(),
                'invoice_no' => 'LAZ-PUSH-1',
                'source' => 'lazada',
                'status' => 'final',
                'products' => [],
                'payments' => [['amount' => 25.0, 'method' => 'other']],
            ]);
        $erp->shouldReceive('createSell')->once()->andReturn(['success' => 1]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('pushed', 1)
            ->assertJsonPath('results.0.ok', true);
    }

    public function test_compare_matches_lazada_order_by_lazada_invoice_no(): void
    {
        $user = User::factory()->create();
        ['lazada' => $lazada] = $this->seedStores($user);
        $order = $this->makeOrder($lazada, [
            'order_sn' => '509277410820493',
            'raw_response' => [
                'items' => [
                    ['order_item_id' => 123, 'invoice_number' => 'MKT31460'],
                ],
            ],
        ]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->once()
            ->with(Mockery::on(function ($invs) {
                return in_array('MKT31460', $invs, true) && in_array('509277410820493', $invs, true);
            }))
            ->andReturn([
                'MKT31460' => [
                    'id' => 86904,
                    'invoice_no' => 'MKT31460',
                    'transaction_date' => '2026-09-02 15:00:00',
                    'final_total' => 25.0,
                    'status' => 'final',
                    'lines' => [
                        ['sku' => 'SOAP-01', 'product_id' => 55, 'variation_id' => 66, 'quantity' => 2.0, 'unit_price' => 12.5],
                    ],
                ],
            ]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->getJson('/api/erp/sales-compare?platform=lazada');
        $response->assertOk()
            ->assertJsonPath('stats.matched', 1)
            ->assertJsonPath('data.0.order_sn', '509277410820493')
            ->assertJsonPath('data.0.invoice_no', 'MKT31460')
            ->assertJsonPath('data.0.sync_state', 'matched')
            ->assertJsonPath('data.0.pos_sell.invoice_no', 'MKT31460');
    }

    public function test_push_uses_lazada_invoice_no_when_present(): void
    {
        $user = User::factory()->create();
        ['lazada' => $lazada] = $this->seedStores($user);
        $order = $this->makeOrder($lazada, [
            'order_sn' => '509246043128442',
            'order_status' => 'delivered',
            'raw_response' => [
                'items' => [
                    ['order_item_id' => 456, 'invoice_number' => 'TSOC1754'],
                ],
            ],
        ]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(Mockery::on(function ($invs) {
                return in_array('TSOC1754', $invs, true);
            }))
            ->andReturn([], [
                'TSOC1754' => [
                    'id' => 999,
                    'invoice_no' => 'TSOC1754',
                    'final_total' => 25.0,
                    'status' => 'final',
                    'lines' => [],
                ],
            ]);
        $erp->shouldReceive('resolveVariationBySku')
            ->once()
            ->with('SOAP-01')
            ->andReturn(['product_id' => 55, 'variation_id' => 66, 'sku' => 'SOAP-01']);
        $erp->shouldReceive('buildSellEntry')
            ->once()
            ->withArgs(function ($invoiceNo, $source) {
                return $invoiceNo === 'TSOC1754' && $source === 'lazada';
            })
            ->andReturn([
                'location_id' => 10,
                'contact_id' => 37,
                'transaction_date' => now()->toDateTimeString(),
                'invoice_no' => 'TSOC1754',
                'source' => 'lazada',
                'status' => 'final',
                'products' => [],
                'payments' => [['amount' => 25.0, 'method' => 'other']],
            ]);
        $erp->shouldReceive('createSell')->once()->andReturn(['success' => 1]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('pushed', 1)
            ->assertJsonPath('results.0.ok', true)
            ->assertJsonPath('results.0.order_sn', '509246043128442')
            ->assertJsonPath('results.0.invoice_no', 'TSOC1754');
    }

    public function test_push_skips_existing_invoice_and_rejects_unknown_sku(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $exists = $this->makeOrder($store, ['order_sn' => 'EXISTS-1']);
        $badSku = $this->makeOrder($store, ['order_sn' => 'BADSKU-1'], [[
            'item_id' => 9,
            'model_id' => 9,
            'item_name' => 'Unknown',
            'sku' => 'NO-POS-SKU',
            'quantity' => 1,
            'price' => 5,
        ]]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(['EXISTS-1'])
            ->andReturn([
                'EXISTS-1' => [
                    'id' => 99,
                    'invoice_no' => 'EXISTS-1',
                    'transaction_date' => null,
                    'final_total' => 25,
                    'status' => 'final',
                    'lines' => [],
                ],
            ]);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(['BADSKU-1'])
            ->andReturn([]);
        $erp->shouldReceive('resolveVariationBySku')
            ->with('NO-POS-SKU')
            ->andReturn(null);
        $erp->shouldNotReceive('createSell');
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$exists->id, $badSku->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('pushed', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('failed', 1);

        $results = collect($response->json('results'))->keyBy('order_sn');
        $this->assertTrue($results['EXISTS-1']['skipped'] ?? false);
        $this->assertStringContainsString('Unknown SKU', $results['BADSKU-1']['error']);
        $this->assertSame('shopee', $results['BADSKU-1']['platform']);
        $this->assertCount(1, $results['BADSKU-1']['details']);
        $this->assertSame("SKU 'NO-POS-SKU' not found in POS Melur", $results['BADSKU-1']['details'][0]['reason']);
    }

    public function test_push_fails_with_details_when_item_has_no_sku(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $order = $this->makeOrder(
            $store,
            [
                'order_sn' => 'BLANKSKU-1',
                'order_status' => 'SHIPPED',
            ],
            [
                [
                    'item_id' => 1002,
                    'model_id' => 2002,
                    'item_name' => 'LVP - keratan rumpun serai aceh',
                    'sku' => '',
                    'quantity' => 2,
                    'price' => 12.0,
                ],
            ]
        );

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->with(['BLANKSKU-1'])
            ->andReturn([]);
        $erp->shouldNotReceive('createSell');
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('pushed', 0)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.order_sn', 'BLANKSKU-1')
            ->assertJsonPath('results.0.platform', 'shopee')
            ->assertJsonPath('results.0.details.0.item_name', 'LVP - keratan rumpun serai aceh')
            ->assertJsonPath('results.0.details.0.sku', null)
            ->assertJsonPath('results.0.details.0.reason', 'No Seller SKU configured in Shopee');
    }

    public function test_push_rejects_pre_ship_status(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $order = $this->makeOrder($store, [
            'order_sn' => 'RTS-PUSH',
            'order_status' => 'READY_TO_SHIP',
        ]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldNotReceive('createSell');
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->postJson('/api/erp/sales-push', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('results.0.ok', false);
        $this->assertStringContainsString('not eligible', $response->json('results.0.error'));
    }

    public function test_update_pos_prices_updates_existing_sell(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $order = $this->makeOrder($store, ['order_sn' => 'PRICE-001']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')
            ->twice()
            ->with(['PRICE-001'])
            ->andReturn(
                [
                    'PRICE-001' => [
                        'id' => 55,
                        'invoice_no' => 'PRICE-001',
                        'transaction_date' => '2026-08-01 10:00:00',
                        'final_total' => 30.0,
                        'status' => 'final',
                        'contact_id' => 40,
                        'location_id' => 10,
                        'shipping_status' => 'shipped',
                        'source' => 'shopee',
                        'lines' => [
                            ['sku' => 'SOAP-01', 'product_id' => 1, 'variation_id' => 2, 'quantity' => 2.0, 'unit_price' => 15.0],
                        ],
                    ],
                ],
                [
                    'PRICE-001' => [
                        'id' => 55,
                        'invoice_no' => 'PRICE-001',
                        'transaction_date' => '2026-08-01 10:00:00',
                        'final_total' => 20.0,
                        'status' => 'final',
                        'contact_id' => 40,
                        'location_id' => 10,
                        'shipping_status' => 'shipped',
                        'source' => 'shopee',
                        'lines' => [
                            ['sku' => 'SOAP-01', 'product_id' => 1, 'variation_id' => 2, 'quantity' => 2.0, 'unit_price' => 10.0],
                        ],
                    ],
                ],
            );
        $erp->shouldReceive('updateSell')
            ->once()
            ->with(55, Mockery::on(function ($payload) {
                return ($payload['products'][0]['unit_price'] ?? null) == 10.0
                    && ($payload['payments'][0]['amount'] ?? null) == 20.0;
            }))
            ->andReturn(['id' => 55]);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->putJson("/api/erp/sales/{$order->id}/pos-prices", [
            'lines' => [
                [
                    'product_id' => 1,
                    'variation_id' => 2,
                    'quantity' => 2,
                    'unit_price' => 10,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('order_sn', 'PRICE-001')
            ->assertJsonPath('pos_sell.final_total', 20)
            ->assertJsonPath('sync_state', 'mismatch');

        $this->assertDatabaseHas('workspace_activity_logs', [
            'action' => 'erp.sales_price_update',
            'user_id' => $user->id,
        ]);
    }

    public function test_update_pos_prices_rejects_missing_sell(): void
    {
        $user = User::factory()->create();
        ['store' => $store] = $this->seedStores($user);
        $order = $this->makeOrder($store, ['order_sn' => 'NOPOS-1']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('findSellsByInvoiceNos')->once()->with(['NOPOS-1'])->andReturn([]);
        $erp->shouldNotReceive('updateSell');
        $this->app->instance(ErpConnectorService::class, $erp);

        $this->actingAs($user)->putJson("/api/erp/sales/{$order->id}/pos-prices", [
            'lines' => [
                ['product_id' => 1, 'variation_id' => 2, 'quantity' => 1, 'unit_price' => 5],
            ],
        ])->assertStatus(422);
    }
}
