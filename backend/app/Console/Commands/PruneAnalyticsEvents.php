<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use Illuminate\Console\Command;

class PruneAnalyticsEvents extends Command
{
    protected $signature = 'analytics:prune {--months=13}';

    protected $description = 'Delete analytics events older than N months (default 13)';

    public function handle(): int
    {
        $cutoff = now()->subMonths((int) $this->option('months'));
        $total = 0;
        do {
            $deleted = AnalyticsEvent::where('created_at', '<', $cutoff)->limit(1000)->delete();
            $total += $deleted;
        } while ($deleted > 0);
        $this->info("Pruned {$total} analytics events older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
