<?php

namespace Tests\Feature;

use App\Models\ShopeeOAuthState;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LazadaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LazadaAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_auth_url_requires_authenticated_user(): void
    {
        $response = $this->getJson('/api/lazada/auth-url');
        $response->assertStatus(401);
    }

    public function test_auth_url_creates_oauth_state_with_lazada_platform(): void
    {
        $user = User::factory()->create();

        $lazadaMock = Mockery::mock(LazadaService::class);
        $lazadaMock->shouldReceive('getAuthUrl')
            ->once()
            ->withArgs(function ($state) {
                return is_string($state) && strlen($state) === 64;
            })
            ->andReturn('https://auth.lazada.com/oauth/authorize?test=1');

        $this->app->instance(LazadaService::class, $lazadaMock);

        $response = $this->actingAs($user)->getJson('/api/lazada/auth-url');

        $response->assertStatus(200);
        $response->assertJson(['url' => 'https://auth.lazada.com/oauth/authorize?test=1']);

        $this->assertDatabaseHas('shopee_oauth_states', [
            'user_id' => $user->id,
            'platform' => 'lazada',
            'store_id' => null,
            'consumed_at' => null,
        ]);
    }

    public function test_reauthorize_creates_state_with_lazada_platform_and_store_id(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 77711,
            'shop_name' => 'Lazada Reauth Test',
            'platform' => 'lazada',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $lazadaMock = Mockery::mock(LazadaService::class);
        $lazadaMock->shouldReceive('getAuthUrl')
            ->once()
            ->andReturn('https://auth.lazada.com/oauth/authorize?reauth=1');

        $this->app->instance(LazadaService::class, $lazadaMock);

        $response = $this->actingAs($user)->postJson("/api/lazada/stores/{$store->id}/reauthorize");

        $response->assertStatus(200);
        $response->assertJson(['url' => 'https://auth.lazada.com/oauth/authorize?reauth=1']);

        $this->assertDatabaseHas('shopee_oauth_states', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'platform' => 'lazada',
        ]);
    }

    public function test_reauthorize_rejects_non_lazada_store(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 11111,
            'shop_name' => 'Shopee Store',
            'platform' => 'shopee',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/lazada/stores/{$store->id}/reauthorize");

        $response->assertStatus(400);
        $response->assertJson(['message' => 'This store is not a Lazada store.']);
    }

    public function test_callback_creates_store_and_token_on_success(): void
    {
        $user = User::factory()->create();
        $rawState = str_repeat('a', 64);

        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(10),
        ]);

        $lazadaMock = Mockery::mock(LazadaService::class);
        $lazadaMock->shouldReceive('createToken')
            ->once()
            ->with('valid-code')
            ->andReturn([
                'access_token' => 'lz-access-123',
                'refresh_token' => 'lz-refresh-123',
                'expires_in' => 2592000,
                'refresh_expires_in' => 15552000,
                'country' => 'my',
                'seller_id' => '998877',
            ]);

        $lazadaMock->shouldReceive('resolveShopName')
            ->once()
            ->with('lz-access-123', 'my')
            ->andReturn('Official Lazada Shop');

        $this->app->instance(LazadaService::class, $lazadaMock);

        $response = $this->get("/api/lazada/callback?code=valid-code&state={$rawState}");

        $response->assertRedirect();
        $this->assertStringContainsString('linked=1', $response->headers->get('Location'));

        $this->assertDatabaseHas('shopee_stores', [
            'shop_id' => 998877,
            'platform' => 'lazada',
            'shop_name' => 'Official Lazada Shop',
            'region' => 'my',
            'is_active' => 1,
        ]);

        $store = ShopeeStore::where('shop_id', 998877)->first();
        $this->assertNotNull($store->token);
        $this->assertSame('lz-access-123', decrypt($store->token->access_token));
        $this->assertSame('lz-refresh-123', decrypt($store->token->refresh_token));

        $oauthState = ShopeeOAuthState::where('state_hash', hash('sha256', $rawState))->first();
        $this->assertNotNull($oauthState->consumed_at);
    }

    public function test_callback_rejects_state_from_different_platform(): void
    {
        $user = User::factory()->create();
        $rawState = str_repeat('b', 64);

        // Created for Shopee platform
        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->get("/api/lazada/callback?code=valid-code&state={$rawState}");

        $response->assertRedirect();
        $this->assertStringContainsString('error=oauth_failed', $response->headers->get('Location'));

        // State remains unconsumed
        $oauthState = ShopeeOAuthState::where('state_hash', hash('sha256', $rawState))->first();
        $this->assertNull($oauthState->consumed_at);
    }

    public function test_callback_rejects_mismatched_seller_id_during_reauthorization(): void
    {
        $user = User::factory()->create();
        $intendedStore = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 55555,
            'shop_name' => 'Intended Store',
            'platform' => 'lazada',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $rawState = str_repeat('c', 64);
        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'store_id' => $intendedStore->id,
            'platform' => 'lazada',
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(10),
        ]);

        $lazadaMock = Mockery::mock(LazadaService::class);
        $lazadaMock->shouldReceive('createToken')
            ->once()
            ->with('reauth-code')
            ->andReturn([
                'access_token' => 'mismatched-access',
                'refresh_token' => 'mismatched-refresh',
                'expires_in' => 2592000,
                'refresh_expires_in' => 15552000,
                'country' => 'my',
                'seller_id' => '99999', // Different from intended 55555
            ]);

        $lazadaMock->shouldReceive('resolveShopName')
            ->andReturn('Different Account Shop');

        $this->app->instance(LazadaService::class, $lazadaMock);

        $response = $this->get("/api/lazada/callback?code=reauth-code&state={$rawState}");

        $response->assertRedirect();
        $this->assertStringContainsString('error=reauthorize_shop_mismatch', $response->headers->get('Location'));

        // Target store token should not have been created or modified
        $this->assertNull($intendedStore->fresh()->token);
    }

    public function test_disconnect_and_unlink_reject_non_lazada_stores(): void
    {
        $user = User::factory()->create();
        $shopeeStore = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 12345,
            'shop_name' => 'Shopee Store',
            'platform' => 'shopee',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/lazada/stores/{$shopeeStore->id}/disconnect")
            ->assertStatus(400)
            ->assertJson(['message' => 'This store is not a Lazada store.']);

        $this->actingAs($user)
            ->deleteJson("/api/lazada/stores/{$shopeeStore->id}", [
                'confirmation' => 'DELETE STORE DATA',
            ])
            ->assertStatus(400)
            ->assertJson(['message' => 'This store is not a Lazada store.']);
    }

    public function test_disconnect_lazada_store_deactivates_and_deletes_token(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 66666,
            'shop_name' => 'Lazada Store To Disconnect',
            'platform' => 'lazada',
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(180),
            'auth_expires_at' => now()->addDays(180),
        ]);

        $response = $this->actingAs($user)->postJson("/api/lazada/stores/{$store->id}/disconnect");

        $response->assertStatus(200);
        $this->assertFalse($store->fresh()->is_active);
        $this->assertNull($store->fresh()->token);
    }
}
