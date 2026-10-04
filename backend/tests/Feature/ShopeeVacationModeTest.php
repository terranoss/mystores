<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ShopeeService;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShopeeVacationModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_listing_includes_vacation_mode(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10001,
            'shop_name' => 'Test Shop',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => true,
            'vacation_mode_synced_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/shopee/stores');

        $response->assertStatus(200);
        $stores = $response->json('stores');
        $this->assertCount(1, $stores);
        $this->assertTrue($stores[0]['vacation_mode']);
        $this->assertNotNull($stores[0]['vacation_mode_synced_at']);
    }

    public function test_owner_can_enable_vacation_mode(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10002,
            'shop_name' => 'Active Shop',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => false,
        ]);

        $shopeeMock = Mockery::mock(ShopeeService::class);
        $shopeeMock->shouldReceive('setHolidayMode')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id), true)
            ->andReturn(['holiday_mode_on' => true]);
        $this->app->instance(ShopeeService::class, $shopeeMock);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/vacation-mode", [
            'vacation_mode' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Vacation mode enabled successfully.',
            'vacation_mode' => true,
        ]);

        $store->refresh();
        $this->assertTrue($store->vacation_mode);
        $this->assertNotNull($store->vacation_mode_synced_at);
    }

    public function test_owner_can_disable_vacation_mode(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10003,
            'shop_name' => 'Active Shop',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => true,
        ]);

        $shopeeMock = Mockery::mock(ShopeeService::class);
        $shopeeMock->shouldReceive('setHolidayMode')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id), false)
            ->andReturn(['holiday_mode_on' => false]);
        $this->app->instance(ShopeeService::class, $shopeeMock);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/vacation-mode", [
            'vacation_mode' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Vacation mode disabled successfully.',
            'vacation_mode' => false,
        ]);

        $store->refresh();
        $this->assertFalse($store->vacation_mode);
    }

    public function test_non_owner_cannot_toggle_vacation_mode(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Team Workspace',
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

        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'shop_id' => 10004,
            'shop_name' => 'Owner Store',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => false,
        ]);

        $response = $this->actingAs($member)->postJson("/api/shopee/stores/{$store->id}/vacation-mode", [
            'vacation_mode' => true,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Only the workspace owner can manage store vacation mode.']);
    }

    public function test_non_shopee_store_rejected_for_vacation_mode(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => 10005,
            'shop_name' => 'Lazada Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/vacation-mode", [
            'vacation_mode' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Vacation mode is only supported for Shopee stores.']);
    }

    public function test_shopee_api_error_returns_422(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10006,
            'shop_name' => 'Error Shop',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => false,
        ]);

        $shopeeMock = Mockery::mock(ShopeeService::class);
        $shopeeMock->shouldReceive('setHolidayMode')
            ->once()
            ->andThrow(new ShopeeException('Holiday mode cannot be enabled due to ongoing campaign.'));
        $this->app->instance(ShopeeService::class, $shopeeMock);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/vacation-mode", [
            'vacation_mode' => true,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('ongoing campaign', $response->json('message'));
        $store->refresh();
        $this->assertFalse($store->vacation_mode);
    }

    public function test_owner_can_get_vacation_mode_status(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10007,
            'shop_name' => 'Query Shop',
            'region' => 'MY',
            'is_active' => true,
            'vacation_mode' => false,
        ]);

        $shopeeMock = Mockery::mock(ShopeeService::class);
        $shopeeMock->shouldReceive('getHolidayMode')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $store->id))
            ->andReturn(['holiday_mode_on' => true, 'holiday_mode_type' => 0]);
        $this->app->instance(ShopeeService::class, $shopeeMock);

        $response = $this->actingAs($user)->getJson("/api/shopee/stores/{$store->id}/vacation-mode");

        $response->assertStatus(200);
        $response->assertJson([
            'vacation_mode' => true,
        ]);

        $store->refresh();
        $this->assertTrue($store->vacation_mode);
    }
}
