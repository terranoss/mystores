<?php

use App\Models\ShopeeOAuthState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh Shopee tokens every 15 minutes (backup to lazy refresh on API calls)
Schedule::command('shopee:refresh-tokens')->everyFifteenMinutes();

// Refresh Lazada tokens hourly (access tokens last ~30 days)
Schedule::command('lazada:refresh-tokens')->hourly();

// Sync Shopee products every 30 minutes
Schedule::command('shopee:sync-products')->everyThirtyMinutes();

// Sync Shopee orders every 15 minutes
Schedule::command('shopee:sync-orders')->everyFifteenMinutes();

// Sync Lazada orders every 15 minutes
Schedule::command('lazada:sync-orders')->everyFifteenMinutes();

// Drop pick-list photos once no open orders still need those items
Schedule::command('picklist:cleanup-photos')->hourly();

// Remove expired API tokens and stale OAuth state records.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(function () {
    ShopeeOAuthState::where('expires_at', '<', now()->subDay())->delete();
})->daily();

