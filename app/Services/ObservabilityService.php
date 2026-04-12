<?php

namespace App\Services;

use App\Models\ErrorLog;
use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use App\Models\SlowQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Observability Service
 *
 * Central service for all dashboard data and metrics queries.
 */
class ObservabilityService
{
    /**
     * Get dashboard summary.
     */
    public function getDashboard(?string $project = null): array
    {
        $now = now();
        $oneHourAgo = $now->copy()->subHour();
        $oneDayAgo = $now->copy()->subDay();

        $requestStats = RequestMetric::where('created_at', '>=', $oneDayAgo)
            ->when($project, fn ($q) => $q->where('project', $project))
            ->selectRaw("COUNT(*) as total_24h")
            ->selectRaw("SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as total_1h", [$oneHourAgo])
            ->selectRaw("SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors_24h")
            ->selectRaw("SUM(CASE WHEN status_code >= 400 AND created_at >= ? THEN 1 ELSE 0 END) as errors_1h", [$oneHourAgo])
            ->selectRaw("AVG(CASE WHEN created_at >= ? THEN response_time_ms END) as avg_time_1h", [$oneHourAgo])
            ->first();

        $requestsLastHour = (int) $requestStats->total_1h;
        $requestsLast24h = (int) $requestStats->total_24h;
        $errorsLastHour = (int) $requestStats->errors_1h;
        $errorsLast24h = (int) $requestStats->errors_24h;
        $avgResponseTime = $requestStats->avg_time_1h;

        $slowestEndpoints = RequestMetric::where('created_at', '>=', $oneHourAgo)
            ->when($project, fn ($q) => $q->where('project', $project))
            ->select('path')
            ->selectRaw('AVG(response_time_ms) as avg_time')
            ->selectRaw('COUNT(*) as request_count')
            ->groupBy('path')
            ->orderByDesc('avg_time')
            ->limit(5)
            ->get();

        $errorStats = ErrorLog::where('created_at', '>=', $oneDayAgo)
            ->when($project, fn ($q) => $q->where('project', $project))
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical")
            ->first();

        $recentErrorCount = (int) $errorStats->total;
        $criticalErrors = (int) $errorStats->critical;

        $slowQueryCount = SlowQuery::where('created_at', '>=', $oneDayAgo)
            ->when($project, fn ($q) => $q->where('project', $project))
            ->count();

        return [
            'requests' => [
                'last_hour' => $requestsLastHour,
                'last_24h' => $requestsLast24h,
                'error_rate_1h' => $requestsLastHour > 0
                    ? round($errorsLastHour / $requestsLastHour * 100, 2)
                    : 0,
                'error_rate_24h' => $requestsLast24h > 0
                    ? round($errorsLast24h / $requestsLast24h * 100, 2)
                    : 0,
            ],
            'performance' => [
                'avg_response_time_ms' => round($avgResponseTime ?? 0),
            ],
            'slowest_endpoints' => $slowestEndpoints,
            'errors' => [
                'last_24h' => $recentErrorCount,
                'critical' => $criticalErrors,
            ],
            'slow_queries' => [
                'last_24h' => $slowQueryCount,
            ],
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Get paginated request metrics.
     */
    public function getRequests(array $filters = []): LengthAwarePaginator
    {
        $query = RequestMetric::query()->orderByDesc('created_at');

        if (! empty($filters['project'])) {
            $query->forProject($filters['project']);
        }
        if (! empty($filters['path'])) {
            $query->where('path', 'like', '%' . $filters['path'] . '%');
        }
        if (! empty($filters['status_code'])) {
            $query->where('status_code', $filters['status_code']);
        }
        if (! empty($filters['tenant'])) {
            $query->forTenant($filters['tenant']);
        }
        if (! empty($filters['method'])) {
            $query->where('method', strtoupper($filters['method']));
        }
        if (! empty($filters['errors_only'])) {
            $query->errors();
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get paginated error logs.
     */
    public function getErrors(array $filters = []): LengthAwarePaginator
    {
        $query = ErrorLog::query()->orderByDesc('last_occurred_at');

        if (! empty($filters['project'])) {
            $query->where('project', $filters['project']);
        }
        if (! empty($filters['severity'])) {
            $query->forSeverity($filters['severity']);
        }
        if (! empty($filters['exception_class'])) {
            $query->where('exception_class', 'like', '%' . $filters['exception_class'] . '%');
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get paginated slow queries.
     */
    public function getSlowQueries(array $filters = []): LengthAwarePaginator
    {
        $query = SlowQuery::query()->orderByDesc('created_at');

        if (! empty($filters['project'])) {
            $query->where('project', $filters['project']);
        }
        if (! empty($filters['min_time'])) {
            $query->slowerThan((float) $filters['min_time']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get system health information.
     */
    public function getSystemHealth(): array
    {
        // Disk usage
        $diskFree = disk_free_space(base_path());
        $diskTotal = disk_total_space(base_path());

        // Database size
        $dbSize = $this->getDatabaseSize();

        // Table sizes for observability tables
        $tableSizes = $this->getObservabilityTableSizes();

        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'disk' => [
                'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
                'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
                'used_percent' => round((1 - $diskFree / $diskTotal) * 100, 1),
            ],
            'database' => [
                'size_mb' => $dbSize,
                'observability_tables' => $tableSizes,
            ],
            'memory' => [
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get aggregated metrics for endpoints.
     */
    public function getMetrics(string $period = 'hourly', ?string $path = null, int $limit = 48): array
    {
        $query = MetricsAggregated::where('period', $period)
            ->orderByDesc('period_start')
            ->limit($limit);

        if ($path) {
            $query->forPath($path);
        } else {
            $query->global();
        }

        return $query->get()->toArray();
    }

    /**
     * Get database size in MB.
     */
    protected function getDatabaseSize(): float
    {
        try {
            $dbName = config('database.connections.' . config('database.default') . '.database');

            if (config('database.default') === 'sqlite') {
                $path = $dbName;
                if (file_exists($path)) {
                    return round(filesize($path) / 1024 / 1024, 2);
                }

                return 0;
            }

            $result = DB::selectOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.TABLES
                 WHERE table_schema = ?",
                [$dbName]
            );

            return (float) ($result->size_mb ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get row counts for observability tables.
     */
    protected function getObservabilityTableSizes(): array
    {
        try {
            return [
                'request_metrics' => RequestMetric::count(),
                'error_logs' => ErrorLog::count(),
                'slow_queries' => SlowQuery::count(),
                'metrics_aggregated' => MetricsAggregated::count(),
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
