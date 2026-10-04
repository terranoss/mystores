<?php

namespace Tests\Feature;

use App\Models\PosInventory;
use App\Models\User;
use App\Services\ErpConnectorService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpSyncPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProduct(
        int $id,
        string $sku,
        float $price = 10.0,
        int $qty = 5,
        ?array $category = null,
        ?array $subCategory = null,
        ?array $brand = null,
    ): array {
        $product = [
            'id' => $id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'default_sell_price' => $price,
            'product_variations' => [
                [
                    'variations' => [
                        [
                            'sell_price_inc_tax' => $price,
                            'variation_location_details' => [
                                ['qty_available' => $qty],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if ($category !== null) {
            $product['category'] = $category;
        }
        if ($subCategory !== null) {
            $product['sub_category'] = $subCategory;
        }
        if ($brand !== null) {
            $product['brand'] = $brand;
        }

        return $product;
    }

    public function test_parse_product_maps_category_and_brand(): void
    {
        $parsed = app(ErpConnectorService::class)->parseProduct(
            $this->fakeProduct(
                7,
                'CAT-SKU',
                12.5,
                3,
                ['id' => 1, 'name' => "Men's"],
                ['id' => 5, 'name' => 'Shirts'],
                ['id' => 10, 'name' => 'Levis'],
            )
        );

        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['category_id']);
        $this->assertSame("Men's", $parsed['category_name']);
        $this->assertSame(5, $parsed['sub_category_id']);
        $this->assertSame('Shirts', $parsed['sub_category_name']);
        $this->assertSame(10, $parsed['brand_id']);
        $this->assertSame('Levis', $parsed['brand_name']);
    }

    public function test_parse_product_uses_subcategory_parent_as_category_id(): void
    {
        $parsed = app(ErpConnectorService::class)->parseProduct(
            $this->fakeProduct(
                8,
                'SUB-ONLY',
                9,
                2,
                null,
                ['id' => 11, 'name' => 'Hiasan', 'parent_id' => 1],
            )
        );

        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['category_id']);
        $this->assertNull($parsed['category_name']);
        $this->assertSame(11, $parsed['sub_category_id']);
    }

    public function test_pull_and_sync_resolves_category_name_from_taxonomy(): void
    {
        config([
            'erp.base_url' => 'https://pos.example.test',
            'erp.client_id' => '1',
            'erp.client_secret' => 'secret',
            'erp.username' => 'Admin',
            'erp.password' => 'password',
        ]);
        Cache::forget('erp_access_token');

        Http::fake([
            'pos.example.test/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'pos.example.test/connector/api/taxonomy*' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Tanaman'],
                ],
            ], 200),
            'pos.example.test/connector/api/product*' => Http::response([
                'data' => [
                    $this->fakeProduct(
                        1,
                        'SUB-ONLY',
                        15,
                        4,
                        null,
                        ['id' => 11, 'name' => 'Hiasan', 'parent_id' => 1],
                    ),
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => 1,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $synced = app(ErpConnectorService::class)->pullAndSync($user);
        $this->assertSame(1, $synced);

        $row = PosInventory::where('sku', 'SUB-ONLY')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->category_id);
        $this->assertSame('Tanaman', $row->category_name);
    }

    public function test_pull_and_sync_persists_category_and_brand(): void
    {
        config([
            'erp.base_url' => 'https://pos.example.test',
            'erp.client_id' => '1',
            'erp.client_secret' => 'secret',
            'erp.username' => 'Admin',
            'erp.password' => 'password',
        ]);
        Cache::forget('erp_access_token');

        Http::fake([
            'pos.example.test/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'pos.example.test/connector/api/product*' => Http::response([
                'data' => [
                    $this->fakeProduct(
                        1,
                        'BRAND-1',
                        15,
                        4,
                        ['id' => 2, 'name' => "Women's"],
                        ['id' => 8, 'name' => 'Tops'],
                        ['id' => 11, 'name' => 'Nike'],
                    ),
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => 1,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $synced = app(ErpConnectorService::class)->pullAndSync($user);
        $this->assertSame(1, $synced);

        $row = PosInventory::where('sku', 'BRAND-1')->first();
        $this->assertNotNull($row);
        $this->assertSame(2, $row->category_id);
        $this->assertSame("Women's", $row->category_name);
        $this->assertSame(8, $row->sub_category_id);
        $this->assertSame('Tops', $row->sub_category_name);
        $this->assertSame(11, $row->brand_id);
        $this->assertSame('Nike', $row->brand_name);
    }

    public function test_pull_and_sync_fetches_all_pages_using_meta_last_page(): void
    {
        config([
            'erp.base_url' => 'https://pos.example.test',
            'erp.client_id' => '1',
            'erp.client_secret' => 'secret',
            'erp.username' => 'Admin',
            'erp.password' => 'password',
        ]);
        Cache::forget('erp_access_token');

        $page1 = [];
        for ($i = 1; $i <= 100; $i++) {
            $page1[] = $this->fakeProduct($i, sprintf('SKU-%03d', $i));
        }
        $page2 = [
            $this->fakeProduct(101, 'SKU-101'),
            $this->fakeProduct(102, 'SKU-102'),
        ];

        Http::fake([
            'pos.example.test/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'pos.example.test/connector/api/product*' => function ($request) use ($page1, $page2) {
                $page = (int) ($request['page'] ?? 1);
                if ($page === 1) {
                    return Http::response([
                        'data' => $page1,
                        'meta' => [
                            'current_page' => 1,
                            'last_page' => 2,
                            'per_page' => 100,
                            'total' => 102,
                        ],
                    ], 200);
                }
                return Http::response([
                    'data' => $page2,
                    'meta' => [
                        'current_page' => 2,
                        'last_page' => 2,
                        'per_page' => 100,
                        'total' => 102,
                    ],
                ], 200);
            },
        ]);

        $user = User::factory()->create();
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $synced = app(ErpConnectorService::class)->pullAndSync($user);

        $this->assertSame(102, $synced);
        $this->assertSame(102, PosInventory::count());
        $this->assertTrue(PosInventory::where('sku', 'SKU-001')->exists());
        $this->assertTrue(PosInventory::where('sku', 'SKU-101')->exists());
        $this->assertTrue(PosInventory::where('sku', 'SKU-102')->exists());

        Http::assertSentCount(3); // token + 2 product pages
    }

    public function test_pull_and_sync_continues_when_last_page_missing_and_page_is_full(): void
    {
        config([
            'erp.base_url' => 'https://pos.example.test',
            'erp.client_id' => '1',
            'erp.client_secret' => 'secret',
            'erp.username' => 'Admin',
            'erp.password' => 'password',
        ]);
        Cache::forget('erp_access_token');

        $page1 = [];
        for ($i = 1; $i <= 100; $i++) {
            $page1[] = $this->fakeProduct($i, sprintf('FULL-%03d', $i));
        }
        $page2 = [
            $this->fakeProduct(201, 'FULL-201'),
        ];

        Http::fake([
            'pos.example.test/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'pos.example.test/connector/api/product*' => function ($request) use ($page1, $page2) {
                $page = (int) ($request['page'] ?? 1);
                if ($page === 1) {
                    return Http::response(['data' => $page1], 200);
                }
                return Http::response(['data' => $page2], 200);
            },
        ]);

        $user = User::factory()->create();
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $synced = app(ErpConnectorService::class)->pullAndSync($user);

        $this->assertSame(101, $synced);
        $this->assertSame(101, PosInventory::count());
        $this->assertTrue(PosInventory::where('sku', 'FULL-201')->exists());
    }
}
