<?php

namespace App\Console\Commands;

use App\Models\RequestMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceBaselineCommand extends Command
{
    protected $signature = 'observability:baseline';

    protected $description = 'Generate daily performance baseline and compare with previous day';

    public function handle(): int
    {
        $today = now()->subDay()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $todayStats = $this->getStats($today, $today->copy()->endOfDay());
        $yesterdayStats = $this->getStats($yesterday, $yesterday->copy()->endOfDay());

        $this->info("Performance Baseline — {$today->toDateString()}");
        $this->newLine();

        $report = [];

        foreach ($todayStats as $project => $stats) {
            $prev = $yesterdayStats[$project] ?? null;
            $this->info("=== {$project} ===");

            $lines = [];
            $lines[] = $this->compareStat('Requests', $stats['count'], $prev['count'] ?? 0);
            $lines[] = $this->compareStat('Avg (ms)', $stats['avg'], $prev['avg'] ?? 0, true);
            $lines[] = $this->compareStat('P95 (ms)', $stats['p95'], $prev['p95'] ?? 0, true);
            $lines[] = $this->compareStat('Errors', $stats['errors'], $prev['errors'] ?? 0, true);
            $lines[] = $this->compareStat('Error %', $stats['error_rate'], $prev['error_rate'] ?? 0, true);

            foreach ($lines as $line) {
                $this->line("  {$line}");
            }

            $report[$project] = [
                'stats' => $stats,
                'previous' => $prev,
                'lines' => $lines,
            ];

            $this->newLine();
        }

        // Store baseline in cache for API access
        cache()->put('performance_baseline:' . $today->toDateString(), $report, now()->addDays(30));

        // Alert on significant regression (p95 > 2x previous)
        $regressions = [];
        foreach ($todayStats as $project => $stats) {
            $prevP95 = $yesterdayStats[$project]['p95'] ?? 0;
            if ($prevP95 > 0 && $stats['p95'] > $prevP95 * 2) {
                $regressions[] = "{$project}: p95 {$prevP95}ms → {$stats['p95']}ms";
            }
        }

        if (! empty($regressions)) {
            $this->warn('Performance regressions detected:');
            foreach ($regressions as $r) {
                $this->warn("  - {$r}");
            }
            $this->sendRegressionAlert($today, $regressions);
        }

        return self::SUCCESS;
    }

    protected function getStats($from, $to): array
    {
        $metrics = RequestMetric::whereBetween('created_at', [$from, $to])
            ->select('project')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('AVG(response_time_ms) as avg_ms')
            ->selectRaw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->groupBy('project')
            ->get();

        $result = [];
        foreach ($metrics as $m) {
            // Calculate p95 per project
            $p95 = RequestMetric::whereBetween('created_at', [$from, $to])
                ->where('project', $m->project)
                ->orderBy('response_time_ms')
                ->offset((int) floor($m->cnt * 0.95))
                ->limit(1)
                ->value('response_time_ms') ?? 0;

            $result[$m->project] = [
                'count' => (int) $m->cnt,
                'avg' => round((float) $m->avg_ms, 1),
                'p95' => (int) $p95,
                'errors' => (int) $m->errors,
                'error_rate' => $m->cnt > 0 ? round($m->errors / $m->cnt * 100, 2) : 0,
            ];
        }

        return $result;
    }

    protected function compareStat(string $label, $current, $previous, bool $lowerIsBetter = false): string
    {
        $diff = $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : 0;
        $arrow = '';

        if ($diff > 0) {
            $arrow = $lowerIsBetter ? ' ▲ WORSE' : ' ▲';
        } elseif ($diff < 0) {
            $arrow = $lowerIsBetter ? ' ▼ BETTER' : ' ▼';
        }

        return sprintf('%-12s %8s (was: %s, %+.1f%%%s)', $label, $current, $previous, $diff, $arrow);
    }

    protected function sendRegressionAlert(mixed $date, array $regressions): void
    {
        // Regressions are visible in HavunAdmin dashboard via /api/observability/baseline
        Log::warning('Performance regression detected', [
            'date' => $date->toDateString(),
            'regressions' => $regressions,
        ]);
    }
}
