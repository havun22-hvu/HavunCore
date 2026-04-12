<?php

namespace App\Console\Commands;

use App\Models\ChaosResult;
use App\Models\ErrorLog;
use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use App\Models\SlowQuery;
use Illuminate\Console\Command;

class CleanupMetricsCommand extends Command
{
    protected $signature = 'observability:cleanup';

    protected $description = 'Purge old observability data based on retention config';

    public function handle(): int
    {
        $rawDays = config('observability.retention.raw_days', 30);
        $aggregatedDays = config('observability.retention.aggregated_days', 365);

        $rawCutoff = now()->subDays($rawDays);
        $aggregatedCutoff = now()->subDays($aggregatedDays);

        $this->info("Cleaning up data older than {$rawDays} days (raw) / {$aggregatedDays} days (aggregated)");

        $deleted = [
            'request_metrics' => RequestMetric::where('created_at', '<', $rawCutoff)->delete(),
            'error_logs' => ErrorLog::where('created_at', '<', $rawCutoff)->delete(),
            'slow_queries' => SlowQuery::where('created_at', '<', $rawCutoff)->delete(),
            'metrics_aggregated' => MetricsAggregated::where('period_start', '<', $aggregatedCutoff)->delete(),
            'chaos_results' => ChaosResult::where('created_at', '<', $rawCutoff)->delete(),
        ];

        foreach ($deleted as $table => $count) {
            $this->info("  {$table}: {$count} rows deleted");
        }

        $total = array_sum($deleted);
        $this->info("Total: {$total} rows cleaned up");

        return self::SUCCESS;
    }
}
