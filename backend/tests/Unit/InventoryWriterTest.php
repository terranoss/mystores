<?php

namespace Tests\Unit;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Services\InventoryWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWriterTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = [], bool $withVariants = false): Product
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 99001,
            'shop_name' => 'Writer Test Store',
            'region' => 'my',
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'store_id' => $store->id,
            'item_id' => 88001,
            'item_name' => 'Writer Test Product',
            'item_sku' => 'WRITER-SIMPLE',
            'price' => 25.00,
            'stock' => 10,
            'has_variants' => $withVariants,
            'needs_push' => false,
            'synced_at' => now(),
        ], $overrides));
    }

    private function writer(): InventoryWriter
    {
        return app(InventoryWriter::class);
    }

    public function test_apply_records_one_success_row_for_a_simple_stock_change(): void
    {
        $product = $this->makeProduct();

        $this->writer()->apply($product, [
            ['seller_stock' => [['stock' => 18]]],
        ], null, 'user');

        $product->refresh();
        $this->assertSame(18, (int) $product->stock);
        $this->assertFalse($product->needs_push);
        $this->assertNotNull($product->synced_at);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('success', $logs[0]->status);
        $this->assertNull($logs[0]->product_variant_id);
        $this->assertSame('WRITER-SIMPLE', $logs[0]->sku);
        $this->assertSame(10, (int) $logs[0]->old_stock);
        $this->assertSame(18, (int) $logs[0]->new_stock);
        $this->assertNull($logs[0]->old_price);
        $this->assertNull($logs[0]->new_price);
        $this->assertSame('user', $logs[0]->source);
    }

    public function test_apply_records_one_row_per_variant_and_rolls_up_the_parent_stock(): void
    {
        $product = $this->makeProduct(['item_sku' => 'WRITER-VAR-PARENT'], true);
        $small = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 501,
            'name' => 'Small',
            'model_sku' => 'WRITER-VAR-S',
            'price' => 15.00,
            'stock' => 8,
        ]);
        $large = ProductVariant::create([
            'product_id' => $product->id,
            'model_id' => 502,
            'name' => 'Large',
            'model_sku' => 'WRITER-VAR-L',
            'price' => 20.00,
            'stock' => 12,
        ]);

        $this->writer()->apply($product, [
            ['model_id' => 501, 'seller_stock' => [['stock' => 14]]],
            ['model_id' => 502, 'seller_stock' => [['stock' => 16]]],
        ], null, 'local_staged');

        $this->assertSame(14, (int) $small->fresh()->stock);
        $this->assertSame(16, (int) $large->fresh()->stock);
        $this->assertSame(30, (int) $product->fresh()->stock);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn (InventoryLog $log) => $log->status === 'success'));
        $this->assertTrue($logs->every(fn (InventoryLog $log) => $log->source === 'local_staged'));
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $small->id,
            'sku' => 'WRITER-VAR-S',
            'old_stock' => 8,
            'new_stock' => 14,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'product_variant_id' => $large->id,
            'sku' => 'WRITER-VAR-L',
            'old_stock' => 12,
            'new_stock' => 16,
        ]);
    }

    public function test_apply_records_price_change_with_old_and_new_values(): void
    {
        $product = $this->makeProduct();

        $this->writer()->apply($product, null, [
            ['original_price' => 29.50],
        ], 'user');

        $product->refresh();
        $this->assertSame('29.50', (string) $product->price);

        $logs = InventoryLog::where('product_id', $product->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame('success', $logs[0]->status);
        $this->assertNull($logs[0]->old_stock);
        $this->assertNull($logs[0]->new_stock);
        $this->assertSame(25.0, (float) $logs[0]->old_price);
        $this->assertSame(29.5, (float) $logs[0]->new_price);
        $this->assertSame('user', $logs[0]->source);
    }

    public function test_begin_then_succeed_closes_the_pending_row_instead_of_adding_one(): void
    {
        $product = $this->makeProduct();

        $log = $this->writer()->begin(
            $product,
            null,
            'WRITER-SIMPLE',
            10,
            18,
            null,
            null,
            'user',
        );

        $this->assertSame('pending', $log->fresh()->status);
        $this->assertSame(1, InventoryLog::where('product_id', $product->id)->count());

        $this->writer()->succeed($log);

        $this->assertSame('success', $log->fresh()->status);
        $this->assertSame(1, InventoryLog::where('product_id', $product->id)->count());
    }

    public function test_begin_then_fail_records_the_error_message(): void
    {
        $product = $this->makeProduct();

        $log = $this->writer()->begin(
            $product,
            null,
            'WRITER-SIMPLE',
            10,
            18,
            null,
            null,
            'user',
        );

        $this->writer()->fail($log, 'Shopee rejected the stock update');

        $fresh = $log->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Shopee rejected the stock update', $fresh->error_message);
        $this->assertSame(1, InventoryLog::where('product_id', $product->id)->count());
    }

    public function test_fail_restores_needs_push_only_when_asked(): void
    {
        $restored = $this->makeProduct(['item_sku' => 'WRITER-DIRTY'], false);
        $untouched = $this->makeProduct(['item_id' => 88002, 'item_sku' => 'WRITER-CLEAN'], false);

        $restoredLog = $this->writer()->begin($restored, null, 'WRITER-DIRTY', 10, 18, null, null, 'user');
        $untouchedLog = $this->writer()->begin($untouched, null, 'WRITER-CLEAN', 10, 18, null, null, 'user');

        $this->writer()->fail($restoredLog, 'push failed', true);
        $this->writer()->fail($untouchedLog, 'push failed');

        $this->assertTrue($restored->fresh()->needs_push);
        $this->assertFalse($untouched->fresh()->needs_push);
    }
}
