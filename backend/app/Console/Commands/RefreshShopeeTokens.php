<?php

namespace App\Console\Commands;

use App\Models\ShopeeToken;
use App\Services\ShopeeService;
use Illuminate\Console\Command;

class RefreshShopeeTokens extends Command
{
    protected $signature = 'shopee:refresh-tokens';
    protected $description = 'Refresh expiring Shopee access tokens';

    public function handle(ShopeeService $shopee): int
    {
        $expiringTokens = ShopeeToken::with('store')
            ->where(function ($q) {
                // Tokens expiring within the next hour (covers 30-min lazy buffer + cron drift)
                $q->where('access_token_expires_at', '<=', now()->addHour())
                    ->where('auth_expires_at', '>', now());
            })->orWhere(function ($q) {
                // Tokens on stores deactivated by auth error handling (try to revive them)
                $q->whereHas('store', fn ($sq) => $sq->where('is_active', false))
                    ->where('auth_expires_at', '>', now());
            })->get();

        if ($expiringTokens->isEmpty()) {
            $this->info('No tokens need refreshing.');
            return self::SUCCESS;
        }

        $this->info("Refreshing {$expiringTokens->count()} token(s)...");

        $refreshed = 0;
        $failed = 0;

        foreach ($expiringTokens as $token) {
            $store = $token->store;
            if (!$store) {
                $failed++;
                $this->error("  ✗ Token #{$token->id} has no store");
                continue;
            }

            try {
                $shopee->refreshStoreToken($store);
                $refreshed++;
                $this->line("  ✓ Shop #{$store->shop_id} refreshed");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Shop #{$store->shop_id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Done: {$refreshed} refreshed, {$failed} failed.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
