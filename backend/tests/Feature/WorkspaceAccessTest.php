<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creation_bootstraps_workspace_for_user(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 900001,
            'shop_name' => 'Boot Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $this->assertNotNull($store->workspace_id);
        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $user->id,
            'workspace_id' => $store->workspace_id,
            'role' => 'owner',
        ]);
    }

    public function test_member_can_list_owner_stores_but_cannot_disconnect(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);

        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 900002,
            'shop_name' => 'Shared Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $list = $this->actingAs($member)->getJson('/api/shopee/stores');
        $list->assertOk();
        $list->assertJsonPath('stores.0.id', $store->id);

        $disconnect = $this->actingAs($member)->postJson("/api/shopee/stores/{$store->id}/disconnect");
        $disconnect->assertForbidden();
    }

    public function test_owner_can_invite_and_member_can_accept(): void
    {
        $owner = User::factory()->create(['email' => 'boss@example.com']);
        $invitee = User::factory()->create(['email' => 'staff@example.com']);
        app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);

        $inviteRes = $this->actingAs($owner)->postJson('/api/workspace/invites', [
            'email' => 'staff@example.com',
        ]);
        $inviteRes->assertCreated();
        $token = $inviteRes->json('invite.token');
        $this->assertNotEmpty($token);

        $accept = $this->actingAs($invitee)->postJson('/api/workspace/invites/accept', [
            'token' => $token,
        ]);
        $accept->assertOk();
        $accept->assertJsonPath('membership.role', 'member');

        $this->assertDatabaseHas('workspace_members', [
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        $membership = WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $res = $this->actingAs($owner)->deleteJson("/api/workspace/members/{$membership->id}");
        $res->assertOk();
        $this->assertDatabaseMissing('workspace_members', ['id' => $membership->id]);
    }

    public function test_member_sees_workspace_products(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($owner);
        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 900003,
            'shop_name' => 'Product Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        \App\Models\Product::create([
            'store_id' => $store->id,
            'item_id' => 111,
            'item_name' => 'Shared Plant',
            'price' => 10,
            'stock' => 5,
            'synced_at' => now(),
        ]);

        $res = $this->actingAs($member)->getJson('/api/products/grouped');
        $res->assertOk();
        $res->assertJsonPath('total', 1);
    }
}
