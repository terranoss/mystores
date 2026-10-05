<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\WorkspaceActivityLog;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_is_logged_and_visibility_is_role_scoped(): void
    {
        $owner = User::factory()->create(['email' => 'owner-act@example.com']);
        $memberA = User::factory()->create(['email' => 'member-a@example.com']);
        $memberB = User::factory()->create(['email' => 'member-b@example.com']);

        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 88001,
            'shop_name' => 'Act Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $memberA->id,
            'role' => 'member',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $memberB->id,
            'role' => 'member',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 501,
            'item_name' => 'Logged Plant',
            'item_sku' => 'LOG-1',
            'price' => 10,
            'stock' => 3,
            'synced_at' => now(),
        ]);

        $logger = app(WorkspaceActivityLogger::class);
        $logger->log($memberA, 'product.update_stock', 'Updated stock for Logged Plant', $product, ['item_sku' => 'LOG-1']);
        $logger->log($memberB, 'order.cancel', 'Cancelled order ABC', null, ['order_sn' => 'ABC'], $workspace);

        $this->assertDatabaseCount('workspace_activity_logs', 2);

        $ownerView = $this->actingAs($owner)->getJson('/api/workspace/activity');
        $ownerView->assertOk();
        $ownerView->assertJsonPath('total', 2);

        $memberAView = $this->actingAs($memberA)->getJson('/api/workspace/activity');
        $memberAView->assertOk();
        $memberAView->assertJsonPath('total', 1);
        $memberAView->assertJsonPath('data.0.action', 'product.update_stock');
        $memberAView->assertJsonPath('data.0.user.email', 'member-a@example.com');

        $memberBView = $this->actingAs($memberB)->getJson('/api/workspace/activity');
        $memberBView->assertOk();
        $memberBView->assertJsonPath('total', 1);
        $memberBView->assertJsonPath('data.0.action', 'order.cancel');
    }

    public function test_activity_can_filter_by_action(): void
    {
        $owner = User::factory()->create(['email' => 'filter-act@example.com']);
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        $logger = app(WorkspaceActivityLogger::class);

        $logger->log($owner, 'erp.update_stock', 'Stock A', null, ['sku' => 'A'], $workspace);
        $logger->log($owner, 'erp.update_stock', 'Stock B', null, ['sku' => 'B'], $workspace);
        $logger->log($owner, 'team.invite', 'Invite X', null, ['email' => 'x@example.com'], $workspace);

        $filtered = $this->actingAs($owner)->getJson('/api/workspace/activity?action=erp.update_stock');
        $filtered->assertOk();
        $filtered->assertJsonPath('total', 2);
        $filtered->assertJsonPath('data.0.action', 'erp.update_stock');
        $filtered->assertJsonPath('data.1.action', 'erp.update_stock');

        $all = $this->actingAs($owner)->getJson('/api/workspace/activity');
        $all->assertOk();
        $all->assertJsonPath('total', 3);
    }



    public function test_invite_writes_activity_log(): void
    {
        $owner = User::factory()->create(['email' => 'boss-act@example.com']);
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);

        $res = $this->actingAs($owner)->postJson('/api/workspace/invites', [
            'email' => 'newhire@example.com',
        ]);
        $res->assertCreated();

        $this->assertDatabaseHas('workspace_activity_logs', [
            'action' => 'team.invite',
            'user_id' => $owner->id,
        ]);
        $this->assertTrue(
            WorkspaceActivityLog::query()->where('summary', 'like', '%newhire@example.com%')->exists()
        );
    }
}
