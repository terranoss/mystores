<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled_and_returns_405(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(405);
    }

    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);
        $response->assertJsonFragment([
            'email' => 'login@example.com',
        ]);
    }

    public function test_invalid_login_returns_generic_401(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'real-password',
        ]);

        // Wrong password
        $response = $this->postJson('/api/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid email or password.',
        ]);

        // Nonexistent email — should give same generic message
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'some-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid email or password.',
        ]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Auth Test User',
            'email' => 'authme@example.com',
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'authme@example.com',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('token');
        $this->assertNotNull($token);

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Auth Test User',
            'email' => 'authme@example.com',
        ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout-test@example.com',
            'password' => 'logout-password',
        ]);

        // Login and get token
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'logout-test@example.com',
            'password' => 'logout-password',
        ]);
        $token1 = $loginResponse->json('token');
        $this->assertNotNull($token1);

        // Logout with token1 — token1 is revoked
        $this->withToken($token1)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // Token1 no longer works for a new login (it's deleted)
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Login again to get token2
        $loginResponse2 = $this->postJson('/api/login', [
            'email' => 'logout-test@example.com',
            'password' => 'logout-password',
        ]);
        $token2 = $loginResponse2->json('token');

        // token2 is different from token1
        $this->assertNotNull($token2);
        $this->assertNotSame($token1, $token2);

        // token1 is gone from DB
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token1));
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();

        // Create a token that has already expired
        $token = $user->createToken('expired-test', ['*'], now()->subDay())->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertStatus(401);
    }
}
