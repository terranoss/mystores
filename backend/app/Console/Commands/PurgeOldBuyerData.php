<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class PurgeOldBuyerData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:purge-buyer-data 
        {--days=90 : Retention period in days (default: 90)}
        {--dry-run : Preview changes without modifying data}
        {--platform=all : Platform to purge (shopee, lazada, all)}
        {--batch-size=100 : Number of records to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge buyer PII from orders older than specified days (Shopee 90-day requirement)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $platform = $this->option('platform');
        $batchSize = (int) $this->option('batch-size');
        
        $cutoff = now()->subDays($days);
        
        $this->info("Starting buyer data purge for orders older than {$days} days (before {$cutoff->toDateString()})");
        $dryRunText = $dryRun ? 'Yes' : 'No';
        $this->info("Platform: {$platform}, Dry-run: {$dryRunText}, Batch size: {$batchSize}");
        
        // Build base query for orders older than cutoff
        $query = Order::where('created_at', '<', $cutoff);
        
        // Filter by platform if specified
        if ($platform !== 'all') {
            $query->whereHas('store', function ($q) use ($platform) {
                $q->where('platform', $platform);
            });
        }
        
        // Only process orders that actually have PII to purge
        $query->where(function ($q) {
            $q->whereNotNull('buyer_username')
              ->orWhereNotNull('shipping_address')
              ->orWhereNotNull('raw_response');
        });
        
        $totalCount = $query->count();
        
        // Debug output
        if ($this->option('dry-run')) {
            $this->info("DEBUG: Found {$totalCount} orders matching criteria");
            if ($totalCount > 0) {
                $sampleOrders = $query->limit(5)->get(['id', 'created_at', 'buyer_username']);
                foreach ($sampleOrders as $order) {
                    $this->info("DEBUG: Order ID {$order->id}, created_at: {$order->created_at}, buyer: {$order->buyer_username}");
                }
            }
        }
        
        if ($totalCount === 0) {
            $this->info("No orders found matching purge criteria.");
            return 0;
        }
        
        $this->info("Found {$totalCount} orders to process.");
        
        if ($dryRun) {
            $this->info("[DRY RUN] Would purge buyer data from {$totalCount} orders.");
            $this->info("[DRY RUN] No changes made to database.");
            return 0;
        }
        
        // Process in batches to avoid memory issues
        $processed = 0;
        $updated = 0;
        
        $query->chunkById($batchSize, function ($orders) use (&$processed, &$updated, &$days) {
            foreach ($orders as $order) {
                $processed++;
                
                // Track if we made any changes
                $changed = false;
                
                // Anonymize buyer_username
                if ($order->buyer_username !== null) {
                    $order->buyer_username = null;
                    $changed = true;
                }
                
                // Anonymize shipping_address
                if ($order->shipping_address !== null) {
                    $order->shipping_address = null;
                    $changed = true;
                }
                
                // Sanitize raw_response (remove PII, keep operational data)
                if ($order->raw_response !== null && is_array($order->raw_response)) {
                    $sanitized = $this->sanitizeRawResponse($order->raw_response);
                    if ($sanitized !== $order->raw_response) {
                        $order->raw_response = $sanitized;
                        $changed = true;
                    }
                }
                
                // Save if any changes were made
                if ($changed) {
                    $order->save();
                    $updated++;
                }
                
                // Progress indicator every 50 records
                if ($processed % 50 === 0) {
                    $this->output->write('.');
                }
            }
        });
        
        $this->info(""); // New line after progress dots
        $this->info("Purged buyer data from {$updated} of {$processed} processed orders.");
        
        // Log for audit trail
        Log::info('Buyer data purged per 90-day retention policy', [
            'orders_processed' => $processed,
            'orders_updated' => $updated,
            'cutoff_date' => $cutoff->toDateString(),
            'platform' => $platform,
            'days' => $days,
            'dry_run' => false,
        ]);
        
        return 0;
    }
    
    /**
     * Sanitize raw_response by removing PII fields while keeping operational data.
     *
     * @param array $rawResponse
     * @return array
     */
    private function sanitizeRawResponse(array $rawResponse): array
    {
        // Define PII fields to remove (based on Shopee and Lazada API responses)
        $piiFields = [
            // Shopee fields
            'buyer_username',
            'buyer_user_name',
            'recipient_address',
            'receiver_address',
            'address_shipping',
            'receiver_phone',
            'receiver_email',
            'phone',
            'email',
            
            // Lazada fields
            'customer_first_name',
            'customer_last_name',
            'address_shipping',
            'phone',
            'email',
            'contact_phone',
            'contact_email',
            
            // Common PII in nested structures
            'receiver',
            'buyer',
            'customer',
            'address',
            'contact_info',
        ];
        
        return $this->removePiIFromArray($rawResponse, $piiFields);
    }
    
    /**
     * Recursively remove PII fields from an array.
     *
     * @param array $data
     * @param array $piiFields List of field names to remove
     * @return array
     */
    private function removePiIFromArray(array $data, array $piiFields): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Skip if this key is a PII field
            if (in_array(strtolower($key), array_map('strtolower', $piiFields), true)) {
                continue;
            }
            
            // Recursively process arrays and objects
            if (is_array($value)) {
                $result[$key] = $this->removePiIFromArray($value, $piiFields);
            } elseif (is_object($value)) {
                // Convert object to array, process, then convert back if needed
                // For simplicity, we'll just skip objects as they're less likely to contain direct PII
                $result[$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}