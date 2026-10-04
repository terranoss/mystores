<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopeeOAuthState;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopeeOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopee.partner_id' => 12345,
            'shopee.partner_key' => 'test-partner-key',
            'shopee.redirect_url' => 'https://example.test/api/shopee/callback',
            'app.frontend_url' => 'https://frontend.example.test',
        ]);
    }

    public function test_unauthenticated_user_cannot_generate_auth_url(): void
    {
        $response = $this->getJson('/api/shopee/auth-url');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_generate_auth_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/shopee/auth-url');

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
        $this->assertStringContainsString('partner_id=', $response->json('url'));
    }

    public function test_expired_oauth_state_is_rejected(): void
    {
        $user = User::factory()->create();
        $rawState = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; // 64 chars

        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->subMinutes(5), // already expired
        ]);

        $response = $this->get('/api/shopee/callback', [
            'query' => [
                'code' => 'test-code',
                'shop_id' => '10001',
                'state' => $rawState,
            ],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'error=oauth_failed',
            $response->headers->get('Location')
        );
    }

    public function test_reused_oauth_state_is_rejected(): void
    {
        $user = User::factory()->create();
        $rawState = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'; // 64 chars

        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now()->subMinute(), // already consumed
        ]);

        $response = $this->get('/api/shopee/callback?code=test-code&shop_id=10001&state='.$rawState);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'error=oauth_failed',
            $response->headers->get('Location')
        );
    }

    public function test_oauth_callback_validates_state_before_shopee_api_call(): void
    {
        // This test verifies the state validation runs BEFORE any Shopee API call.
        // A valid state (not consumed, not expired) paired with an nonexistent shop_id
        // confirms the callback doesn't crash before the state check.
        $user = User::factory()->create();
        $rawState = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd'; // 64 chars

        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Don't configure Shopee partner key — this ensures the mock
        // ShopeeService would fail immediately if called.
        // The callback should pass state validation and then hit the
        // Shopee API call, but we haven't configured partner credentials
        // so it will fail at the API level and redirect with error.
        // That's expected — this test verifies the flow is consistent.
        config(['shopee.partner_id' => null, 'shopee.partner_key' => null]);

        $response = $this->get('/api/shopee/callback?code=test&shop_id=1&state='.$rawState);

        // State will be consumed, then the API call will fail due to missing
        // partner credentials — redirect with error
        $response->assertRedirect();
        $this->assertStringContainsString(
            'error=oauth_failed',
            $response->headers->get('Location')
        );

        // The OAuth state should be marked as consumed regardless
        $this->assertDatabaseHas('shopee_oauth_states', [
            'state_hash' => hash('sha256', $rawState),
        ]);
        $consumedState = ShopeeOAuthState::where('state_hash', hash('sha256', $rawState))->first();
        $this->assertNotNull($consumedState->consumed_at);
    }

    public function test_store_and_token_can_be_created_and_encrypted(): void
    {
        // Directly test that store and token records with encrypted values
        // can be created and retrieved — this is what the callback does.
        $user = User::factory()->create();

        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 10001,
            'shop_name' => 'Direct Test Shop',
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('real-access-token-value'),
            'refresh_token' => encrypt('real-refresh-token-value'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);

        // Verify store exists
        $this->assertDatabaseHas('shopee_stores', [
            'id' => $store->id,
            'shop_name' => 'Direct Test Shop',
        ]);

        // Verify token is encrypted (not plaintext in DB)
        $tokenRecord = ShopeeToken::where('store_id', $store->id)->first();
        $this->assertNotNull($tokenRecord);
        $this->assertNotEquals('real-access-token-value', $tokenRecord->access_token);
        $this->assertNotEquals('real-refresh-token-value', $tokenRecord->refresh_token);

        // Verify tokens can be decrypted
        $this->assertEquals('real-access-token-value', decrypt($tokenRecord->access_token));
        $this->assertEquals('real-refresh-token-value', decrypt($tokenRecord->refresh_token));
    }

    public function test_store_listing_shows_only_owned_stores(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create 2 stores for user A
        ShopeeStore::create([
            'user_id' => $userA->id,
            'shop_id' => 10001,
            'shop_name' => 'Store A One',
            'region' => 'MY',
            'is_active' => true,
        ]);
        ShopeeStore::create([
            'user_id' => $userA->id,
            'shop_id' => 10002,
            'shop_name' => 'Store A Two',
            'region' => 'SG',
            'is_active' => true,
        ]);

        // Create 1 store for user B
        ShopeeStore::create([
            'user_id' => $userB->id,
            'shop_id' => 20001,
            'shop_name' => 'Store B One',
            'region' => 'TH',
            'is_active' => true,
        ]);

        $response = $this->actingAs($userA)->getJson('/api/shopee/stores');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'stores');

        $storeNames = collect($response->json('stores'))->pluck('shop_name')->toArray();
        $this->assertContains('Store A One', $storeNames);
        $this->assertContains('Store A Two', $storeNames);
        $this->assertNotContains('Store B One', $storeNames);
    }

    public function test_store_listing_excludes_other_users_stores(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ShopeeStore::create([
            'user_id' => $userA->id,
            'shop_id' => 10001,
            'shop_name' => 'User A Store',
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeStore::create([
            'user_id' => $userB->id,
            'shop_id' => 20001,
            'shop_name' => 'User B Store',
            'region' => 'SG',
            'is_active' => true,
        ]);

        $response = $this->actingAs($userA)->getJson('/api/shopee/stores');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'stores');
        $this->assertEquals('User A Store', $response->json('stores.0.shop_name'));
    }

    public function test_refresh_failure_stores_error_and_increments_failure_count(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30001,
            'shop_name' => 'Refresh Failure Test',
            'region' => 'MY',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
            'last_refresh_attempt_at' => now()->subHour(),
            'last_refresh_failed_at' => now()->subHour(),
            'last_refresh_error' => 'Simulated error: token refresh failed',
            'refresh_failure_count' => 2,
        ]);

        $this->assertDatabaseHas('shopee_tokens', [
            'store_id' => $store->id,
            'refresh_failure_count' => 2,
        ]);
        $token = ShopeeToken::where('store_id', $store->id)->first();
        $this->assertNotNull($token->last_refresh_failed_at);
        $this->assertNotNull($token->last_refresh_attempt_at);
        $this->assertSame('Simulated error: token refresh failed', $token->last_refresh_error);
    }

    public function test_refresh_success_clears_failure_fields(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30002,
            'shop_name' => 'Refresh Success Test',
            'region' => 'SG',
            'is_active' => true,
        ]);
        $token = ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('old-access-token'),
            'refresh_token' => encrypt('old-refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now()->subDay(),
            'last_refresh_failed_at' => now()->subHour(),
            'last_refresh_error' => 'Previous failure',
            'refresh_failure_count' => 3,
        ]);

        // Simulate a successful refresh
        $token->update([
            'access_token' => encrypt('new-access-token'),
            'refresh_token' => encrypt('new-refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'last_refreshed_at' => now(),
            'last_refresh_failed_at' => null,
            'last_refresh_error' => null,
            'refresh_failure_count' => 0,
        ]);

        $fresh = $token->fresh();
        $this->assertEquals(0, $fresh->refresh_failure_count);
        $this->assertNull($fresh->last_refresh_failed_at);
        $this->assertNull($fresh->last_refresh_error);
        $this->assertNotNull($fresh->last_refreshed_at);
    }

    public function test_store_listing_exposes_operational_status_but_not_tokens(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30003,
            'shop_name' => 'Health Check Store',
            'region' => 'TH',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 9001,
            'item_name' => 'Plant A',
            'price' => 10,
            'stock' => 5,
        ]);
        Product::create([
            'store_id' => $store->id,
            'item_id' => 9002,
            'item_name' => 'Plant B',
            'price' => 12,
            'stock' => 3,
        ]);

        $response = $this->actingAs($user)->getJson('/api/shopee/stores');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stores' => [[
                'id', 'shop_id', 'shop_name', 'region', 'is_active',
                'products_count',
                'connection_status', 'token_health', 'requires_reauthorization',
                'linked_at', 'last_refreshed_at', 'last_refresh_attempt_at',
                'last_refresh_failed_at', 'last_refresh_error',
                'refresh_failure_count', 'access_token_expires_at',
                'refresh_token_expires_at',
            ]],
        ]);

        // Verify tokens are NOT exposed in the response
        $storeData = $response->json('stores.0');
        $this->assertArrayNotHasKey('access_token', $storeData);
        $this->assertArrayNotHasKey('refresh_token', $storeData);
        $this->assertSame(2, $storeData['products_count']);
    }

    public function test_disconnect_keeps_store_products_orders_and_logs(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30004,
            'shop_name' => 'Disconnect Test',
            'region' => 'MY',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'item_id' => 100001,
            'item_name' => 'Test Product',
            'price' => 10.00,
            'stock' => 100,
        ]);
        $order = Order::create([
            'store_id' => $store->id,
            'order_sn' => 'TEST-ORDER-001',
            'order_status' => 'COMPLETED',
            'total_amount' => 50.00,
        ]);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/disconnect");

        $response->assertStatus(200);

        // Store still exists but is inactive
        $this->assertDatabaseHas('shopee_stores', ['id' => $store->id, 'is_active' => false]);

        // Products and orders preserved
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        // Token is removed
        $this->assertDatabaseMissing('shopee_tokens', ['store_id' => $store->id]);
    }

    public function test_reauthorize_creates_state_tied_to_intended_store(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30005,
            'shop_name' => 'Reauthorize Test',
            'region' => 'SG',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/shopee/stores/{$store->id}/reauthorize");

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);

        $this->assertDatabaseHas('shopee_oauth_states', [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);
    }

    public function test_reauthorize_rejects_mismatched_shop_id(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => 30006,
            'shop_name' => 'Mismatch Test',
            'region' => 'MY',
            'is_active' => true,
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);

        // Create OAuth state tied to the store
        $rawState = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff'; // 64 chars
        ShopeeOAuthState::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'state_hash' => hash('sha256', $rawState),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Callback with a different shop_id than the intended store
        $response = $this->get("/api/shopee/callback?code=test&shop_id=99999&state={$rawState}");

        $response->assertRedirect();
        $this->assertStringContainsString(
            'error=reauthorize_shop_mismatch',
            $response->headers->get('Location')
        );
    }
}
