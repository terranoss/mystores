<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGroupedFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(User $user, int $shopId, string $name): ShopeeStore
    {
        return ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'shop_name' => $name,
            'region' => 'MY',
            'is_active' => true,
        ]);
    }

    public function test_grouped_search_matches_name_sku_item_id_and_variant_sku(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 701001, 'Filter Shop');

        $byName = Product::create([
            'store_id' => $store->id,
            'item_id' => 1001,
            'item_name' => 'Monstera Deliciosa',
            'item_sku' => 'MON-01',
            'price' => 20,
            'stock' => 8,
            'synced_at' => now(),
        ]);

        $byItemId = Product::create([
            'store_id' => $store->id,
            'item_id' => 99887766,
            'item_name' => 'Other Plant',
            'item_sku' => null,
            'price' => 10,
            'stock' => 3,
            'synced_at' => now(),
        ]);

        $byVariant = Product::create([
            'store_id' => $store->id,
            'item_id' => 2002,
            'item_name' => 'Variant Plant',
            'item_sku' => 'VAR-01',
            'price' => 15,
            'stock' => 4,
            'has_variants' => true,
            'synced_at' => now(),
        ]);
        ProductVariant::create([
            'product_id' => $byVariant->id,
            'model_id' => 55,
            'name' => 'Small',
            'model_sku' => 'VAR-SMALL-SKU',
            'price' => 15,
            'stock' => 4,
        ]);

        $nameHit = $this->actingAs($user)->getJson('/api/products/grouped?search=Monstera');
        $nameHit->assertOk();
        $nameHit->assertJsonPath('total', 1);
        $nameHit->assertJsonPath('grouped.0.products.0.id', $byName->id);

        $itemHit = $this->actingAs($user)->getJson('/api/products/grouped?search=99887766');
        $itemHit->assertOk();
        $itemHit->assertJsonPath('total', 1);
        $itemHit->assertJsonPath('ungrouped.0.id', $byItemId->id);

        $variantHit = $this->actingAs($user)->getJson('/api/products/grouped?search=VAR-SMALL-SKU');
        $variantHit->assertOk();
        $variantHit->assertJsonPath('total', 1);
        $variantHit->assertJsonPath('grouped.0.products.0.id', $byVariant->id);
    }

    public function test_grouped_filters_by_stock_health_and_link_status(): void
    {
        $user = User::factory()->create();
        $storeA = $this->makeStore($user, 701002, 'Shop A');
        $storeB = $this->makeStore($user, 701003, 'Shop B');

        Product::create([
            'store_id' => $storeA->id,
            'item_id' => 1,
            'item_name' => 'Out Plant',
            'item_sku' => 'OUT-1',
            'price' => 5,
            'stock' => 0,
            'synced_at' => now(),
        ]);

        Product::create([
            'store_id' => $storeA->id,
            'item_id' => 2,
            'item_name' => 'Low Plant',
            'item_sku' => null,
            'price' => 5,
            'stock' => 3,
            'synced_at' => now(),
        ]);

        Product::create([
            'store_id' => $storeA->id,
            'item_id' => 3,
            'item_name' => 'In Plant A',
            'item_sku' => 'SHARED',
            'price' => 12,
            'stock' => 20,
            'synced_at' => now(),
        ]);

        Product::create([
            'store_id' => $storeB->id,
            'item_id' => 4,
            'item_name' => 'In Plant B',
            'item_sku' => 'SHARED',
            'price' => 12,
            'stock' => 18,
            'synced_at' => now(),
        ]);

        Product::create([
            'store_id' => $storeA->id,
            'item_id' => 5,
            'item_name' => 'Variant Only',
            'item_sku' => 'VAR-ONLY',
            'price' => 9,
            'stock' => 7,
            'has_variants' => true,
            'synced_at' => now(),
        ]);

        $out = $this->actingAs($user)->getJson('/api/products/grouped?stock_health=out');
        $out->assertOk();
        $out->assertJsonPath('total', 1);
        $out->assertJsonPath('grouped.0.sku', 'OUT-1');

        $low = $this->actingAs($user)->getJson('/api/products/grouped?stock_health=low');
        $low->assertOk();
        $low->assertJsonPath('total', 1);
        $low->assertJsonPath('ungrouped.0.item_name', 'Low Plant');

        $linked = $this->actingAs($user)->getJson('/api/products/grouped?link_status=linked');
        $linked->assertOk();
        $this->assertSame(0, count($linked->json('ungrouped')));
        $this->assertGreaterThanOrEqual(2, (int) $linked->json('total'));

        $unlinked = $this->actingAs($user)->getJson('/api/products/grouped?link_status=unlinked');
        $unlinked->assertOk();
        $unlinked->assertJsonPath('total', 1);
        $unlinked->assertJsonPath('ungrouped.0.item_name', 'Low Plant');

        $multi = $this->actingAs($user)->getJson('/api/products/grouped?link_status=multi_store');
        $multi->assertOk();
        $multi->assertJsonPath('total', 1);
        $multi->assertJsonPath('grouped.0.sku', 'SHARED');
        $this->assertCount(2, $multi->json('grouped.0.products'));

        $variants = $this->actingAs($user)->getJson('/api/products/grouped?link_status=variants');
        $variants->assertOk();
        $variants->assertJsonPath('total', 1);
        $variants->assertJsonPath('grouped.0.sku', 'VAR-ONLY');
    }

    public function test_grouped_sorts_by_stock_ascending(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 701004, 'Sort Shop');

        Product::create([
            'store_id' => $store->id,
            'item_id' => 10,
            'item_name' => 'High Stock',
            'item_sku' => null,
            'price' => 5,
            'stock' => 40,
            'synced_at' => now()->subDay(),
        ]);

        Product::create([
            'store_id' => $store->id,
            'item_id' => 11,
            'item_name' => 'Low Stock',
            'item_sku' => null,
            'price' => 5,
            'stock' => 2,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/products/grouped?sort_by=stock&sort_dir=asc'
        );
        $response->assertOk();
        $response->assertJsonPath('ungrouped.0.item_name', 'Low Stock');
        $response->assertJsonPath('ungrouped.1.item_name', 'High Stock');
    }
}
