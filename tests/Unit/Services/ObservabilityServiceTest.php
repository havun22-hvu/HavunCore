<?php

namespace Tests\Unit\Services;

use App\Models\ErrorLog;
use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use App\Models\SlowQuery;
use App\Services\ObservabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor ObservabilityService — dashboard-aggregatie en
 * project-filtering. Toegevoegd 2026-04-20 om HavunCore Unit-coverage
 * richting 80 % te tillen.
 */
class ObservabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_zero_stats_when_no_metrics(): void
    {
        $dashboard = (new ObservabilityService())->getDashboard();

        $this->assertSame(0, $dashboard['requests']['last_hour']);
        $this->assertSame(0, $dashboard['requests']['last_24h']);
        $this->assertSame(0, $dashboard['requests']['error_rate_1h']);
        $this->assertSame(0, $dashboard['errors']['last_24h']);
        $this->assertSame(0, $dashboard['slow_queries']['last_24h']);
        $this->assertArrayHasKey('generated_at', $dashboard);
    }

    public function test_dashboard_counts_requests_in_last_24h_window(): void
    {
        RequestMetric::create([
            'project' => 'havuncore',
            'method' => 'GET',
            'path' => '/api/x',
            'status_code' => 200,
            'response_time_ms' => 50,
            'created_at' => now()->subMinutes(30),
        ]);
        RequestMetric::create([
            'project' => 'havuncore',
            'method' => 'GET',
            'path' => '/api/y',
            'status_code' => 500,
            'response_time_ms' => 100,
            'created_at' => now()->subMinutes(10),
        ]);

        $dashboard = (new ObservabilityService())->getDashboard();

        $this->assertSame(2, $dashboard['requests']['last_24h']);
        $this->assertSame(2, $dashboard['requests']['last_hour']);
        $this->assertEqualsWithDelta(50.0, $dashboard['requests']['error_rate_1h'], 0.01);
    }

    public function test_dashboard_filter_by_project_isolates_metrics(): void
    {
        RequestMetric::create([
            'project' => 'havuncore',
            'method' => 'GET', 'path' => '/a', 'status_code' => 200,
            'response_time_ms' => 10, 'created_at' => now()->subMinutes(5),
        ]);
        RequestMetric::create([
            'project' => 'judotoernooi',
            'method' => 'GET', 'path' => '/b', 'status_code' => 200,
            'response_time_ms' => 10, 'created_at' => now()->subMinutes(5),
        ]);

        $hcDash = (new ObservabilityService())->getDashboard('havuncore');
        $jtDash = (new ObservabilityService())->getDashboard('judotoernooi');

        $this->assertSame(1, $hcDash['requests']['last_24h']);
        $this->assertSame(1, $jtDash['requests']['last_24h']);
    }

    public function test_dashboard_counts_critical_errors_separately(): void
    {
        ErrorLog::create([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'critical thing',
            'file' => '/x', 'line' => 1,
            'severity' => 'critical',
            'fingerprint' => 'a-' . uniqid(),
            'last_occurred_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);
        ErrorLog::create([
            'project' => 'havuncore',
            'exception_class' => 'Notice',
            'message' => 'small thing',
            'file' => '/y', 'line' => 2,
            'severity' => 'warning',
            'fingerprint' => 'b-' . uniqid(),
            'last_occurred_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);

        $dashboard = (new ObservabilityService())->getDashboard();

        $this->assertSame(2, $dashboard['errors']['last_24h']);
        $this->assertSame(1, $dashboard['errors']['critical']);
    }

    public function test_dashboard_counts_slow_queries_in_24h(): void
    {
        SlowQuery::create([
            'project' => 'havuncore',
            'query' => 'SELECT * FROM x',
            'time_ms' => 1500,
            'connection' => 'mysql',
            'created_at' => now()->subHours(2),
        ]);

        $this->assertSame(1, (new ObservabilityService())->getDashboard()['slow_queries']['last_24h']);
    }

    public function test_quality_findings_returns_null_when_no_scans(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->assertNull((new ObservabilityService())->getQualityFindings());
    }

    public function test_quality_findings_reads_latest_scan_and_filters_high_critical(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $today = now()->toDateString();

        $disk->put("qv-scans/2025-01-01/run-older.json", json_encode([
            'findings' => [['severity' => 'critical', 'project' => 'x', 'check' => 'old']],
            'totals' => ['critical' => 1, 'high' => 0, 'errors' => 0],
        ]));
        $disk->put("qv-scans/{$today}/run-newer.json", json_encode([
            'findings' => [
                ['severity' => 'critical', 'project' => 'jt', 'check' => 'observatory', 'title' => 'grade F'],
                ['severity' => 'high', 'project' => 'hp', 'check' => 'forms', 'title' => '52%'],
                ['severity' => 'medium', 'project' => 'hp', 'check' => 'x', 'title' => 'ignored'],
            ],
            'totals' => ['critical' => 1, 'high' => 1, 'errors' => 0],
        ]));

        $result = (new ObservabilityService())->getQualityFindings();

        $this->assertIsArray($result);
        $this->assertSame(1, $result['totals']['critical']);
        $this->assertSame(1, $result['totals']['high']);
        $this->assertCount(2, $result['findings']);
        $severities = array_column($result['findings'], 'severity');
        $this->assertSame(['critical', 'high'], $severities);
    }

    /**
     * Kills Coalesce + Increment/Decrement mutations on `?? 50` default
     * for getRequests/getErrors/getSlowQueries pagination. We create
     * exactly 50 rows and assert a full first page = 50 (the default).
     */
    public function test_get_requests_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            RequestMetric::create([
                'project' => 'havuncore',
                'method' => 'GET',
                'path' => '/r/' . $i,
                'status_code' => 200,
                'response_time_ms' => 5,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = (new ObservabilityService())->getRequests();

        $this->assertSame(50, $page->perPage(), 'default per_page MUST be 50');
        $this->assertCount(50, $page->items());
        $this->assertSame(51, $page->total());
    }

    public function test_get_requests_honors_explicit_per_page_filter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RequestMetric::create([
                'project' => 'havuncore',
                'method' => 'GET',
                'path' => '/p/' . $i,
                'status_code' => 200,
                'response_time_ms' => 5,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = (new ObservabilityService())->getRequests(['per_page' => 3]);

        $this->assertSame(3, $page->perPage());
        $this->assertCount(3, $page->items());
    }

    public function test_get_requests_filters_path_substring(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/api/ai/chat',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/api/vault',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getRequests(['path' => 'ai']);

        $this->assertSame(1, $page->total());
        $this->assertSame('/api/ai/chat', $page->items()[0]->path);
    }

    public function test_get_requests_filter_status_code_matches_exact(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/a',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/b',
            'status_code' => 500, 'response_time_ms' => 1, 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getRequests(['status_code' => 500]);

        $this->assertSame(1, $page->total());
        $this->assertSame(500, $page->items()[0]->status_code);
    }

    public function test_get_requests_filter_tenant_via_scope(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'tenant' => 'infosyst', 'method' => 'GET',
            'path' => '/a', 'status_code' => 200, 'response_time_ms' => 1,
            'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'tenant' => 'havuncore', 'method' => 'GET',
            'path' => '/b', 'status_code' => 200, 'response_time_ms' => 1,
            'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getRequests(['tenant' => 'infosyst']);

        $this->assertSame(1, $page->total());
        $this->assertSame('infosyst', $page->items()[0]->tenant);
    }

    public function test_get_requests_method_filter_is_uppercased(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'method' => 'POST', 'path' => '/a',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/b',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getRequests(['method' => 'post']);

        $this->assertSame(1, $page->total(), 'strtoupper must normalize lowercase input');
        $this->assertSame('POST', $page->items()[0]->method);
    }

    public function test_get_requests_errors_only_keeps_4xx_and_5xx(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/ok',
            'status_code' => 200, 'response_time_ms' => 1, 'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/404',
            'status_code' => 404, 'response_time_ms' => 1, 'created_at' => now(),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/500',
            'status_code' => 500, 'response_time_ms' => 1, 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getRequests(['errors_only' => true]);

        $this->assertSame(2, $page->total());
        $statuses = array_map(fn ($r) => $r->status_code, $page->items());
        sort($statuses);
        $this->assertSame([404, 500], $statuses);
    }

    /**
     * Kills Coalesce + Int increment/decrement on getErrors `?? 50`.
     * Also kills `exception_class like '%...%'` filter mutations.
     */
    public function test_get_errors_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            ErrorLog::create([
                'project' => 'havuncore',
                'exception_class' => 'E' . $i,
                'message' => 'm',
                'file' => '/x', 'line' => 1,
                'severity' => 'warning',
                'fingerprint' => 'fp-' . $i,
                'last_occurred_at' => now()->subMinutes($i),
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = (new ObservabilityService())->getErrors();

        $this->assertSame(50, $page->perPage());
        $this->assertCount(50, $page->items());
    }

    public function test_get_errors_filters_project_and_severity_and_exception_substring(): void
    {
        ErrorLog::create([
            'project' => 'havuncore',
            'exception_class' => 'Illuminate\\Database\\QueryException',
            'message' => 'sql', 'file' => '/x', 'line' => 1,
            'severity' => 'critical',
            'fingerprint' => 'q-' . uniqid(),
            'last_occurred_at' => now(), 'created_at' => now(),
        ]);
        ErrorLog::create([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'other', 'file' => '/y', 'line' => 2,
            'severity' => 'warning',
            'fingerprint' => 'r-' . uniqid(),
            'last_occurred_at' => now(), 'created_at' => now(),
        ]);
        ErrorLog::create([
            'project' => 'judotoernooi',
            'exception_class' => 'Illuminate\\Database\\QueryException',
            'message' => 'other-proj', 'file' => '/z', 'line' => 3,
            'severity' => 'critical',
            'fingerprint' => 'z-' . uniqid(),
            'last_occurred_at' => now(), 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getErrors([
            'project' => 'havuncore',
            'severity' => 'critical',
            'exception_class' => 'QueryException',
        ]);

        $this->assertSame(1, $page->total());
        $row = $page->items()[0];
        $this->assertSame('havuncore', $row->project);
        $this->assertSame('critical', $row->severity);
        $this->assertStringContainsString('QueryException', $row->exception_class);
    }

    /**
     * Kills Coalesce + Int mutations on getSlowQueries `?? 50`, and
     * CastFloat mutation on slowerThan((float) $min_time) at line 203.
     */
    public function test_get_slow_queries_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            SlowQuery::create([
                'project' => 'havuncore',
                'query' => 'SELECT ' . $i,
                'time_ms' => 100 + $i,
                'connection' => 'mysql',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = (new ObservabilityService())->getSlowQueries();

        $this->assertSame(50, $page->perPage());
        $this->assertCount(50, $page->items());
    }

    public function test_get_slow_queries_min_time_cast_to_float_and_filters(): void
    {
        SlowQuery::create([
            'project' => 'hc', 'query' => 'fast', 'time_ms' => 100,
            'connection' => 'mysql', 'created_at' => now(),
        ]);
        SlowQuery::create([
            'project' => 'hc', 'query' => 'slow', 'time_ms' => 500,
            'connection' => 'mysql', 'created_at' => now(),
        ]);

        // String input must be cast to float for the slowerThan scope.
        $page = (new ObservabilityService())->getSlowQueries(['min_time' => '250']);

        $this->assertSame(1, $page->total());
        $this->assertSame('slow', $page->items()[0]->query);
    }

    public function test_get_slow_queries_filter_by_project(): void
    {
        SlowQuery::create([
            'project' => 'havuncore', 'query' => 'a', 'time_ms' => 1000,
            'connection' => 'mysql', 'created_at' => now(),
        ]);
        SlowQuery::create([
            'project' => 'judotoernooi', 'query' => 'b', 'time_ms' => 1000,
            'connection' => 'mysql', 'created_at' => now(),
        ]);

        $page = (new ObservabilityService())->getSlowQueries(['project' => 'havuncore']);

        $this->assertSame(1, $page->total());
        $this->assertSame('havuncore', $page->items()[0]->project);
    }

    /**
     * Kills Int mutations on `int $limit = 48` default + MethodCallRemoval
     * on `$query->global()` when no path is given.
     */
    public function test_get_metrics_default_limit_is_48_and_uses_global_scope(): void
    {
        // 50 global (path=null) rows, oldest first by period_start.
        for ($i = 0; $i < 50; $i++) {
            MetricsAggregated::create([
                'project' => 'havuncore',
                'period' => 'hourly',
                'period_start' => now()->subHours($i),
                'path' => null,
                'request_count' => 1,
                'error_count' => 0,
                'server_error_count' => 0,
                'avg_response_time_ms' => 10,
                'p95_response_time_ms' => 10,
                'p99_response_time_ms' => 10,
                'min_response_time_ms' => 10,
                'max_response_time_ms' => 10,
            ]);
        }
        // Path-scoped row that MUST be excluded by `->global()`.
        MetricsAggregated::create([
            'project' => 'havuncore',
            'period' => 'hourly',
            'period_start' => now(),
            'path' => '/api/ai/chat',
            'request_count' => 99,
            'error_count' => 0,
            'server_error_count' => 0,
            'avg_response_time_ms' => 10,
            'p95_response_time_ms' => 10,
            'p99_response_time_ms' => 10,
            'min_response_time_ms' => 10,
            'max_response_time_ms' => 10,
        ]);

        $rows = (new ObservabilityService())->getMetrics();

        $this->assertCount(48, $rows, 'default limit MUST be exactly 48');
        // No path-scoped row should appear.
        foreach ($rows as $row) {
            $this->assertNull($row['path']);
        }
    }

    public function test_get_metrics_forPath_excludes_global_rows(): void
    {
        MetricsAggregated::create([
            'project' => 'hc', 'period' => 'hourly',
            'period_start' => now(), 'path' => null,
            'request_count' => 1, 'error_count' => 0, 'server_error_count' => 0,
            'avg_response_time_ms' => 1, 'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1, 'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ]);
        MetricsAggregated::create([
            'project' => 'hc', 'period' => 'hourly',
            'period_start' => now(), 'path' => '/x',
            'request_count' => 1, 'error_count' => 0, 'server_error_count' => 0,
            'avg_response_time_ms' => 1, 'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1, 'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ]);

        $rows = (new ObservabilityService())->getMetrics('hourly', '/x');

        $this->assertCount(1, $rows);
        $this->assertSame('/x', $rows[0]['path']);
    }

    public function test_get_metrics_period_filter_isolates_hourly_vs_daily(): void
    {
        MetricsAggregated::create([
            'project' => 'hc', 'period' => 'hourly',
            'period_start' => now(), 'path' => null,
            'request_count' => 1, 'error_count' => 0, 'server_error_count' => 0,
            'avg_response_time_ms' => 1, 'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1, 'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ]);
        MetricsAggregated::create([
            'project' => 'hc', 'period' => 'daily',
            'period_start' => now(), 'path' => null,
            'request_count' => 1, 'error_count' => 0, 'server_error_count' => 0,
            'avg_response_time_ms' => 1, 'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1, 'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ]);

        $rows = (new ObservabilityService())->getMetrics('daily');

        $this->assertCount(1, $rows);
        $this->assertSame('daily', $rows[0]['period']);
    }

    /**
     * Kills system-health shape mutations. Disk/memory math uses real
     * system values, so we assert ratio-invariants that survive on any
     * machine (free <= total, used_percent 0..100, exact 2 decimals).
     */
    public function test_system_health_disk_bytes_are_consistent_in_gigabytes(): void
    {
        $health = (new ObservabilityService())->getSystemHealth();

        $this->assertIsArray($health['disk']);
        $this->assertArrayHasKey('free_gb', $health['disk']);
        $this->assertArrayHasKey('total_gb', $health['disk']);
        $this->assertArrayHasKey('used_percent', $health['disk']);

        // Values must be strictly positive floats (kills division-swap
        // mutations that yield 0 / negative / gigantic ratios).
        $this->assertGreaterThan(0.0, $health['disk']['free_gb']);
        $this->assertGreaterThan(0.0, $health['disk']['total_gb']);
        // Physical invariant: free cannot exceed total.
        $this->assertLessThanOrEqual(
            $health['disk']['total_gb'],
            $health['disk']['free_gb'],
        );
        // used_percent must sit in [0, 100] (kills * 101 / * 99 /
        // reversed-order mutations).
        $this->assertGreaterThanOrEqual(0.0, $health['disk']['used_percent']);
        $this->assertLessThanOrEqual(100.0, $health['disk']['used_percent']);

        // GB conversion sanity: a modern disk is always < 10^6 GB (1 EB);
        // multiplication-swap (e.g. `* 1024` instead of `/ 1024`) would
        // push values way above this ceiling.
        $this->assertLessThan(1_000_000.0, $health['disk']['total_gb']);
        $this->assertLessThan(1_000_000.0, $health['disk']['free_gb']);
    }

    public function test_system_health_disk_values_rounded_to_2_decimals(): void
    {
        $health = (new ObservabilityService())->getSystemHealth();

        // Kills IncrementInteger on `round(..., 2)` -> `round(..., 3)`:
        // precision argument must be exactly 2 for free_gb / total_gb.
        foreach (['free_gb', 'total_gb'] as $key) {
            $value = $health['disk'][$key];
            $this->assertSame(
                round($value, 2),
                $value,
                "disk.{$key} must be rounded to 2 decimals"
            );
        }

        // used_percent uses precision = 1 (different slot).
        $pct = $health['disk']['used_percent'];
        $this->assertSame(round($pct, 1), $pct, 'used_percent must be rounded to 1 decimal');
    }

    public function test_system_health_memory_values_are_positive_megabytes(): void
    {
        $health = (new ObservabilityService())->getSystemHealth();

        $this->assertGreaterThan(0.0, $health['memory']['current_mb']);
        $this->assertGreaterThan(0.0, $health['memory']['peak_mb']);
        // Peak usage must be >= current usage (memory_get_peak_usage
        // semantic). Kills division-swap that flips the ratio.
        $this->assertGreaterThanOrEqual(
            $health['memory']['current_mb'],
            $health['memory']['peak_mb'],
        );
        // PHP memory is always < 100 GB in a test run; catches
        // multiplication-swap mutations that blow the value up.
        $this->assertLessThan(100_000.0, $health['memory']['current_mb']);
        $this->assertLessThan(100_000.0, $health['memory']['peak_mb']);

        // Precision = 2 decimals.
        $this->assertSame(
            round($health['memory']['current_mb'], 2),
            $health['memory']['current_mb'],
        );
        $this->assertSame(
            round($health['memory']['peak_mb'], 2),
            $health['memory']['peak_mb'],
        );
    }

    public function test_system_health_contains_php_and_laravel_version_and_env(): void
    {
        $health = (new ObservabilityService())->getSystemHealth();

        $this->assertSame(PHP_VERSION, $health['php_version']);
        $this->assertSame(app()->version(), $health['laravel_version']);
        $this->assertSame(app()->environment(), $health['environment']);
        $this->assertArrayHasKey('checked_at', $health);
    }

    /**
     * Fixture-based test for the sqlite branch of getDatabaseSize()
     * (lines 271-277): we point the connection to a temp file with a
     * known byte-count and assert the rounded-MB return is correct.
     *
     * Kills: Identical (=== 'sqlite' flip), IfNegation on file_exists,
     * Concat mutations on the key-builder, and the 0-default return
     * when the file is absent.
     */
    public function test_database_size_returns_file_size_in_mb_for_sqlite(): void
    {
        // getDatabaseSize() reads `database.default` (expected 'sqlite')
        // and then `database.connections.sqlite.database`. We only
        // swap the `.database` path for the duration of this test so
        // the `=== 'sqlite'` branch is exercised with a controlled file.
        $originalPath = config('database.connections.sqlite.database');

        $tmp = tempnam(sys_get_temp_dir(), 'obs-db-');
        // Write exactly 2 MB so the expected rounded return is 2.0.
        file_put_contents($tmp, str_repeat('x', 2 * 1024 * 1024));

        config()->set('database.connections.sqlite.database', $tmp);

        try {
            $svc = new class extends ObservabilityService {
                public function callDbSize(): float
                {
                    return $this->getDatabaseSize();
                }
            };

            $this->assertSame(2.0, $svc->callDbSize());
        } finally {
            config()->set('database.connections.sqlite.database', $originalPath);
            @unlink($tmp);
        }
    }

    public function test_database_size_returns_zero_when_sqlite_file_missing(): void
    {
        $originalPath = config('database.connections.sqlite.database');
        config()->set(
            'database.connections.sqlite.database',
            '/definitely/does/not/exist.sqlite',
        );

        try {
            $svc = new class extends ObservabilityService {
                public function callDbSize(): float
                {
                    return $this->getDatabaseSize();
                }
            };

            // Strict zero: kills Increment (-> 1), Decrement (-> -1)
            // and ReturnRemoval (-> void) mutations on line 277.
            $this->assertSame(0.0, $svc->callDbSize());
        } finally {
            config()->set('database.connections.sqlite.database', $originalPath);
        }
    }

    public function test_database_size_non_sqlite_fallback_returns_zero_on_failure(): void
    {
        // Simulate a non-sqlite driver where the information_schema
        // SELECT throws. We use sqlite but swap the default name to
        // something that has no live connection configured, triggering
        // the try/catch path without breaking the RefreshDatabase
        // transaction of the real sqlite test connection.
        $original = config('database.default');
        config()->set('database.default', 'mysql-nope');
        config()->set('database.connections.mysql-nope', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nope',
            'username' => 'nope',
            'password' => 'nope',
        ]);

        try {
            $svc = new class extends ObservabilityService {
                public function callDbSize(): float
                {
                    return $this->getDatabaseSize();
                }
            };

            $this->assertSame(0.0, $svc->callDbSize());
        } finally {
            config()->set('database.default', $original);
        }
    }

    /**
     * Kills ArrayItemRemoval on getObservabilityTableSizes() — each of
     * the four keys must be present in the returned array.
     * Also kills ProtectedVisibility by reaching the method via subclass.
     */
    public function test_observability_table_sizes_contain_each_documented_key(): void
    {
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/',
            'status_code' => 200, 'response_time_ms' => 1,
            'created_at' => now(),
        ]);
        ErrorLog::create([
            'project' => 'hc', 'exception_class' => 'E', 'message' => 'm',
            'file' => '/x', 'line' => 1, 'severity' => 'warning',
            'fingerprint' => 'fp-' . uniqid(),
            'last_occurred_at' => now(), 'created_at' => now(),
        ]);
        SlowQuery::create([
            'project' => 'hc', 'query' => 'q', 'time_ms' => 1,
            'connection' => 'mysql', 'created_at' => now(),
        ]);
        MetricsAggregated::create([
            'project' => 'hc', 'period' => 'hourly', 'period_start' => now(),
            'path' => null,
            'request_count' => 1, 'error_count' => 0, 'server_error_count' => 0,
            'avg_response_time_ms' => 1, 'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1, 'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ]);

        $svc = new class extends ObservabilityService {
            public function callTableSizes(): array
            {
                return $this->getObservabilityTableSizes();
            }
        };

        $sizes = $svc->callTableSizes();

        // Per-key === 1 assertions kill ArrayItemRemoval for ANY of
        // the four rows (removing a key makes that assertion fail).
        $this->assertArrayHasKey('request_metrics', $sizes);
        $this->assertArrayHasKey('error_logs', $sizes);
        $this->assertArrayHasKey('slow_queries', $sizes);
        $this->assertArrayHasKey('metrics_aggregated', $sizes);
        $this->assertSame(1, $sizes['request_metrics']);
        $this->assertSame(1, $sizes['error_logs']);
        $this->assertSame(1, $sizes['slow_queries']);
        $this->assertSame(1, $sizes['metrics_aggregated']);
    }

    public function test_dashboard_slowest_endpoints_groups_by_path_with_avg(): void
    {
        // /fast: avg 10ms; /slow: avg 500ms. Must be ordered slow -> fast.
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/fast',
            'status_code' => 200, 'response_time_ms' => 10,
            'created_at' => now()->subMinutes(5),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/fast',
            'status_code' => 200, 'response_time_ms' => 10,
            'created_at' => now()->subMinutes(10),
        ]);
        RequestMetric::create([
            'project' => 'hc', 'method' => 'GET', 'path' => '/slow',
            'status_code' => 200, 'response_time_ms' => 500,
            'created_at' => now()->subMinutes(5),
        ]);

        $dashboard = (new ObservabilityService())->getDashboard();

        $paths = collect($dashboard['slowest_endpoints'])->pluck('path')->all();
        $this->assertSame(['/slow', '/fast'], $paths);
    }
}
