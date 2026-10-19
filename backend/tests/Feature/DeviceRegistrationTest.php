<?php

namespace Tests\Feature;

use App\Models\DeviceRegistration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_requires_authentication(): void
    {
        $response = $this->postJson('/api/devices', [
            'player_id' => 'player-12345',
        ]);

        $response->assertStatus(401);
    }

    public function test_device_identifier_is_required_and_length_bounded(): void
    {
        $user = User::factory()->create();

        // Missing player_id
        $this->actingAs($user)
            ->postJson('/api/devices', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);

        // Oversized player_id (> 255 chars)
        $this->actingAs($user)
            ->postJson('/api/devices', [
                'player_id' => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['player_id']);
    }

    public function test_registering_returns_callers_workspace_public_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/devices', [
            'player_id' => 'player-uuid-1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['workspace_public_id']);

        $publicId = $response->json('workspace_public_id');
        $this->assertNotEmpty($publicId);

        $this->assertDatabaseHas('device_registrations', [
            'player_id' => 'player-uuid-1',
            'user_id' => $user->id,
        ]);

        $device = DeviceRegistration::where('player_id', 'player-uuid-1')->first();
        $this->assertNotNull($device);
        $this->assertEquals($publicId, $device->workspace->public_id);
    }

    public function test_registering_the_same_device_identifier_twice_upserts_and_does_not_duplicate(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 registers device
        $res1 = $this->actingAs($user1)->postJson('/api/devices', [
            'player_id' => 'shared-player-id',
        ]);
        $res1->assertStatus(200);

        $this->assertDatabaseCount('device_registrations', 1);
        $device = DeviceRegistration::where('player_id', 'shared-player-id')->first();
        $this->assertEquals($user1->id, $device->user_id);

        // User 2 registers same device
        $res2 = $this->actingAs($user2)->postJson('/api/devices', [
            'player_id' => 'shared-player-id',
        ]);
        $res2->assertStatus(200);

        // Still only 1 record, moved to user2
        $this->assertDatabaseCount('device_registrations', 1);
        $device->refresh();
        $this->assertEquals($user2->id, $device->user_id);
    }

    public function test_user_belonging_to_multiple_workspaces_is_bound_to_own_primary(): void
    {
        $owner = User::factory()->create();
        $invited = User::factory()->create();

        // Boot personal workspace for owner
        $ownerWorkspace = Workspace::create([
            'name' => 'Owner Workspace',
            'owner_user_id' => $owner->id,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $ownerWorkspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        // Boot personal workspace for invited user
        $invitedWorkspace = Workspace::create([
            'name' => 'Invited User Workspace',
            'owner_user_id' => $invited->id,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $invitedWorkspace->id,
            'user_id' => $invited->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        // Add invited user to owner's workspace as member
        WorkspaceMember::create([
            'workspace_id' => $ownerWorkspace->id,
            'user_id' => $invited->id,
            'role' => WorkspaceMember::ROLE_MEMBER,
        ]);

        // When invited user registers device, it must bind to their own workspace
        $res = $this->actingAs($invited)->postJson('/api/devices', [
            'player_id' => 'player-invited-1',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['workspace_public_id' => $invitedWorkspace->public_id]);

        $device = DeviceRegistration::where('player_id', 'player-invited-1')->first();
        $this->assertEquals($invitedWorkspace->id, $device->workspace_id);
    }

    public function test_every_workspace_has_a_unique_public_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $w1 = Workspace::create(['name' => 'W1', 'owner_user_id' => $user1->id]);
        $w2 = Workspace::create(['name' => 'W2', 'owner_user_id' => $user2->id]);

        $this->assertNotNull($w1->public_id);
        $this->assertNotNull($w2->public_id);
        $this->assertNotEquals($w1->public_id, $w2->public_id);
    }

    public function test_unregistering_requires_authentication(): void
    {
        $this->deleteJson('/api/devices/player-123')
            ->assertStatus(401);
    }

    public function test_user_cannot_unregister_another_users_device(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1)->postJson('/api/devices', [
            'player_id' => 'user1-device',
        ])->assertStatus(200);

        $this->assertDatabaseHas('device_registrations', ['player_id' => 'user1-device']);

        // User 2 attempts to unregister User 1's device
        $res = $this->actingAs($user2)->deleteJson('/api/devices/user1-device');
        $res->assertStatus(200);

        // Device must still exist in DB
        $this->assertDatabaseHas('device_registrations', ['player_id' => 'user1-device']);

        // User 1 unregisters own device
        $resSelf = $this->actingAs($user1)->deleteJson('/api/devices/user1-device');
        $resSelf->assertStatus(200);
        $this->assertDatabaseMissing('device_registrations', ['player_id' => 'user1-device']);
    }

    public function test_deleting_a_workspace_removes_device_records(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/devices', [
            'player_id' => 'cascade-device',
        ])->assertStatus(200);

        $device = DeviceRegistration::where('player_id', 'cascade-device')->first();
        $this->assertNotNull($device);

        $device->workspace->delete();

        $this->assertDatabaseMissing('device_registrations', ['player_id' => 'cascade-device']);
    }
}
