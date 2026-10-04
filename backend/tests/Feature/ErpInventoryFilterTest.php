<?php

namespace Tests\Feature;

use App\Models\PosInventory;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpInventoryFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, workspaceId: int, shopee: ShopeeStore, lazada: ShopeeStore}
     */
    private function seedWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $shopee = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 501,
            'shop_name' => 'Shopee Shop',
            'platform' => 'shopee',
            'is_active' => true,
            'linked_at' => now(),
        ]);
        $lazada = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 502,
            'shop_name' => 'Lazada Shop',
            'platform' => 'lazada',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        return [
            'user' => $user,
            'workspaceId' => $workspace->id,
            'shopee' => $shopee,
            'lazada' => $lazada,
        ];
    }

    private function seedPos(
        int $workspaceId,
        string $sku,
        string $name,
        int $stock,
        float $price,
        array $extra = [],
    ): PosInventory {
        return PosInventory::create(array_merge([
            'workspace_id' => $workspaceId,
            'pos_product_id' => crc32($sku) % 100000,
            'sku' => $sku,
            'name' => $name,
            'price' => $price,
            'total_stock' => $stock,
            'synced_at' => now(),
        ], $extra));
    }

    private function seedProduct(ShopeeStore $store, string $sku, int $stock, float $price): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'item_id' => random_int(1000, 999999),
            'item_name' => $sku,
            'item_sku' => $sku,
            'stock' => $stock,
            'price' => $price,
            'synced_at' => now(),
        ]);
    }

    public function test_stock_out_and_low_filters(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'OUT-1', 'Out Item', 0, 10);
        $this->seedPos($ws, 'LOW-1', 'Low Item', 3, 10);
        $this->seedPos($ws, 'OK-1', 'Ok Item', 20, 10);

        $out = $this->actingAs($user)->getJson('/api/erp/inventory?stock=out&per_page=50');
        $out->assertOk();
        $this->assertSame(1, $out->json('meta.total'));
        $this->assertSame(['OUT-1'], collect($out->json('data'))->pluck('sku')->all());

        $low = $this->actingAs($user)->getJson('/api/erp/inventory?stock=low&per_page=50');
        $low->assertOk();
        $this->assertSame(1, $low->json('meta.total'));
        $this->assertSame(['LOW-1'], collect($low->json('data'))->pluck('sku')->all());
    }

    public function test_platform_and_store_filters(): void
    {
        ['user' => $user, 'workspaceId' => $ws, 'shopee' => $shopee, 'lazada' => $lazada] = $this->seedWorkspace();
        $this->seedPos($ws, 'SKU-S', 'Shopee Only', 5, 12);
        $this->seedPos($ws, 'SKU-L', 'Lazada Only', 5, 12);
        $this->seedPos($ws, 'SKU-U', 'Unmatched', 5, 12);
        $this->seedProduct($shopee, 'SKU-S', 5, 12);
        $this->seedProduct($lazada, 'SKU-L', 5, 12);

        $shopeeRes = $this->actingAs($user)->getJson('/api/erp/inventory?platform=shopee&per_page=50');
        $shopeeRes->assertOk();
        $this->assertSame(1, $shopeeRes->json('meta.total'));
        $this->assertSame('SKU-S', $shopeeRes->json('data.0.sku'));

        $storeRes = $this->actingAs($user)->getJson('/api/erp/inventory?store_id='.$lazada->id.'&per_page=50');
        $storeRes->assertOk();
        $this->assertSame(1, $storeRes->json('meta.total'));
        $this->assertSame('SKU-L', $storeRes->json('data.0.sku'));
        $this->assertNotEmpty($storeRes->json('filter_options.stores'));
    }

    public function test_in_sync_and_mismatch_filters_with_accurate_totals(): void
    {
        ['user' => $user, 'workspaceId' => $ws, 'shopee' => $shopee] = $this->seedWorkspace();
        $this->seedPos($ws, 'SYNC-1', 'Synced', 10, 20);
        $this->seedPos($ws, 'STK-1', 'Stock Diff', 10, 20);
        $this->seedPos($ws, 'PRC-1', 'Price Diff', 10, 20);
        $this->seedPos($ws, 'NONE-1', 'Unmatched', 10, 20);
        $this->seedProduct($shopee, 'SYNC-1', 10, 20);
        $this->seedProduct($shopee, 'STK-1', 2, 20);
        $this->seedProduct($shopee, 'PRC-1', 10, 99);

        $sync = $this->actingAs($user)->getJson('/api/erp/inventory?filter=in_sync&per_page=50');
        $sync->assertOk();
        $this->assertSame(1, $sync->json('meta.total'));
        $this->assertCount(1, $sync->json('data'));
        $this->assertSame('SYNC-1', $sync->json('data.0.sku'));

        $stock = $this->actingAs($user)->getJson('/api/erp/inventory?filter=stock_mismatch&per_page=50');
        $stock->assertOk();
        $this->assertSame(1, $stock->json('meta.total'));
        $this->assertSame('STK-1', $stock->json('data.0.sku'));

        $price = $this->actingAs($user)->getJson('/api/erp/inventory?filter=price_mismatch&per_page=50');
        $price->assertOk();
        $this->assertSame(1, $price->json('meta.total'));
        $this->assertSame('PRC-1', $price->json('data.0.sku'));

        $unmatched = $this->actingAs($user)->getJson('/api/erp/inventory?filter=unmatched&per_page=50');
        $unmatched->assertOk();
        $this->assertSame(1, $unmatched->json('meta.total'));
        $this->assertSame('NONE-1', $unmatched->json('data.0.sku'));
    }

    public function test_sort_by_stock_desc(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'A', 'Alpha', 1, 5);
        $this->seedPos($ws, 'B', 'Beta', 50, 5);
        $this->seedPos($ws, 'C', 'Gamma', 10, 5);

        $res = $this->actingAs($user)->getJson('/api/erp/inventory?sort=stock&dir=desc&per_page=50');
        $res->assertOk();
        $this->assertSame(['B', 'C', 'A'], collect($res->json('data'))->pluck('sku')->all());
    }

    public function test_sort_by_sku_and_category(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'Z-1', 'Zeta', 1, 5, ['category_id' => 2, 'category_name' => 'Beta Cat']);
        $this->seedPos($ws, 'A-1', 'Alpha', 1, 5, ['category_id' => 1, 'category_name' => 'Alpha Cat']);
        $this->seedPos($ws, 'M-1', 'Mu', 1, 5, ['category_id' => 3, 'category_name' => 'Gamma Cat']);

        $bySku = $this->actingAs($user)->getJson('/api/erp/inventory?sort=sku&dir=asc&per_page=50');
        $bySku->assertOk();
        $this->assertSame(['A-1', 'M-1', 'Z-1'], collect($bySku->json('data'))->pluck('sku')->all());

        $byCat = $this->actingAs($user)->getJson('/api/erp/inventory?sort=category&dir=asc&per_page=50');
        $byCat->assertOk();
        $this->assertSame(['A-1', 'Z-1', 'M-1'], collect($byCat->json('data'))->pluck('sku')->all());
    }

    public function test_category_filter(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'MEN-1', 'Men Shirt', 5, 20, [
            'category_id' => 1,
            'category_name' => "Men's",
        ]);
        $this->seedPos($ws, 'WOM-1', 'Women Top', 5, 20, [
            'category_id' => 2,
            'category_name' => "Women's",
        ]);
        $this->seedPos($ws, 'MEN-2', 'Men Pants', 5, 20, [
            'category_id' => 1,
            'category_name' => "Men's",
        ]);

        $byCat = $this->actingAs($user)->getJson('/api/erp/inventory?category_id=1&per_page=50');
        $byCat->assertOk();
        $this->assertSame(2, $byCat->json('meta.total'));
        $this->assertSame(['MEN-1', 'MEN-2'], collect($byCat->json('data'))->pluck('sku')->sort()->values()->all());
        $this->assertSame("Men's", $byCat->json('data.0.category_name'));
        $this->assertArrayNotHasKey('brand_name', $byCat->json('data.0'));
        $this->assertArrayNotHasKey('sub_category_name', $byCat->json('data.0'));

        $opts = $this->actingAs($user)->getJson('/api/erp/inventory?per_page=50');
        $opts->assertOk();
        $this->assertCount(2, $opts->json('filter_options.categories'));
        $this->assertArrayNotHasKey('brands', $opts->json('filter_options'));
        $this->assertSame(['id', 'name'], array_keys($opts->json('filter_options.categories.0')));
    }

    public function test_search_matches_all_words_in_any_order_case_insensitive(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'IX-1', 'Ixora coccinea putih garden', 5, 10);
        $this->seedPos($ws, 'IX-2', 'Ixora merah besar', 5, 10);
        $this->seedPos($ws, 'OTH-1', 'Hibiscus putih', 5, 10);

        $res = $this->actingAs($user)->getJson('/api/erp/inventory?search='.urlencode('putih ixora').'&per_page=50');
        $res->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('IX-1', $res->json('data.0.sku'));
    }

    public function test_search_single_word_still_works(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'IX-1', 'Ixora coccinea putih', 5, 10);
        $this->seedPos($ws, 'HIB-1', 'Hibiscus rosa', 5, 10);

        $res = $this->actingAs($user)->getJson('/api/erp/inventory?search=ixora&per_page=50');
        $res->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('IX-1', $res->json('data.0.sku'));
    }

    public function test_search_escapes_like_wildcards(): void
    {
        ['user' => $user, 'workspaceId' => $ws] = $this->seedWorkspace();
        $this->seedPos($ws, 'PCT-1', 'Discount 50% off sale', 5, 10);
        $this->seedPos($ws, 'ANY-1', 'Anything else', 5, 10);

        $res = $this->actingAs($user)->getJson('/api/erp/inventory?search='.urlencode('50%').'&per_page=50');
        $res->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame('PCT-1', $res->json('data.0.sku'));
    }
}
