<?php

namespace Tests\Feature;

use App\Models\PosInventory;
use App\Models\User;
use App\Models\WorkspaceActivityLog;
use App\Services\ErpConnectorService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ErpStockUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeErpItem(User $user, array $overrides = []): PosInventory
    {
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        return PosInventory::create(array_merge([
            'workspace_id' => $workspace->id,
            'pos_product_id' => 9001,
            'sku' => 'FIELD-SKU-1',
            'name' => 'Field Hibiscus',
            'price' => 12.50,
            'total_stock' => 8,
            'synced_at' => now(),
        ], $overrides));
    }

    public function test_update_stock_success_writes_cache_activity_and_diff(): void
    {
        $user = User::factory()->create();
        $item = $this->makeErpItem($user);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')
            ->once()
            ->with($item->pos_product_id, 8, 15);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 15,
            'note' => 'Counted shelf B',
            'source' => 'mobile_field',
        ]);

        $response->assertOk()
            ->assertJsonPath('old_stock', 8)
            ->assertJsonPath('new_stock', 15)
            ->assertJsonPath('item.total_stock', 15)
            ->assertJsonPath('item.stock_note', 'Counted shelf B')
            ->assertJsonPath('message', 'POS Melur stock (Melur Tumpat) updated to 15');

        $this->assertDatabaseHas('pos_inventories', [
            'id' => $item->id,
            'total_stock' => 15,
            'stock_note' => 'Counted shelf B',
            'stock_updated_by' => $user->id,
        ]);
        $item->refresh();
        $this->assertNotNull($item->stock_updated_at);
        $this->assertSame($user->id, $item->stock_updated_by);
        $this->assertSame('Counted shelf B', $item->stock_note);

        $log = WorkspaceActivityLog::query()
            ->where('action', 'erp.update_stock')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('FIELD-SKU-1', $log->meta['sku'] ?? null);
        $this->assertSame(8, $log->meta['old_stock'] ?? null);
        $this->assertSame(15, $log->meta['new_stock'] ?? null);
        $this->assertSame('mobile_field', $log->meta['source'] ?? null);
        $this->assertSame('Counted shelf B', $log->meta['note'] ?? null);
        $this->assertArrayHasKey('location_id', $log->meta ?? []);
        $this->assertArrayHasKey('pos_product_id', $log->meta ?? []);
    }

    public function test_update_stock_blank_note_clears_stock_note(): void
    {
        $user = User::factory()->create();
        $item = $this->makeErpItem($user, ['stock_note' => 'Shelf A row 3']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')
            ->once()
            ->with($item->pos_product_id, 8, 10);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 10,
            'note' => '   ',
            'source' => 'mobile_field',
        ]);

        $response->assertOk()
            ->assertJsonPath('item.stock_note', null);

        $item->refresh();
        $this->assertNull($item->stock_note);
    }

    public function test_update_stock_omitted_note_preserves_stock_note(): void
    {
        $user = User::factory()->create();
        $item = $this->makeErpItem($user, ['stock_note' => 'Keep me']);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')
            ->once()
            ->with($item->pos_product_id, 8, 11);
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 11,
            'source' => 'mobile_field',
        ]);

        $response->assertOk()
            ->assertJsonPath('item.stock_note', 'Keep me');

        $item->refresh();
        $this->assertSame('Keep me', $item->stock_note);
    }

    public function test_update_stock_failure_returns_502_without_cache_or_activity(): void
    {
        $user = User::factory()->create();
        $item = $this->makeErpItem($user, ['total_stock' => 4]);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')
            ->once()
            ->andThrow(new \RuntimeException('Could not locate variation ID for POS product #9001'));
        $this->app->instance(ErpConnectorService::class, $erp);

        $response = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 9,
            'source' => 'mobile_field',
        ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'Could not locate variation ID for POS product #9001');

        $this->assertDatabaseHas('pos_inventories', [
            'id' => $item->id,
            'total_stock' => 4,
        ]);

        $this->assertDatabaseMissing('workspace_activity_logs', [
            'action' => 'erp.update_stock',
            'user_id' => $user->id,
        ]);
    }

    public function test_update_stock_validation_errors_return_422(): void
    {
        $user = User::factory()->create();
        $item = $this->makeErpItem($user);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')->never();
        $this->app->instance(ErpConnectorService::class, $erp);

        $missing = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", []);
        $missing->assertStatus(422);

        $negative = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => -1,
        ]);
        $negative->assertStatus(422);

        $badSource = $this->actingAs($user)->putJson("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 1,
            'source' => 'invalid',
        ]);
        $badSource->assertStatus(422);

        $this->assertDatabaseHas('pos_inventories', [
            'id' => $item->id,
            'total_stock' => 8,
        ]);
    }

    public function test_update_stock_with_photo_saves_and_overwrites_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::factory()->create();
        $item = $this->makeErpItem($user);

        $erp = Mockery::mock(ErpConnectorService::class);
        $erp->shouldReceive('updateStock')->twice();
        $this->app->instance(ErpConnectorService::class, $erp);

        $photo1 = \Illuminate\Http\UploadedFile::fake()->image('shelf1.jpg', 800, 600);

        $res1 = $this->actingAs($user)->post("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 12,
            'note' => 'First count with photo',
            'source' => 'mobile_field',
            'photo' => $photo1,
        ]);

        $res1->assertOk()
            ->assertJsonPath('new_stock', 12)
            ->assertJsonPath('photo_url', "/erp/inventory/{$item->id}/photo");

        $item->refresh();
        $this->assertNotNull($item->photo_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($item->photo_path);
        $firstPath = $item->photo_path;

        // Fetch photo endpoint
        $photoRes = $this->actingAs($user)->getJson("/api/erp/inventory/{$item->id}/photo");
        $photoRes->assertOk();

        // Overwrite with second photo
        $photo2 = \Illuminate\Http\UploadedFile::fake()->image('shelf2.jpg', 1000, 800);

        $res2 = $this->actingAs($user)->post("/api/erp/inventory/{$item->id}/stock", [
            'stock' => 14,
            'note' => 'Second count replacing photo',
            'source' => 'mobile_field',
            'photo' => $photo2,
        ]);

        $res2->assertOk()
            ->assertJsonPath('new_stock', 14)
            ->assertJsonPath('photo_url', "/erp/inventory/{$item->id}/photo");

        $item->refresh();
        $this->assertNotSame($firstPath, $item->photo_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($firstPath);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($item->photo_path);
    }
}
