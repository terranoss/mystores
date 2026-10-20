<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\OrderService;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedWorkspaceWithMember(): array
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);
        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 99001,
            'shop_name' => 'Notify Shop',
            'platform' => 'shopee',
            'is_active' => true,
            'linked_at' => now(),
        ]);

        return compact('owner', 'member', 'workspace', 'store');
    }

    public function test_new_today_order_notifies_workspace_members(): void
    {
        ['owner' => $owner, 'member' => $member, 'store' => $store] = $this->seedWorkspaceWithMember();

        $service = app(OrderService::class);
        $order = $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-NEW-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 12.5,
            'create_time' => now()->timestamp,
            'item_list' => [
                ['item_id' => 1, 'model_id' => 2, 'item_name' => 'Widget', 'model_quantity_purchased' => 1, 'model_discounted_price' => 12.5],
            ],
        ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_sn' => 'SN-NEW-1']);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $member->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'order_new',
            $owner->fresh()->unreadNotifications()->first()->data['category']
        );

        // Re-sync same status → no duplicate new-order, no status change
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-NEW-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 12.5,
            'create_time' => now()->timestamp,
            'item_list' => [
                ['item_id' => 1, 'model_id' => 2, 'item_name' => 'Widget', 'model_quantity_purchased' => 1, 'model_discounted_price' => 12.5],
            ],
        ]);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_old_order_create_does_not_notify_as_new_today(): void
    {
        ['owner' => $owner, 'store' => $store] = $this->seedWorkspaceWithMember();

        app(OrderService::class)->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-OLD-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 9,
            'create_time' => now()->subDays(3)->timestamp,
            'item_list' => [
                ['item_id' => 9, 'item_name' => 'Old', 'model_quantity_purchased' => 1, 'model_discounted_price' => 9],
            ],
        ]);

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_status_change_notifies_once_per_new_status(): void
    {
        ['owner' => $owner, 'member' => $member, 'store' => $store] = $this->seedWorkspaceWithMember();

        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'SN-STAT-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 20,
            'ordered_at' => now()->subDay(),
        ]);

        $service = app(OrderService::class);
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-STAT-1',
            'order_status' => 'SHIPPED',
            'total_amount' => 20,
            'create_time' => now()->subDay()->timestamp,
            'item_list' => [],
        ]);

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $member->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'order_status',
            $owner->fresh()->unreadNotifications()->first()->data['category']
        );

        // Same status again → no extra notification
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-STAT-1',
            'order_status' => 'SHIPPED',
            'total_amount' => 20,
            'create_time' => now()->subDay()->timestamp,
            'item_list' => [],
        ]);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
    }

    public function test_status_change_notification_compares_canonical_status(): void
    {
        ['owner' => $owner, 'store' => $store] = $this->seedWorkspaceWithMember();

        Order::create([
            'store_id' => $store->id,
            'order_sn' => 'SN-CANON-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 20,
            'ordered_at' => now()->subDay(),
        ]);

        $service = app(OrderService::class);

        // A casing-only change is the same lifecycle state.
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-CANON-1',
            'order_status' => 'ready_to_ship',
            'total_amount' => 20,
            'create_time' => now()->subDay()->timestamp,
            'item_list' => [],
        ]);
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        // PROCESSED and READY_TO_SHIP are synonyms, so this is not a change either.
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-CANON-1',
            'order_status' => 'PROCESSED',
            'total_amount' => 20,
            'create_time' => now()->subDay()->timestamp,
            'item_list' => [],
        ]);
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        // A real lifecycle change still notifies once.
        $service->upsertOrderFromDetail($store, [
            'order_sn' => 'SN-CANON-1',
            'order_status' => 'SHIPPED',
            'total_amount' => 20,
            'create_time' => now()->subDay()->timestamp,
            'item_list' => [],
        ]);
        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'order_status',
            $owner->fresh()->unreadNotifications()->first()->data['category']
        );
    }

    public function test_picklist_comment_notifies_other_members_only(): void
    {
        ['owner' => $owner, 'member' => $member, 'workspace' => $workspace, 'store' => $store] = $this->seedWorkspaceWithMember();

        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'SN-CMT-1',
            'order_status' => 'READY_TO_SHIP',
            'total_amount' => 5,
            'ordered_at' => now(),
        ]);
        $order->items()->create([
            'item_id' => 11,
            'model_id' => 22,
            'item_name' => 'Chat Item',
            'quantity' => 1,
            'price' => 5,
        ]);

        $this->actingAs($member)->postJson('/api/dashboard/picklist-comments', [
            'kind' => 'today',
            'item_key' => '11_22',
            'item_id' => '11',
            'model_id' => '22',
            'item_name' => 'Chat Item',
            'body' => 'Please pick this first',
        ])->assertCreated();

        $this->assertSame(1, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $member->fresh()->unreadNotifications()->count());
        $n = $owner->fresh()->unreadNotifications()->first();
        $this->assertSame('picklist_comment', $n->data['category']);
        $this->assertStringContainsString('Please pick this first', $n->data['message']);
        $this->assertSame($workspace->id, $workspace->id);
    }
}
