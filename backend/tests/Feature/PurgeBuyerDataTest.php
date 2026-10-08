<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ShopeeStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurgeBuyerDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and store
        $this->user = User::factory()->create();
        $this->store = ShopeeStore::create([
            'user_id' => $this->user->id,
            'platform' => 'shopee',
            'shop_id' => 123456,
            'shop_name' => 'Test Store',
            'region' => 'my',
            'is_active' => true,
        ]);
    }

    private function ageOrder(Order $order, int $days): void
    {
        $order->forceFill(['created_at' => Carbon::now()->copy()->subDays($days)])->save();
    }

    public function test_it_can_purge_buyer_data_from_old_orders()
    {
        // Create an order older than 90 days with PII
        $oldOrder = Order::create([
            'store_id' => $this->store->id,
            'order_sn' => 'OLD123',
            'order_status' => 'completed',
            'total_amount' => 100.00,
            'currency' => 'MYR',
            'buyer_username' => 'John Doe',
            'shipping_address' => '123 Main St, City, State, 12345, Phone: 555-1234',
            'raw_response' => [
                'buyer_username' => 'John Doe',
                'recipient_address' => [
                    'name' => 'John Doe',
                    'address' => '123 Main St',
                    'city' => 'Anytown',
                    'state' => 'ST',
                    'zip_code' => '12345',
                    'country' => 'USA',
                    'phone_number' => '555-1234',
                    'email_address' => 'john@example.com'
                ],
                'order_sn' => 'OLD123',
                'order_status' => 'completed',
                'total_amount' => 100.00,
                'item_list' => []
            ],
        ]);
        // created_at is not mass-assignable, so age the order explicitly
        $this->ageOrder($oldOrder, 100);

        // Create a recent order with PII (should not be purged)
        $recentOrder = Order::create([
            'store_id' => $this->store->id,
            'order_sn' => 'RECENT123',
            'order_status' => 'pending',
            'total_amount' => 50.00,
            'currency' => 'MYR',
            'buyer_username' => 'Jane Smith',
            'shipping_address' => '456 Oak Ave, City, State, 67890, Phone: 555-5678',
            'raw_response' => [
                'buyer_username' => 'Jane Smith',
                'recipient_address' => [
                    'name' => 'Jane Smith',
                    'address' => '456 Oak Ave',
                    'city' => 'Somewhere',
                    'state' => 'ST',
                    'zip_code' => '67890',
                    'country' => 'USA',
                    'phone_number' => '555-5678',
                    'email_address' => 'jane@example.com'
                ],
                'order_sn' => 'RECENT123',
                'order_status' => 'pending',
                'total_amount' => 50.00,
                'item_list' => []
            ],
        ]);

        // Sanity check the fixtures before running the purge
        $this->assertNotNull($oldOrder->id);
        $this->assertNotNull($recentOrder->id);
        $this->assertEquals('John Doe', $oldOrder->buyer_username);
        $this->assertEquals('Jane Smith', $recentOrder->buyer_username);

        $ninetyDaysAgo = Carbon::now()->copy()->subDays(90);
        $this->assertTrue($oldOrder->created_at->lt($ninetyDaysAgo));
        $this->assertFalse($recentOrder->created_at->lt($ninetyDaysAgo));

        // Run the purge command in dry-run mode first
        $this->artisan('orders:purge-buyer-data', ['--dry-run' => true])
            ->expectsOutput('[DRY RUN] Would purge buyer data from 1 orders.')
            ->assertExitCode(0);

        // Run the actual purge command
        $this->artisan('orders:purge-buyer-data')
            ->expectsOutput('Purged buyer data from 1 of 1 processed orders.')
            ->assertExitCode(0);

        // Refresh the old order from database
        $oldOrder->refresh();
        
        // Assert PII fields are nullified
        $this->assertNull($oldOrder->buyer_username);
        $this->assertNull($oldOrder->shipping_address);
        
        // Assert raw_response has been sanitized (PII removed, non-PII kept)
        $this->assertIsArray($oldOrder->raw_response);
        $this->assertArrayNotHasKey('buyer_username', $oldOrder->raw_response);
        $this->assertArrayNotHasKey('recipient_address', $oldOrder->raw_response);
        $this->assertArrayHasKey('order_sn', $oldOrder->raw_response);
        $this->assertArrayHasKey('order_status', $oldOrder->raw_response);
        $this->assertArrayHasKey('total_amount', $oldOrder->raw_response);
        $this->assertArrayHasKey('item_list', $oldOrder->raw_response);

        // Refresh the recent order from database
        $recentOrder->refresh();
        
        // Assert recent order PII is still intact
        $this->assertEquals('Jane Smith', $recentOrder->buyer_username);
        $this->assertEquals('456 Oak Ave, City, State, 67890, Phone: 555-5678', $recentOrder->shipping_address);
        $this->assertIsArray($recentOrder->raw_response);
        $this->assertArrayHasKey('buyer_username', $recentOrder->raw_response);
        $this->assertArrayHasKey('recipient_address', $recentOrder->raw_response);
    }
}