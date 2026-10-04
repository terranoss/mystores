<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class BackfillOrderOrderedAt extends Command
{
    protected $signature = 'orders:backfill-ordered-at';

    protected $description = 'Fill ordered_at from raw_response.create_time where missing';

    public function handle(OrderService $orders): int
    {
        $result = $orders->backfillOrderedAtFromRaw();
        $this->info("Updated {$result['updated']} order(s); skipped {$result['skipped']} without create_time.");
        return self::SUCCESS;
    }
}
