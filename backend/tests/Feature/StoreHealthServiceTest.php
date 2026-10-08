<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\StoreHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private StoreHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StoreHealthService::class);
    }

    public function test_store_without_token_reports_missing_token(): void
    {
        $health = $this->service->computeHealth($this->makeStore());

        $this->assertSame('missing_token', $health['connection_status']);
        $this->assertSame('expired', $health['token_health']);
        $this->assertFalse($health['requires_reauthorization']);
        $this->assertFalse($this->service->isOperational($this->makeStore(isActive: false, shopId: 40002)));
    }

    public function test_healthy_token_reports_active(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store);

        $health = $this->service->computeHealth($store);

        $this->assertSame('active', $health['connection_status']);
        $this->assertSame('healthy', $health['token_health']);
        $this->assertFalse($health['requires_reauthorization']);
        $this->assertTrue($this->service->isOperational($store));
    }

    public function test_inactive_store_reports_disconnected_even_with_valid_token(): void
    {
        $store = $this->makeStore(isActive: false);
        $this->attachToken($store);

        $health = $this->service->computeHealth($store);

        $this->assertSame('disconnected', $health['connection_status']);
        $this->assertFalse($health['requires_reauthorization']);
        $this->assertFalse($this->service->isOperational($store));
    }

    public function test_token_expiring_within_warning_window_reports_expiring_soon(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, [
            'access_token_expires_at' => now()->addSeconds(StoreHealthService::ACCESS_TOKEN_WARNING_SECONDS - 60),
        ]);

        $health = $this->service->computeHealth($store);

        $this->assertSame('expiring_soon', $health['connection_status']);
        $this->assertSame('expiring_soon', $health['token_health']);
        $this->assertFalse($health['requires_reauthorization']);
    }

    public function test_expired_access_token_reports_expiring_soon(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, ['access_token_expires_at' => now()->subMinute()]);

        $this->assertSame('expiring_soon', $this->service->computeHealth($store)['connection_status']);
    }

    public function test_null_access_token_expiry_reports_expiring_soon(): void
    {
        $store = $this->makeStore();
        $token = $this->attachToken($store);

        // Defensive guard: the column is NOT NULL, but a malformed/hydrated model
        // must not be classified as healthy.
        $token->access_token_expires_at = null;
        $store->setRelation('token', $token);

        $this->assertSame('expiring_soon', $this->service->computeHealth($store)['connection_status']);
    }

    public function test_refresh_token_expiring_within_warning_window_requires_reauthorization(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, [
            'refresh_token_expires_at' => now()->addDays(StoreHealthService::REFRESH_TOKEN_WARNING_DAYS - 1),
        ]);

        $health = $this->service->computeHealth($store);

        $this->assertSame('expiring_soon', $health['connection_status']);
        $this->assertTrue($health['requires_reauthorization']);
        $this->assertTrue($this->service->requiresReauthorization($store));
    }

    public function test_past_authorization_reports_reauthorize_required(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, ['auth_expires_at' => now()->subDay()]);

        $health = $this->service->computeHealth($store);

        $this->assertSame('reauthorize_required', $health['connection_status']);
        $this->assertSame('expired', $health['token_health']);
        $this->assertTrue($health['requires_reauthorization']);
    }

    public function test_single_refresh_failure_reports_refresh_failed_without_reauth(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, [
            'refresh_failure_count' => 1,
            'last_refresh_failed_at' => now()->subMinutes(5),
            'last_refresh_error' => 'Invalid client',
        ]);

        $health = $this->service->computeHealth($store);

        $this->assertSame('refresh_failed', $health['connection_status']);
        $this->assertFalse($health['requires_reauthorization']);
    }

    public function test_refresh_failures_at_threshold_require_reauthorization(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, [
            'refresh_failure_count' => StoreHealthService::REFRESH_FAILURE_THRESHOLD,
            'last_refresh_failed_at' => now()->subMinutes(5),
        ]);

        $health = $this->service->computeHealth($store);

        $this->assertSame('reauthorize_required', $health['connection_status']);
        $this->assertTrue($health['requires_reauthorization']);
    }

    public function test_stale_failure_count_without_failure_timestamp_is_ignored(): void
    {
        $store = $this->makeStore();
        $this->attachToken($store, [
            'refresh_failure_count' => 5,
            'last_refresh_failed_at' => null,
        ]);

        $this->assertSame('active', $this->service->computeHealth($store)['connection_status']);
    }

    private function makeStore(bool $isActive = true, int $shopId = 0): ShopeeStore
    {
        static $nextShopId = 41000;

        return ShopeeStore::create([
            'user_id' => User::factory()->create()->id,
            'shop_id' => $shopId ?: $nextShopId++,
            'shop_name' => 'Health Test Shop',
            'platform' => 'shopee',
            'region' => 'MY',
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function attachToken(ShopeeStore $store, array $overrides = []): ShopeeToken
    {
        $token = ShopeeToken::create(array_merge([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(90),
            'auth_expires_at' => now()->addYear(),
            'refresh_failure_count' => 0,
        ], $overrides));

        $store->setRelation('token', $token);

        return $token;
    }
}
