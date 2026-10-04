<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class BackfillShopeeOrderQuantities extends Command
{
    protected $signature = 'orders:backfill-shopee-quantities';

    protected $description = 'Fix Shopee order_items.quantity from raw_response.item_list.model_quantity';

    public function handle(OrderService $orders): int
    {
        $result = $orders->backfillQuantitiesFromRaw();
        $this->info("Updated {$result['updated']} order(s); skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
