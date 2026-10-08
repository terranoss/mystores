<?php

namespace Tests\Feature;

use App\Models\ShopeeStore;
use App\Models\ShopeeToken;
use App\Models\User;
use App\Services\NotificationGeneratorService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreHealthNotificationTest extends TestCase
{
    use RefreshDatabase;

    private NotificationGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationGeneratorService::class);
    }

    public function test_expiring_store_notifies_owner_once(): void
    {
        ['user' => $user, 'store' => $store] = $this->seedStore();
        $this->attachToken($store, ['access_token_expires_at' => now()->addMinutes(30)]);

        $this->service->generateStoreHealthNotifications();

        $notification = $user->fresh()->unreadNotifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('store_reauth', $notification->data['category']);
        $this->assertSame($store->id, $notification->data['store_id']);
        $this->assertStringContainsString('expiring soon', $notification->data['message']);

        // Second run must not duplicate while the first notification is unread.
        $this->service->generateStoreHealthNotifications();
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_healthy_store_is_not_notified(): void
    {
        ['user' => $user, 'store' => $store] = $this->seedStore();
        $this->attachToken($store);

        $this->service->generateStoreHealthNotifications();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_repeated_refresh_failures_notify_owner(): void
    {
        ['user' => $user, 'store' => $store] = $this->seedStore();
        $this->attachToken($store, [
            'refresh_failure_count' => 3,
            'last_refresh_failed_at' => now()->subHour(),
        ]);

        $this->service->generateStoreHealthNotifications();

        $notification = $user->fresh()->unreadNotifications()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('re-authorization', $notification->data['message']);
    }

    public function test_store_without_token_notifies_owner(): void
    {
        ['user' => $user] = $this->seedStore();

        $this->service->generateStoreHealthNotifications();

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_store_health_notifications_are_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) ($e->command ?? ''), 'store-health-notifications')
                || $e->description === 'store-health-notifications'
                || str_contains(json_encode($e->getSummaryForDisplay()), 'store-health'));

        $this->assertNotNull($event, 'store health notification job should be scheduled');
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    /**
     * @return array{user: User, store: ShopeeStore}
     */
    private function seedStore(): array
    {
        $user = User::factory()->create();
        $store = ShopeeStore::create([
            'user_id' => $user->id,
            'shop_id' => random_int(50000, 59999),
            'shop_name' => 'Notify Health Shop',
            'platform' => 'shopee',
            'region' => 'MY',
            'is_active' => true,
        ]);

        return compact('user', 'store');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function attachToken(ShopeeStore $store, array $overrides = []): ShopeeToken
    {
        return ShopeeToken::create(array_merge([
            'store_id' => $store->id,
            'access_token' => encrypt('access'),
            'refresh_token' => encrypt('refresh'),
            'access_token_expires_at' => now()->addHours(4),
            'refresh_token_expires_at' => now()->addDays(90),
            'auth_expires_at' => now()->addYear(),
            'refresh_failure_count' => 0,
        ], $overrides));
    }
}
