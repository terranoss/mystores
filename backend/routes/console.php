<?php

use App\Models\ShopeeOAuthState;
use App\Services\NotificationGeneratorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh Shopee tokens every 15 minutes (backup to lazy refresh on API calls)
Schedule::command('shopee:refresh-tokens')->everyFifteenMinutes()->withoutOverlapping();

// Refresh Lazada tokens hourly (access tokens last ~30 days)
Schedule::command('lazada:refresh-tokens')->hourly()->withoutOverlapping();

// Sync Shopee products every 30 minutes
Schedule::command('shopee:sync-products')->everyThirtyMinutes()->withoutOverlapping();

// Sync Shopee orders every 15 minutes
Schedule::command('shopee:sync-orders')->everyFifteenMinutes()->withoutOverlapping();

// Sync Lazada orders every 15 minutes
Schedule::command('lazada:sync-orders')->everyFifteenMinutes()->withoutOverlapping();

// Drop pick-list photos once no open orders still need those items
Schedule::command('picklist:cleanup-photos')->hourly()->withoutOverlapping();

// Remove expired API tokens and stale OAuth state records.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(function () {
    ShopeeOAuthState::where('expires_at', '<', now()->subDay())->delete();
})->daily();

// Purge buyer PII from orders older than 90 days (Shopee requirement)
Schedule::command('orders:purge-buyer-data')->dailyAt('02:00');

// Notify owners about degrading store connections (expiring / failed tokens).
Schedule::call(function () {
    app(NotificationGeneratorService::class)->generateStoreHealthNotifications();
})->name('store-health-notifications')->dailyAt('08:00')->withoutOverlapping();

// Monitor queue health: alert if jobs remain pending for more than 15 minutes
Schedule::call(function () {
    $stuckJobsCount = \Illuminate\Support\Facades\DB::table('jobs')
        ->where('attempts', 0)
        ->where('created_at', '<', now()->subMinutes(15)->timestamp)
        ->count();

    if ($stuckJobsCount > 0) {
        \Illuminate\Support\Facades\Log::warning("[QUEUE HEALTH ALERT] {$stuckJobsCount} pending job(s) in queue have not been processed for >15 minutes. Ensure 'artisan queue:work' cron is running.");
    }
})->name('queue-health-check')->everyFifteenMinutes()->withoutOverlapping();

// Rotate & trim log files if they grow past 10MB to prevent server disk exhaustion
Schedule::call(function () {
    $logFiles = [
        storage_path('logs/queue.log'),
        storage_path('logs/scheduler.log'),
    ];

    foreach ($logFiles as $file) {
        if (file_exists($file) && filesize($file) > 10 * 1024 * 1024) {
            // Keep the last 1MB of log lines
            $fp = fopen($file, 'r');
            fseek($fp, -1 * 1024 * 1024, SEEK_END);
            $content = fread($fp, 1024 * 1024);
            fclose($fp);

            file_put_contents($file, "[...truncated by daily scheduler...]\n" . $content);
        }
    }
})->name('log-rotation')->daily();
