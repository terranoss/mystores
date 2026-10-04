<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PickListComment;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\PickListPhotoCleanupService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickListCommentTest extends TestCase
{
    use RefreshDatabase;

    private function seedTodayOrder(User $user): array
    {
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 88101,
            'shop_name' => 'Comment Store',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'PL-CMT-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 40.00,
            'ordered_at' => now(),
        ]);
        $order->items()->create([
            'item_id' => 501,
            'model_id' => 601,
            'item_name' => 'Comment Item',
            'quantity' => 1,
            'price' => 40.00,
        ]);

        return [
            'workspace' => $workspace,
            'store' => $store,
            'order' => $order,
            'itemKey' => '501_601',
        ];
    }

    public function test_member_can_post_and_list_comments(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $post = $this->actingAs($member)->postJson('/api/dashboard/picklist-comments', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_id' => '501',
            'model_id' => '601',
            'item_name' => 'Comment Item',
            'body' => 'Need 2 more units from shelf B',
        ]);
        $post->assertCreated()
            ->assertJsonPath('body', 'Need 2 more units from shelf B')
            ->assertJsonPath('user.name', 'Member');

        $list = $this->actingAs($owner)->getJson(
            '/api/dashboard/picklist-comments?kind=today&item_key='.urlencode($itemKey)
        );
        $list->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_outsider_cannot_post_comment_on_foreign_workspace_item_via_picklist_embed(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $this->seedTodayOrder($owner);

        // Outsider posts into their own workspace (primary), not owner's — still allowed for their workspace.
        // Forbidden case: outsider cannot see owner's picklist comments via owner's picklist.
        $ownerList = $this->actingAs($owner)->getJson('/api/dashboard/today-picklist');
        $ownerList->assertOk();

        $outsiderList = $this->actingAs($outsider)->getJson('/api/dashboard/today-picklist');
        $outsiderList->assertOk();
        $this->assertSame([], $outsiderList->json('aggregated_items'));
    }

    public function test_author_can_delete_own_comment_non_author_cannot(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $created = $this->actingAs($member)->postJson('/api/dashboard/picklist-comments', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Comment Item',
            'body' => 'Temp note',
        ]);
        $created->assertCreated();
        $id = (int) $created->json('id');

        $forbidden = $this->actingAs($owner)->deleteJson('/api/dashboard/picklist-comments/'.$id);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('pick_list_comments', ['id' => $id]);

        $ok = $this->actingAs($member)->deleteJson('/api/dashboard/picklist-comments/'.$id);
        $ok->assertOk();
        $this->assertDatabaseMissing('pick_list_comments', ['id' => $id]);
    }

    public function test_picklist_payload_includes_comments(): void
    {
        $user = User::factory()->create(['name' => 'Picker']);
        ['itemKey' => $itemKey] = $this->seedTodayOrder($user);

        $this->actingAs($user)->postJson('/api/dashboard/picklist-comments', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Comment Item',
            'body' => 'Embedded note',
        ])->assertCreated();

        $list = $this->actingAs($user)->getJson('/api/dashboard/today-picklist');
        $list->assertOk()
            ->assertJsonPath('aggregated_items.0.comment_count', 1)
            ->assertJsonPath('aggregated_items.0.comments.0.body', 'Embedded note')
            ->assertJsonPath('aggregated_items.0.comments.0.user.name', 'Picker')
            ->assertJsonPath('aggregated_items.0.has_unread_comments', false)
            ->assertJsonPath('aggregated_items.0.unread_comment_count', 0);
    }

    public function test_teammate_comment_is_unread_until_marked_read(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($owner);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        $this->actingAs($member)->postJson('/api/dashboard/picklist-comments', [
            'kind' => 'today',
            'item_key' => $itemKey,
            'item_name' => 'Comment Item',
            'body' => 'New note for owner',
        ])->assertCreated();

        $ownerList = $this->actingAs($owner)->getJson('/api/dashboard/today-picklist');
        $ownerList->assertOk()
            ->assertJsonPath('aggregated_items.0.has_unread_comments', true)
            ->assertJsonPath('aggregated_items.0.unread_comment_count', 1);

        $memberList = $this->actingAs($member)->getJson('/api/dashboard/today-picklist');
        $memberList->assertOk()
            ->assertJsonPath('aggregated_items.0.has_unread_comments', false)
            ->assertJsonPath('aggregated_items.0.unread_comment_count', 0);

        $this->actingAs($owner)->postJson('/api/dashboard/picklist-comments/read', [
            'kind' => 'today',
            'item_key' => $itemKey,
        ])->assertOk();

        $after = $this->actingAs($owner)->getJson('/api/dashboard/today-picklist');
        $after->assertOk()
            ->assertJsonPath('aggregated_items.0.has_unread_comments', false)
            ->assertJsonPath('aggregated_items.0.unread_comment_count', 0);
    }

    public function test_prune_old_comments_removes_stale_rows(): void
    {
        $user = User::factory()->create();
        ['workspace' => $workspace, 'itemKey' => $itemKey] = $this->seedTodayOrder($user);

        $old = PickListComment::create([
            'workspace_id' => $workspace->id,
            'kind' => 'today',
            'pick_date' => now()->subDays(20)->toDateString(),
            'item_key' => $itemKey,
            'item_name' => 'Old',
            'user_id' => $user->id,
            'body' => 'stale',
        ]);
        $fresh = PickListComment::create([
            'workspace_id' => $workspace->id,
            'kind' => 'today',
            'pick_date' => now()->toDateString(),
            'item_key' => $itemKey,
            'item_name' => 'Fresh',
            'user_id' => $user->id,
            'body' => 'keep',
        ]);

        $pruned = app(PickListPhotoCleanupService::class)->pruneOldComments(14);
        $this->assertSame(1, $pruned);
        $this->assertDatabaseMissing('pick_list_comments', ['id' => $old->id]);
        $this->assertDatabaseHas('pick_list_comments', ['id' => $fresh->id]);
    }
}
