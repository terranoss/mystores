<?php

namespace Tests\Feature;

use App\Models\ShopeeOAuthState;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tokens_expire_without_changing_the_login_response_contract(): void
    {
        config(['sanctum.new_token_expiration' => 60]);
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
        $token = PersonalAccessToken::findToken($response->json('token'));

        $this->assertNotNull($token?->expires_at);
        $this->assertTrue($token->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));
    }

    public function test_login_is_rate_limited(): void
    {
        $email = 'limited-'.uniqid().'@example.com';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_oauth_state_is_opaque_and_stored_with_an_expiry(): void
    {
        config([
            'shopee.partner_id' => 123,
            'shopee.partner_key' => 'test-key',
            'shopee.redirect_url' => 'https://example.test/api/shopee/callback',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/shopee/auth-url')->assertOk();
        parse_str((string) parse_url($response->json('url'), PHP_URL_QUERY), $query);
        parse_str((string) parse_url($query['redirect'], PHP_URL_QUERY), $redirectQuery);
        $state = $redirectQuery['state'];

        $this->assertSame(64, strlen($state));
        $this->assertDatabaseHas('shopee_oauth_states', [
            'user_id' => $user->id,
            'state_hash' => hash('sha256', $state),
        ]);
        $this->assertTrue(ShopeeOAuthState::first()->expires_at->isFuture());
    }

    public function test_invalid_oauth_state_redirects_without_leaking_details(): void
    {
        config(['app.frontend_url' => 'https://frontend.example.test']);

        $response = $this->get('/api/shopee/callback?code=test&shop_id=1&state='.str_repeat('x', 64));

        $response->assertRedirect('https://frontend.example.test/dashboard?error=oauth_failed');
        $this->assertStringNotContainsString('invalid', strtolower($response->headers->get('Location')));
    }

    public function test_security_headers_are_added(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_webhook_rejects_invalid_signature_even_in_debug_mode(): void
    {
        config([
            'app.debug' => true,
            'shopee.partner_id' => 123,
            'shopee.partner_key' => 'test-key',
        ]);

        $response = $this->postJson('/api/shopee/webhook', [
            'event_id' => 'test-event',
            'code' => 3,
        ], ['Authorization' => 'invalid-signature']);

        $response->assertStatus(401);
        $this->assertSame('', $response->getContent());
    }

    public function test_webhook_verifies_signature_before_dedupe_cache(): void
    {
        config([
            'shopee.partner_id' => 123,
            'shopee.partner_key' => 'test-key',
            'shopee.webhook_url' => 'https://example.test/api/shopee/webhook',
        ]);

        $eventId = 'signature-before-dedupe';

        $this->postJson('/api/shopee/webhook', [
            'event_id' => $eventId,
            'code' => 3,
            'shop_id' => 1,
            'data' => ['ordersn' => 'ABC'],
        ], ['Authorization' => 'bad-signature'])->assertStatus(401);

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("shopee:webhook:event_{$eventId}"));
    }

    public function test_store_without_token_shows_expired_health(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'is_active' => true,
            'shop_name' => 'No-Token Store',
            'shop_id' => 99999,
            'region' => 'MY',
        ]);

        $response = $this->actingAs($user)->getJson('/api/shopee/stores');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'shop_name' => 'No-Token Store',
            'token_health' => 'expired',
        ]);
    }

    public function test_oauth_callback_missing_code_redirects_with_error(): void
    {
        config(['app.frontend_url' => 'https://frontend.example.test']);

        // Missing 'code' parameter should redirect with error, not return JSON 422
        $response = $this->get('/api/shopee/callback?shop_id=12345&state='.str_repeat('a', 64));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'error=oauth_failed',
            $response->headers->get('Location')
        );
    }

    public function test_unlink_removes_store_and_token(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'is_active' => true,
            'shop_name' => 'Unlink Test Store',
            'shop_id' => 88888,
            'region' => 'MY',
        ]);
        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('test-access-token'),
            'refresh_token' => encrypt('test-refresh-token'),
            'access_token_expires_at' => now()->addDays(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now(),
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/shopee/stores/{$store->id}", [
            'confirmation' => 'DELETE STORE DATA',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Store and all related data deleted permanently.']);
        $this->assertDatabaseMissing('shopee_stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('shopee_tokens', ['store_id' => $store->id]);
    }

    public function test_unlink_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $owner->id,
            'is_active' => true,
            'shop_name' => 'Owned Store',
            'shop_id' => 77777,
            'region' => 'MY',
        ]);

        $response = $this->actingAs($otherUser)->deleteJson("/api/shopee/stores/{$store->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Only the workspace owner can unlink stores.']);
        $this->assertDatabaseHas('shopee_stores', ['id' => $store->id]);
    }
}
