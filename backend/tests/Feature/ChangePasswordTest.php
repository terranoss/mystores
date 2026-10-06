<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnauthorized();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_short_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password-123',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_successful_password_change_updates_hash_and_keeps_current_session(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()->assertJson(['message' => 'Password updated successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));

        // Current session keeps working after the password change
        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    /**
     * Reset cached auth guards between sequential requests in one test.
     *
     * The test case reuses the application (and its cached RequestGuard) across
     * requests, so a user resolved by a previous request would otherwise leak
     * into the next one. Production boots a fresh application per request.
     */
    private function forgetAuthGuards(): void
    {
        $this->app->make('auth')->forgetGuards();
    }

    public function test_password_change_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $currentToken = $user->createToken('current-session')->plainTextToken;
        $otherToken = $user->createToken('other-session')->plainTextToken;

        $this->withToken($currentToken)->postJson('/api/change-password', [
            'current_password' => 'password123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->forgetAuthGuards();

        // Other session is revoked, current session stays valid
        $this->withToken($otherToken)->getJson('/api/me')->assertUnauthorized();

        $this->forgetAuthGuards();

        $this->withToken($currentToken)->getJson('/api/me')->assertOk();
    }

    public function test_change_password_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = $user->createToken('auth-token')->plainTextToken;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($token)->postJson('/api/change-password', [
                'current_password' => 'incorrect-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertStatus(422);
        }

        $this->withToken($token)->postJson('/api/change-password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertTooManyRequests();
    }
}