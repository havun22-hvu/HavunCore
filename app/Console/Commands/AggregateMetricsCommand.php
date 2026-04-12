<?php

namespace App\Console\Commands;

use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use Illuminate\Console\Command;

class AggregateMetricsCommand extends Command
{
    protected $signature = 'observability:aggregate
                            {--period=hourly : Period to aggregate (hourly or daily)}';

    protected $description = 'Aggregate request metrics into hourly/daily summaries';

    public function handle(): int
    {
        $period = $this->option('period');

        if (! in_array($period, ['hourly', 'daily'])) {
            $this->error("Invalid period: {$period}");

            return self::FAILURE;
        }

        $this->aggregate($period);

        return self::SUCCESS;
    }

    protected function aggregate(string $period): void
    {
        $periodStart = $period === 'hourly'
            ? now()->subHour()->startOfHour()
            : now()->subDay()->startOfDay();

        $periodEnd = $period === 'hourly'
            ? $periodStart->copy()->endOfHour()
            : $periodStart->copy()->endOfDay();

        $this->info("Aggregating {$period} metrics for {$periodStart->toDateTimeString()}");

        $metrics = RequestMetric::whereBetween('created_at', [$periodStart, $periodEnd])->get();

        if ($metrics->isEmpty()) {
            $this->info('No metrics to aggregate.');

            return;
        }

        $grouped = $metrics->groupBy('path');

        $count = 0;
        foreach ($grouped as $path => $pathMetrics) {
            $this->upsertAggregation($period, $periodStart, $path, $pathMetrics);
            $count++;
        }

        $this->upsertAggregation($period, $periodStart, null, $metrics);

        $this->info("Aggregated {$count} endpoints + 1 global for {$periodStart->toDateTimeString()}");
    }

    /**
     * Upsert an aggregation row.
     */
    protected function upsertAggregation(string $period, $periodStart, ?string $path, $metrics): void
    {
        $responseTimes = $metrics->pluck('response_time_ms')->sort()->values();
        $count = $responseTimes->count();

        MetricsAggregated::updateOrCreate(
            [
                'period' => $period,
                'period_start' => $periodStart,
                'path' => $path,
            ],
            [
                'request_count' => $count,
                'error_count' => $metrics->where('status_code', '>=', 400)->count(),
                'server_error_count' => $metrics->where('status_code', '>=', 500)->count(),
                'avg_response_time_ms' => round($responseTimes->avg(), 2),
                'p95_response_time_ms' => $this->percentile($responseTimes, 95),
                'p99_response_time_ms' => $this->percentile($responseTimes, 99),
                'min_response_time_ms' => $responseTimes->min() ?? 0,
                'max_response_time_ms' => $responseTimes->max() ?? 0,
            ]
        );
    }

    /**
     * Calculate percentile from a sorted collection.
     */
    protected function percentile($sorted, int $percentile): float
    {
        $count = $sorted->count();
        if ($count === 0) {
            return 0;
        }

        $index = (int) ceil($count * $percentile / 100) - 1;

        return (float) $sorted->values()[$index];
    }
}
