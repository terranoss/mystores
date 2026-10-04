<?php

namespace Tests\Feature;

use App\Exceptions\StoreNotActiveException;
use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\ProductService;
use App\Services\ShopeeService;
use EcomPHP\Shopee\Client;
use EcomPHP\Shopee\Errors\AuthorizationException;
use EcomPHP\Shopee\Errors\ShopeeException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ShopeeTokenRefreshTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refresh_store_token_persists_new_pair_and_clears_failures(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token' => encrypt('old-access'),
            'refresh_token' => encrypt('old-refresh'),
            'access_token_expires_at' => now()->subMinutes(5),
            'refresh_failure_count' => 2,
            'last_refresh_error' => 'previous failure',
            'last_refresh_failed_at' => now()->subHour(),
        ]);

        Cache::put("shopee:token:shop_{$store->shop_id}", 'stale-cached-token', 3600);

        $service = $this->shopeeServiceWithAuthMock([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expire_in' => 14400,
        ]);

        $service->refreshStoreToken($store);

        $token = $store->fresh()->token;
        $this->assertSame('new-access', decrypt($token->access_token));
        $this->assertSame('new-refresh', decrypt($token->refresh_token));
        $this->assertEquals(0, $token->refresh_failure_count);
        $this->assertNull($token->last_refresh_error);
        $this->assertNull($token->last_refresh_failed_at);
        $this->assertNotNull($token->last_refreshed_at);
        $this->assertTrue($token->access_token_expires_at->gt(now()->addHours(3)));
        $this->assertFalse(Cache::has("shopee:token:shop_{$store->shop_id}"));
    }

    public function test_refresh_store_token_reactivates_inactive_store(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token_expires_at' => now()->subMinute(),
        ], isActive: false);

        $service = $this->shopeeServiceWithAuthMock([
            'access_token' => 'revived-access',
            'refresh_token' => 'revived-refresh',
            'expire_in' => 14400,
        ]);

        $service->refreshStoreToken($store);

        $this->assertTrue($store->fresh()->is_active);
    }

    public function test_refresh_store_token_failure_increments_count(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token_expires_at' => now()->subMinute(),
            'refresh_failure_count' => 1,
        ]);

        $auth = Mockery::mock();
        $auth->shouldReceive('refreshNewToken')
            ->once()
            ->andThrow(new \RuntimeException('refresh rejected'));

        $service = $this->shopeeServiceWithAuth($auth);

        try {
            $service->refreshStoreToken($store);
            $this->fail('Expected refresh to throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('refresh rejected', $e->getMessage());
        }

        $token = $store->fresh()->token;
        $this->assertEquals(2, $token->refresh_failure_count);
        $this->assertSame('refresh rejected', $token->last_refresh_error);
        $this->assertNotNull($token->last_refresh_failed_at);
    }

    public function test_for_store_refreshes_when_access_token_expired(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token' => encrypt('expired-access'),
            'refresh_token' => encrypt('still-valid-refresh'),
            'access_token_expires_at' => now()->subMinutes(10),
        ]);

        $service = $this->shopeeServiceWithAuthMock([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expire_in' => 14400,
        ]);

        $client = $service->forStore($store);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame('fresh-access', decrypt($store->fresh()->token->access_token));
    }

    public function test_for_store_refreshes_when_access_token_within_buffer(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token' => encrypt('soon-expired'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addMinutes(20),
        ]);

        $service = $this->shopeeServiceWithAuthMock([
            'access_token' => 'buffered-access',
            'refresh_token' => 'buffered-refresh',
            'expire_in' => 14400,
        ]);

        $service->forStore($store);

        $this->assertSame('buffered-access', decrypt($store->fresh()->token->access_token));
    }

    public function test_for_store_skips_refresh_when_access_token_still_fresh(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token' => encrypt('still-fresh-access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addHours(3),
        ]);

        $auth = Mockery::mock();
        $auth->shouldReceive('refreshNewToken')->never();

        $service = $this->shopeeServiceWithAuth($auth);
        $service->forStore($store);

        $this->assertSame('still-fresh-access', decrypt($store->fresh()->token->access_token));
    }

    public function test_call_with_token_retry_refreshes_and_retries_without_disconnect(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token_expires_at' => now()->addHours(2),
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $client = Mockery::mock(Client::class);
        $shopee->shouldReceive('forStore')->twice()->with(Mockery::on(fn ($s) => $s->id === $store->id))->andReturn($client);
        $shopee->shouldReceive('clearStoreCache')->once()->with(Mockery::on(fn ($s) => $s->id === $store->id));
        $shopee->shouldReceive('refreshStoreToken')->once()->with(Mockery::on(fn ($s) => $s->id === $store->id));
        $shopee->shouldReceive('invalidateStore')->never();

        $productService = new ProductService($shopee);
        $method = new \ReflectionMethod(ProductService::class, 'callWithTokenRetry');
        $method->setAccessible(true);

        $attempts = 0;
        $result = $method->invoke($productService, $store, function () use (&$attempts) {
            $attempts++;
            if ($attempts === 1) {
                throw new AuthorizationException('Invalid access_token');
            }

            return ['ok' => true];
        });

        $this->assertSame(['ok' => true], $result);
        $this->assertEquals(2, $attempts);
        $this->assertTrue($store->fresh()->is_active);
    }

    public function test_call_with_token_retry_disconnects_when_refresh_fails(): void
    {
        $store = $this->makeStoreWithToken([
            'access_token_expires_at' => now()->addHours(2),
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $client = Mockery::mock(Client::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($client);
        $shopee->shouldReceive('clearStoreCache')->once();
        $shopee->shouldReceive('refreshStoreToken')->once()->andThrow(new \RuntimeException('cannot refresh'));
        $shopee->shouldReceive('invalidateStore')->once()->with(Mockery::on(fn ($s) => $s->id === $store->id));

        $productService = new ProductService($shopee);
        $method = new \ReflectionMethod(ProductService::class, 'callWithTokenRetry');
        $method->setAccessible(true);

        $this->expectException(StoreNotActiveException::class);
        $method->invoke($productService, $store, function () {
            throw new ShopeeException('Invalid access_token');
        });
    }

    public function test_refresh_tokens_command_schedule_is_every_fifteen_minutes(): void
    {
        $schedule = app(Schedule::class);
        $scheduled = false;

        foreach ($schedule->events() as $event) {
            $cmd = (string) ($event->command ?? '');
            if (str_contains($cmd, 'shopee:refresh-tokens')) {
                $this->assertSame('*/15 * * * *', $event->expression);
                $scheduled = true;
            }
        }

        $this->assertTrue($scheduled, 'shopee:refresh-tokens should be scheduled every 15 minutes');
    }

    /**
     * @param  array<string, mixed>  $tokenOverrides
     */
    private function makeStoreWithToken(array $tokenOverrides = [], bool $isActive = true): ShopeeStore
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => random_int(40000, 49999),
            'shop_name' => 'Token Refresh Test',
            'region' => 'MY',
            'is_active' => $isActive,
        ]);

        ShopeeToken::create(array_merge([
            'store_id' => $store->id,
            'access_token' => encrypt('access-token'),
            'refresh_token' => encrypt('refresh-token'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(30),
            'auth_expires_at' => now()->addYear(),
            'last_refreshed_at' => now()->subDay(),
            'refresh_failure_count' => 0,
        ], $tokenOverrides));

        return $store->fresh(['token']);
    }

    /**
     * @param  array{access_token: string, refresh_token: string, expire_in: int}  $newTokens
     */
    private function shopeeServiceWithAuthMock(array $newTokens): ShopeeService
    {
        $auth = Mockery::mock();
        $auth->shouldReceive('refreshNewToken')
            ->once()
            ->andReturn($newTokens);

        return $this->shopeeServiceWithAuth($auth);
    }

    private function shopeeServiceWithAuth(object $auth): ShopeeService
    {
        return new class($auth) extends ShopeeService {
            public function __construct(private object $authMock)
            {
                parent::__construct();
            }

            public function auth()
            {
                return $this->authMock;
            }
        };
    }
}
