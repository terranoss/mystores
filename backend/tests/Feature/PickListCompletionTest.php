<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PickListCompletion;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PickListCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function seedTodayOrder(User $user): array
    {
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 88001,
            'shop_name' => 'Pick Photo Store',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-PHOTO-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 40.00,
            'ordered_at' => now(),
        ]);
        $order->items()->create([
            'item_id' => 101,
            'model_id' => 201,
            'item_name' => 'Ergonomic Mouse',
            'quantity' => 2,
            'price' => 20.00,
        ]);

        $itemKey = PickListCompletion::makeItemKey(101, 201, 'Ergonomic Mouse');

        return compact('workspace', 'store', 'order', 'itemKey');
    }

    public function test_today_picklist_includes_item_key_and_null_completion(): void
    {
        $user = User::factory()->create();
        $this->seedTodayOrder($user);

        $response = $this->actingAs($user)->getJson('/api/dashboard/today-picklist');

        $response->assertOk();
        $items = $response->json('aggregated_items');
        $this->assertCount(1, $items);
        $this->assertSame('101_201', $items[0]['item_key']);
        $this->assertNull($items[0]['completion']);
    }

    public function test_complete_without_photo_returns_422(): void
    {
        $user = User::factory()->create();
        [, , , $itemKey] = array_values($this->seedTodayOrder($user));

        $response = $this->actingAs($user)->postJson('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
        ]);

        $response->assertStatus(422);
    }

    public function test_complete_with_photo_then_picklist_shows_completion(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['name' => 'Ali Picker']);
        ['itemKey' => $itemKey] = $this->seedTodayOrder($user);

        $photo = UploadedFile::fake()->image('pick.jpg', 640, 480);

        $post = $this->actingAs($user)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_id' => '101',
            'model_id' => '201',
            'item_name' => 'Ergonomic Mouse',
            'photo' => $photo,
        ]);

        $post->assertCreated()
            ->assertJsonPath('picked_by.name', 'Ali Picker')
            ->assertJsonStructure(['id', 'picked_at', 'photo_url']);

        $completionId = $post->json('id');
        $this->assertDatabaseHas('pick_list_completions', [
            'item_key' => $itemKey,
            'kind' => 'today',
            'picked_by' => $user->id,
        ]);

        $list = $this->actingAs($user)->getJson('/api/dashboard/today-picklist');
        $list->assertOk()
            ->assertJsonPath('aggregated_items.0.completion.id', $completionId)
            ->assertJsonPath('aggregated_items.0.completion.picked_by.name', 'Ali Picker')
            ->assertJsonPath(
                'aggregated_items.0.completion.photo_url',
                '/dashboard/picklist-photos/'.$completionId
            );

        $photoRes = $this->actingAs($user)->get('/api/dashboard/picklist-photos/'.$completionId);
        $photoRes->assertOk();
    }

    public function test_workspace_member_sees_shared_completion(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertCreated();

        $list = $this->actingAs($member)->getJson('/api/dashboard/today-picklist');
        $list->assertOk()
            ->assertJsonPath('aggregated_items.0.completion.picked_by.name', 'Owner');
    }

    public function test_any_member_can_unpick_and_photo_becomes_404(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->create();
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $created = $this->actingAs($owner)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $created->assertCreated();
        $completionId = $created->json('id');

        $delete = $this->actingAs($member)->deleteJson('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
        ]);
        $delete->assertOk();

        $this->assertDatabaseMissing('pick_list_completions', ['id' => $completionId]);

        $photoRes = $this->actingAs($owner)->get('/api/dashboard/picklist-photos/'.$completionId);
        $photoRes->assertNotFound();

        $list = $this->actingAs($owner)->getJson('/api/dashboard/today-picklist');
        $list->assertJsonPath('aggregated_items.0.completion', null);
    }

    public function test_outsider_cannot_access_photo(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        ['itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        $created = $this->actingAs($owner)->post('/api/dashboard/picklist-completions', [
            'kind' => 'pending',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $created->assertCreated();
        $completionId = $created->json('id');

        $photoRes = $this->actingAs($outsider)->get('/api/dashboard/picklist-photos/'.$completionId);
        $photoRes->assertForbidden();
    }

    public function test_replace_photo_updates_picker(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $first = $this->actingAs($owner)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $first->assertCreated();
        $id = $first->json('id');

        $second = $this->actingAs($member)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('b.jpg'),
        ]);
        $second->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('picked_by.name', 'Member');
    }

    public function test_photo_deleted_when_last_open_order_completed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        ['order' => $order, 'itemKey' => $itemKey] = $this->seedTodayOrder($user);

        $created = $this->actingAs($user)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_id' => '101',
            'model_id' => '201',
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('pick.jpg'),
        ]);
        $created->assertCreated();
        $completionId = (int) $created->json('id');
        $path = PickListCompletion::find($completionId)?->photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $order->update(['order_status' => 'COMPLETED']);
        $deleted = app(\App\Services\PickListPhotoCleanupService::class)
            ->cleanupForOrder($order->fresh()->load(['items', 'store']));

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('pick_list_completions', ['id' => $completionId]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_photo_kept_while_another_open_order_needs_item(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        ['workspace' => $workspace, 'store' => $store, 'order' => $order, 'itemKey' => $itemKey] = $this->seedTodayOrder($user);

        $other = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-PHOTO-2',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 20.00,
            'ordered_at' => now(),
        ]);
        $other->items()->create([
            'item_id' => 101,
            'model_id' => 201,
            'item_name' => 'Ergonomic Mouse',
            'quantity' => 1,
            'price' => 20.00,
        ]);

        $created = $this->actingAs($user)->post('/api/dashboard/picklist-completions', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Ergonomic Mouse',
            'photo' => UploadedFile::fake()->image('pick.jpg'),
        ]);
        $created->assertCreated();
        $completionId = (int) $created->json('id');

        $order->update(['order_status' => 'COMPLETED']);
        $deleted = app(\App\Services\PickListPhotoCleanupService::class)
            ->cleanupForOrder($order->fresh()->load(['items', 'store']));

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('pick_list_completions', ['id' => $completionId]);
        $this->assertNotNull($workspace->id);
    }
}
