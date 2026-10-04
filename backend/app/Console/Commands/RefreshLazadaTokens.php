<?php

namespace App\Console\Commands;

use App\Models\ShopeeStore;
use App\Services\LazadaOrderService;
use Illuminate\Console\Command;

class RefreshLazadaTokens extends Command
{
    protected $signature = 'lazada:refresh-tokens';

    protected $description = 'Refresh expiring Lazada access tokens';

    public function handle(LazadaOrderService $lazadaOrders): int
    {
        $stores = ShopeeStore::with('token')
            ->where('platform', 'lazada')
            ->whereHas('token', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('access_token_expires_at', '<=', now()->addHours(24))
                        ->where(function ($refresh) {
                            $refresh->whereNull('refresh_token_expires_at')
                                ->orWhere('refresh_token_expires_at', '>', now());
                        });
                });
            })
            ->get();

        if ($stores->isEmpty()) {
            $this->info('No Lazada tokens need refreshing.');

            return self::SUCCESS;
        }

        $this->info("Refreshing {$stores->count()} Lazada token(s)...");
        $refreshed = 0;
        $failed = 0;

        foreach ($stores as $store) {
            try {
                $lazadaOrders->ensureFreshToken($store);
                $refreshed++;
                $this->line("  ✓ Store #{$store->id} refreshed");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Store #{$store->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Done: {$refreshed} refreshed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
