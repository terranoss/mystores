<?php

namespace App\Console\Commands;

use App\Services\PickListPhotoCleanupService;
use Illuminate\Console\Command;

class CleanupPickListPhotos extends Command
{
    protected $signature = 'picklist:cleanup-photos';

    protected $description = 'Delete pick-list photos when unused, and prune comments older than 14 days';

    public function handle(PickListPhotoCleanupService $cleanup): int
    {
        $deleted = $cleanup->cleanupStale();
        $this->info("Deleted {$deleted} pick-list completion(s).");

        $pruned = $cleanup->pruneOldComments(14);
        $this->info("Pruned {$pruned} pick-list comment(s) older than 14 days.");

        return self::SUCCESS;
    }
}
