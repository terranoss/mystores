<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaOrderService;
use App\Services\LazadaService;
use App\Services\PickListPhotoCleanupService;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LazadaTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ensure_fresh_token_extends_refresh_and_auth_expiry(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->subDay(),
            'refresh_token_expires_at' => now()->addDay(),
            'auth_expires_at' => now()->addDay(),
            'refresh_failure_count' => 2,
            'last_refresh_error' => 'previous failure',
            'last_refresh_failed_at' => now()->subHour(),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('refreshToken')
            ->once()
            ->with('old-refresh')
            ->andReturn([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 2592000,
                'refresh_expires_in' => 15552000,
            ]);

        $this->service($lazada)->ensureFreshToken($store);

        $token = $store->fresh()->token;
        $this->assertSame('new-access', decrypt($token->access_token));
        $this->assertSame('new-refresh', decrypt($token->refresh_token));
        $this->assertTrue($token->access_token_expires_at->gt(now()->addDays(29)));
        $this->assertTrue($token->refresh_token_expires_at->gt(now()->addDays(179)));
        $this->assertTrue($token->auth_expires_at->gt(now()->addDays(179)));
        $this->assertNotNull($token->last_refresh_attempt_at);
        $this->assertEquals(0, $token->refresh_failure_count);
        $this->assertNull($token->last_refresh_error);
        $this->assertNull($token->last_refresh_failed_at);
    }

    public function test_ensure_fresh_token_skips_upstream_call_when_token_is_fresh(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->addDays(10),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldNotReceive('refreshToken');

        $this->service($lazada)->ensureFreshToken($store);

        $this->assertSame('access', decrypt($store->fresh()->token->access_token));
    }

    public function test_second_concurrent_refresh_does_not_burn_the_rotated_token(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->addDays(10),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('refreshToken')->never();

        $service = $this->service($lazada);

        // A request that already waited on the mutex re-reads the token row inside
        // the lock, so the second caller exits early instead of refreshing again.
        $store->fresh()->token->update([
            'access_token' => encrypt('rotated-access'),
            'access_token_expires_at' => now()->addDay(),
            'refresh_token' => encrypt('rotated-refresh'),
        ]);

        $service->ensureFreshToken($store);

        $this->assertSame('rotated-access', decrypt($store->fresh()->token->access_token));
    }

    public function test_ensure_fresh_token_reloads_token_relation_after_refresh(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->subMinute(),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('refreshToken')
            ->once()
            ->andReturn([
                'access_token' => 'locked-access',
                'refresh_token' => 'locked-refresh',
                'expires_in' => 2592000,
            ]);

        // The in-memory relation still holds the pre-refresh token; a successful
        // refresh must reload it so callers never reuse the rotated token.
        $this->service($lazada)->ensureFreshToken($store);

        $this->assertSame('locked-access', decrypt($store->token->access_token));
    }

    public function test_ensure_fresh_token_records_failure_count_on_api_error(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->subMinute(),
            'refresh_failure_count' => 1,
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('refreshToken')
            ->once()
            ->andThrow(new \RuntimeException('refresh rejected by upstream'));

        try {
            $this->service($lazada)->ensureFreshToken($store);
            $this->fail('Expected refresh to throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('refresh rejected by upstream', $e->getMessage());
        }

        $token = $store->fresh()->token;
        $this->assertEquals(2, $token->refresh_failure_count);
        $this->assertSame('refresh rejected by upstream', $token->last_refresh_error);
        $this->assertNotNull($token->last_refresh_failed_at);
        $this->assertNotNull($token->last_refresh_attempt_at);
    }

    public function test_ensure_fresh_token_rejects_expired_refresh_token(): void
    {
        $store = $this->makeStore([
            'access_token_expires_at' => now()->subMinute(),
            'refresh_token_expires_at' => now()->subMinute(),
        ]);

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldNotReceive('refreshToken');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lazada refresh token expired');

        $this->service($lazada)->ensureFreshToken($store);
    }

    private function service(LazadaService $lazada): LazadaOrderService
    {
        return new LazadaOrderService(
            $lazada,
            app(WorkspaceAccess::class),
            app(PickListPhotoCleanupService::class),
            app(WorkspaceNotificationService::class),
        );
    }

    /**
     * @param  array<string, mixed>  $tokenOverrides
     */
    private function makeStore(array $tokenOverrides = []): ShopeeStore
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'lazada',
            'shop_id' => random_int(50000, 59999),
            'shop_name' => 'Lazada Token Test',
            'region' => 'my',
            'is_active' => true,
        ]);

        ShopeeToken::create(array_merge([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('old-refresh'),
            'access_token_expires_at' => now()->addDays(10),
            'refresh_token_expires_at' => now()->addDays(100),
            'auth_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now()->subDay(),
            'refresh_failure_count' => 0,
        ], $tokenOverrides));

        return $store->fresh('token');
    }
}