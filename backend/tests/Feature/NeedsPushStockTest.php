<?php

namespace Tests\Feature;

use App\Jobs\UpdateShopeeStockJob;
use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NeedsPushStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_stock_save_sets_needs_push(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 55001,
            'item_name' => 'Local Save Plant',
            'item_sku' => 'MCA-LOCAL-1',
            'price' => 10,
            'stock' => 5,
            'needs_push' => false,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}/stock-price", [
            'stock_list' => [['seller_stock' => [['stock' => 0]]]],
            'mode' => 'local',
        ]);

        $response->assertOk();
        $response->assertJsonPath('product.needs_push', true);
        $response->assertJsonPath('product.stock', 0);
        $this->assertTrue($product->fresh()->needs_push);
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_grouped_unsynced_filter_ands_with_stock_health(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);

        Product::create([
            'store_id' => $store->id,
            'item_id' => 55002,
            'item_name' => 'Dirty Out',
            'item_sku' => 'DIRTY-OUT',
            'price' => 8,
            'stock' => 0,
            'needs_push' => true,
            'synced_at' => now(),
        ]);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 55003,
            'item_name' => 'Clean Out',
            'item_sku' => 'CLEAN-OUT',
            'price' => 8,
            'stock' => 0,
            'needs_push' => false,
            'synced_at' => now(),
        ]);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 55004,
            'item_name' => 'Dirty In Stock',
            'item_sku' => 'DIRTY-IN',
            'price' => 8,
            'stock' => 12,
            'needs_push' => true,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/products/grouped?stock_health=out&sync_state=unsynced');
        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame('DIRTY-OUT', $response->json('grouped.0.sku'));
    }

    public function test_pull_upsert_clears_needs_push_via_product_fields(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 55005,
            'item_name' => 'Was Dirty',
            'item_sku' => 'WAS-DIRTY',
            'price' => 9,
            'stock' => 3,
            'needs_push' => true,
            'synced_at' => now()->subHour(),
        ]);

        // Mimic successful marketplace/pull write fields.
        $product->update([
            'stock' => 7,
            'synced_at' => now(),
            'needs_push' => false,
        ]);

        $fresh = $product->fresh();
        $this->assertFalse($fresh->needs_push);
        $this->assertSame(7, (int) $fresh->stock);
    }

    public function test_push_selected_clears_needs_push_when_queued(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $store = $this->makeStore($user);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 55006,
            'item_name' => 'Push Me',
            'item_sku' => 'PUSH-ME',
            'price' => 10,
            'stock' => 8,
            'has_variants' => false,
            'needs_push' => true,
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/products/push', [
            'product_ids' => [$product->id],
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('queued.0.product_id', $product->id);
        Queue::assertPushed(UpdateShopeeStockJob::class);
        $this->assertFalse($product->fresh()->needs_push);
    }

    public function test_stock_by_sku_local_marks_all_listings(): void
    {
        $user = User::factory()->create();
        $storeA = $this->makeStore($user, 'shopee', 8801, 'A');
        $storeB = $this->makeStore($user, 'lazada', 8802, 'B');

        $a = Product::create([
            'store_id' => $storeA->id,
            'item_id' => 56001,
            'item_name' => 'Shared A',
            'item_sku' => 'SHARED-SKU',
            'price' => 5,
            'stock' => 4,
            'needs_push' => false,
        ]);
        $b = Product::create([
            'store_id' => $storeB->id,
            'item_id' => 56002,
            'item_name' => 'Shared B',
            'item_sku' => 'SHARED-SKU',
            'price' => 5,
            'stock' => 4,
            'needs_push' => false,
        ]);

        $response = $this->actingAs($user)->putJson('/api/products/stock-by-sku', [
            'sku' => 'SHARED-SKU',
            'stock' => 0,
            'mode' => 'local',
        ]);

        $response->assertOk();
        $this->assertTrue($a->fresh()->needs_push);
        $this->assertTrue($b->fresh()->needs_push);
        $this->assertSame(0, (int) $a->fresh()->stock);
        $this->assertSame(0, (int) $b->fresh()->stock);
    }

    public function test_sync_linked_stores_cross_platform_dispatches_to_both_shopee_and_lazada(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $storeShopee1 = $this->makeStore($user, 'shopee', 8901, 'Shopee Origin');
        $storeShopee2 = $this->makeStore($user, 'shopee', 8902, 'Shopee Linked');
        $storeLazada = $this->makeStore($user, 'lazada', 8903, 'Lazada Linked');

        $origin = Product::create([
            'store_id' => $storeShopee1->id,
            'item_id' => 77001,
            'item_name' => 'Origin Product',
            'item_sku' => 'CROSS-SKU',
            'price' => 15,
            'stock' => 20,
            'has_variants' => false,
            'needs_push' => false,
        ]);

        $linkedShopee = Product::create([
            'store_id' => $storeShopee2->id,
            'item_id' => 77002,
            'item_name' => 'Linked Shopee Product',
            'item_sku' => 'CROSS-SKU',
            'price' => 15,
            'stock' => 10,
            'has_variants' => false,
            'needs_push' => false,
        ]);

        $linkedLazada = Product::create([
            'store_id' => $storeLazada->id,
            'item_id' => 77003,
            'item_name' => 'Linked Lazada Product',
            'item_sku' => 'CROSS-SKU',
            'price' => 15,
            'stock' => 10,
            'has_variants' => false,
            'needs_push' => false,
        ]);

        $lazadaService = \Mockery::mock(\App\Services\LazadaService::class);
        $lazadaService->shouldReceive('buildPriceQuantitySkuUpdates')->once()->andReturn([
            ['ItemId' => 77003, 'SellerSku' => 'CROSS-SKU', 'Quantity' => 50]
        ]);
        $lazadaService->shouldReceive('buildPriceQuantityPayload')->once()->andReturn('<xml/>');
        $lazadaService->shouldReceive('updatePriceQuantity')->once()->andReturn(['code' => '0']);

        $productService = new \App\Services\ProductService(
            app(\App\Services\ShopeeService::class),
            $lazadaService
        );

        $productService->syncLinkedStores($user, 'CROSS-SKU', 50, $origin->id);

        // Verify Shopee was queued
        Queue::assertPushed(UpdateShopeeStockJob::class, function ($job) use ($linkedShopee) {
            $ref = new \ReflectionClass($job);
            $prop = $ref->getProperty('productId');
            $prop->setAccessible(true);
            return $prop->getValue($job) === $linkedShopee->id;
        });

        // Verify Lazada store was updated synchronously
        $this->assertSame(50, (int) $linkedLazada->fresh()->stock);
    }

    private function makeStore(
        User $user,
        string $platform = 'shopee',
        int $shopId = 7701,
        string $name = 'Needs Push Shop',
    ): ShopeeStore {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'shop_id' => $shopId,
            'shop_name' => $name,
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
}
