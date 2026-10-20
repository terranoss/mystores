<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\LazadaService;
use App\Services\ShopeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_shopee_sync_returns_structured_store_outcomes_and_keeps_results(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'shopee', 9801, 'Sync Shopee');

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andReturn($this->emptyShopeeClient());
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->postJson('/api/orders/sync');

        $response->assertOk()
            ->assertJsonPath('failed', false)
            ->assertJsonPath('results', ["Store #{$store->id}: synced"])
            ->assertJsonPath('stores.0.store_id', $store->id)
            ->assertJsonPath('stores.0.platform', 'shopee')
            ->assertJsonPath('stores.0.ok', true)
            ->assertJsonPath('stores.0.message', 'synced');
    }

    public function test_shopee_sync_reports_store_failure_from_failed_flag(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'shopee', 9802, 'Sync Failure');

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldReceive('forStore')->once()->andThrow(new \RuntimeException('boom'));
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->postJson('/api/orders/sync');

        $response->assertOk()
            ->assertJsonPath('failed', true)
            ->assertJsonPath('results', ["Store #{$store->id}: failed - boom"])
            ->assertJsonPath('stores.0.store_id', $store->id)
            ->assertJsonPath('stores.0.platform', 'shopee')
            ->assertJsonPath('stores.0.ok', false)
            ->assertJsonPath('stores.0.message', 'failed - boom');
    }

    public function test_shopee_sync_marks_a_store_without_token_as_failed(): void
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => 'shopee',
            'shop_id' => 9803,
            'shop_name' => 'No Token',
            'region' => 'MY',
            'is_active' => true,
        ]);

        $shopee = Mockery::mock(ShopeeService::class);
        $shopee->shouldNotReceive('forStore');
        $this->app->instance(ShopeeService::class, $shopee);

        $response = $this->actingAs($user)->postJson('/api/orders/sync');

        $response->assertOk()
            ->assertJsonPath('failed', true)
            ->assertJsonPath('results', ["Store #{$store->id}: skipped (no token)"])
            ->assertJsonPath('stores.0.store_id', $store->id)
            ->assertJsonPath('stores.0.ok', false)
            ->assertJsonPath('stores.0.message', 'skipped (no token)');
    }

    public function test_lazada_sync_returns_structured_store_outcomes_and_keeps_results(): void
    {
        $user = User::factory()->create();
        $store = $this->makeStore($user, 'lazada', 9804, 'Sync Lazada');

        $lazada = Mockery::mock(LazadaService::class);
        $lazada->shouldReceive('credentialsForStore')->andReturn([
            'access_token' => 'tok',
            'region' => 'my',
        ]);
        $lazada->shouldReceive('resolveShopName')->andReturn('Sync Lazada');
        $lazada->shouldReceive('getOrdersList')->once()->andReturn([
            'code' => '0',
            'data' => ['orders' => [], 'count' => 0, 'countTotal' => 0],
        ]);
        $this->app->instance(LazadaService::class, $lazada);

        $response = $this->actingAs($user)->postJson('/api/lazada/orders/sync');

        $response->assertOk()
            ->assertJsonPath('failed', false)
            ->assertJsonPath('results', ["Store #{$store->id}: synced"])
            ->assertJsonPath('stores.0.store_id', $store->id)
            ->assertJsonPath('stores.0.platform', 'lazada')
            ->assertJsonPath('stores.0.ok', true)
            ->assertJsonPath('stores.0.message', 'synced');
    }

    private function emptyShopeeClient(): object
    {
        $orderApi = new class {
            public function getOrderList(array $params): array
            {
                return ['order_list' => [], 'more' => false];
            }
        };

        return new class($orderApi) extends \EcomPHP\Shopee\Client {
            public function __construct(private object $orderApi)
            {
                parent::__construct(1, 'test-key');
            }

            public function __get($resourceName)
            {
                if ($resourceName === 'Order') {
                    return $this->orderApi;
                }

                return parent::__get($resourceName);
            }
        };
    }

    private function makeStore(User $user, string $platform, int $shopId, string $name): ShopeeStore
    {
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'platform' => $platform,
            'shop_id' => $shopId,
            'shop_name' => $name,
            'region' => 'MY',
            'is_active' => true,
        ]);

        ShopeeToken::create([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addDays(10),
            'refresh_token_expires_at' => now()->addDays(100),
            'auth_expires_at' => now()->addDays(100),
            'last_refreshed_at' => now(),
        ]);

        return $store->fresh('token');
    }
}
