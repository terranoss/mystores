<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCredential;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceCredentialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, member: User, workspace: Workspace}
     */
    private function seedTeam(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Credential Workspace',
            'owner_user_id' => $owner->id,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        return compact('owner', 'member', 'workspace');
    }

    public function test_owner_can_view_masked_credentials(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->seedTeam();
        WorkspaceCredential::create([
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'partner_id' => '12345',
            'partner_key' => 'super-secret-key',
            'push_partner_key' => 'push-secret',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/workspace/credentials');

        $response->assertOk();
        $response->assertJson([
            'partner_id' => '12345',
            'partner_key' => '[SET]',
            'push_partner_key' => '[SET]',
        ]);
        $this->assertArrayHasKey('shopee_redirect_url', $response->json());
        $this->assertArrayHasKey('lazada_redirect_url', $response->json());
        $this->assertStringNotContainsString('super-secret-key', $response->getContent());
    }

    public function test_non_owner_cannot_view_credentials(): void
    {
        ['member' => $member, 'workspace' => $workspace] = $this->seedTeam();
        WorkspaceCredential::create([
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'partner_id' => '12345',
            'partner_key' => 'super-secret-key',
            'is_active' => true,
        ]);

        $response = $this->actingAs($member)->getJson('/api/workspace/credentials');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Only the workspace owner can view credentials.']);
        $this->assertStringNotContainsString('super-secret-key', $response->getContent());
    }

    public function test_owner_can_store_and_clear_secret_values(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->seedTeam();

        $this->actingAs($owner)->putJson('/api/workspace/credentials', [
            'partner_id' => '999',
            'partner_key' => 'first-secret',
            'shopee_redirect_url' => 'https://custom.shopee.test/callback',
            'app_key' => 'lazada-app',
            'app_secret' => 'lazada-secret',
            'lazada_redirect_url' => 'https://custom.lazada.test/callback',
        ])->assertOk();

        $shopee = WorkspaceCredential::where('workspace_id', $workspace->id)->where('platform', 'shopee')->firstOrFail();
        $lazada = WorkspaceCredential::where('workspace_id', $workspace->id)->where('platform', 'lazada')->firstOrFail();
        $this->assertSame('first-secret', $shopee->partner_key);
        $this->assertSame('https://custom.shopee.test/callback', $shopee->redirect_url);
        $this->assertSame('lazada-secret', $lazada->app_secret);
        $this->assertSame('https://custom.lazada.test/callback', $lazada->redirect_url);

        // Explicit empty string clears the stored secret.
        $this->actingAs($owner)->putJson('/api/workspace/credentials', [
            'partner_key' => '',
            'app_secret' => '',
        ])->assertOk();

        $this->assertNull($shopee->fresh()->partner_key);
        $this->assertNull($lazada->fresh()->app_secret);

        // Explicit null clears it too.
        $this->actingAs($owner)->putJson('/api/workspace/credentials', [
            'partner_key' => 'second-secret',
        ])->assertOk();
        $this->actingAs($owner)->putJson('/api/workspace/credentials', [
            'partner_key' => null,
        ])->assertOk();
        $this->assertNull($shopee->fresh()->partner_key);
    }

    public function test_masked_placeholder_does_not_overwrite_stored_secret(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->seedTeam();
        WorkspaceCredential::create([
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'partner_id' => '12345',
            'partner_key' => 'keep-me',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->putJson('/api/workspace/credentials', [
            'partner_id' => '12345',
            'partner_key' => '[SET]',
        ])->assertOk();

        $this->assertSame(
            'keep-me',
            WorkspaceCredential::where('workspace_id', $workspace->id)->where('platform', 'shopee')->firstOrFail()->partner_key
        );
    }

    public function test_owner_can_delete_all_credentials(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->seedTeam();
        WorkspaceCredential::create([
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'partner_id' => '1',
            'partner_key' => 'k',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->deleteJson('/api/workspace/credentials')->assertOk();

        $this->assertSame(0, WorkspaceCredential::where('workspace_id', $workspace->id)->count());
    }

    public function test_credentials_resolve_to_requested_workspace_scoped_by_membership(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->seedTeam();
        WorkspaceCredential::create([
            'workspace_id' => $workspace->id,
            'platform' => 'shopee',
            'partner_id' => 'scoped-id',
            'partner_key' => 'scoped-secret',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->getJson('/api/workspace/credentials?workspace_id='.$workspace->id);

        $response->assertOk();
        $response->assertJson(['partner_id' => 'scoped-id']);
    }

    public function test_requesting_another_tenants_workspace_returns_404(): void
    {
        ['owner' => $owner] = $this->seedTeam();

        $foreign = Workspace::create([
            'name' => 'Foreign Workspace',
            'owner_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($owner)->getJson('/api/workspace/credentials?workspace_id='.$foreign->id)
            ->assertStatus(404);
    }

    public function test_personal_workspace_owner_can_manage_credentials(): void
    {
        $user = User::factory()->create();
        $workspace = app(WorkspaceAccess::class)->ensurePersonalWorkspace($user);

        $this->actingAs($user)->getJson('/api/workspace/credentials')->assertOk();

        $this->actingAs($user)->putJson('/api/workspace/credentials', [
            'partner_id' => 'personal',
            'partner_key' => 'personal-secret',
        ])->assertOk();

        $this->assertSame(
            'personal-secret',
            WorkspaceCredential::where('workspace_id', $workspace->id)->where('platform', 'shopee')->firstOrFail()->partner_key
        );
    }
}
