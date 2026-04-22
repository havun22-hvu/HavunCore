<?php

namespace Tests\Unit\Services;

use App\Models\ErrorLog;
use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use App\Models\SlowQuery;
use App\Services\ObservabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage voor ObservabilityService — dashboard-aggregatie en
 * project-filtering. Toegevoegd 2026-04-20 om HavunCore Unit-coverage
 * richting 80 % te tillen.
 */
class ObservabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ObservabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ObservabilityService();
    }

    /**
     * Create a RequestMetric with sane defaults. Overrides win.
     */
    private function makeRequest(array $overrides = []): RequestMetric
    {
        return RequestMetric::create(array_merge([
            'project' => 'hc',
            'method' => 'GET',
            'path' => '/x',
            'status_code' => 200,
            'response_time_ms' => 1,
            'created_at' => now(),
        ], $overrides));
    }

    private function makeError(array $overrides = []): ErrorLog
    {
        return ErrorLog::create(array_merge([
            'project' => 'hc',
            'exception_class' => 'E',
            'message' => 'm',
            'file' => '/x',
            'line' => 1,
            'severity' => 'warning',
            'fingerprint' => 'fp-' . uniqid('', true),
            'last_occurred_at' => now(),
            'created_at' => now(),
        ], $overrides));
    }

    private function makeSlowQuery(array $overrides = []): SlowQuery
    {
        return SlowQuery::create(array_merge([
            'project' => 'hc',
            'query' => 'SELECT 1',
            'time_ms' => 100,
            'connection' => 'mysql',
            'created_at' => now(),
        ], $overrides));
    }

    private function makeMetricsRow(array $overrides = []): MetricsAggregated
    {
        return MetricsAggregated::create(array_merge([
            'project' => 'hc',
            'period' => 'hourly',
            'period_start' => now(),
            'path' => null,
            'request_count' => 1,
            'error_count' => 0,
            'server_error_count' => 0,
            'avg_response_time_ms' => 1,
            'p95_response_time_ms' => 1,
            'p99_response_time_ms' => 1,
            'min_response_time_ms' => 1,
            'max_response_time_ms' => 1,
        ], $overrides));
    }

    /**
     * Subclass that exposes the two protected methods under test.
     * Killing ProtectedVisibility mutations requires calling through
     * this subclass, not direct invocation on the base.
     */
    private function exposedService(): ObservabilityService
    {
        return new class extends ObservabilityService {
            public function callDbSize(): float
            {
                return $this->getDatabaseSize();
            }

            public function callTableSizes(): array
            {
                return $this->getObservabilityTableSizes();
            }
        };
    }

    public function test_dashboard_returns_zero_stats_when_no_metrics(): void
    {
        $dashboard = $this->service->getDashboard();

        $this->assertSame(0, $dashboard['requests']['last_hour']);
        $this->assertSame(0, $dashboard['requests']['last_24h']);
        $this->assertSame(0, $dashboard['requests']['error_rate_1h']);
        $this->assertSame(0, $dashboard['errors']['last_24h']);
        $this->assertSame(0, $dashboard['slow_queries']['last_24h']);
        $this->assertArrayHasKey('generated_at', $dashboard);
    }

    public function test_dashboard_counts_requests_in_last_24h_window(): void
    {
        $this->makeRequest([
            'project' => 'havuncore', 'path' => '/api/x',
            'response_time_ms' => 50, 'created_at' => now()->subMinutes(30),
        ]);
        $this->makeRequest([
            'project' => 'havuncore', 'path' => '/api/y',
            'status_code' => 500, 'response_time_ms' => 100,
            'created_at' => now()->subMinutes(10),
        ]);

        $dashboard = $this->service->getDashboard();

        $this->assertSame(2, $dashboard['requests']['last_24h']);
        $this->assertSame(2, $dashboard['requests']['last_hour']);
        $this->assertEqualsWithDelta(50.0, $dashboard['requests']['error_rate_1h'], 0.01);
    }

    public function test_dashboard_filter_by_project_isolates_metrics(): void
    {
        $this->makeRequest(['project' => 'havuncore', 'path' => '/a', 'response_time_ms' => 10, 'created_at' => now()->subMinutes(5)]);
        $this->makeRequest(['project' => 'judotoernooi', 'path' => '/b', 'response_time_ms' => 10, 'created_at' => now()->subMinutes(5)]);

        $hcDash = $this->service->getDashboard('havuncore');
        $jtDash = $this->service->getDashboard('judotoernooi');

        $this->assertSame(1, $hcDash['requests']['last_24h']);
        $this->assertSame(1, $jtDash['requests']['last_24h']);
    }

    public function test_dashboard_counts_critical_errors_separately(): void
    {
        $this->makeError([
            'project' => 'havuncore', 'exception_class' => 'RuntimeException',
            'message' => 'critical thing', 'severity' => 'critical',
            'last_occurred_at' => now()->subHour(), 'created_at' => now()->subHour(),
        ]);
        $this->makeError([
            'project' => 'havuncore', 'exception_class' => 'Notice',
            'message' => 'small thing', 'file' => '/y', 'line' => 2,
            'last_occurred_at' => now()->subHour(), 'created_at' => now()->subHour(),
        ]);

        $dashboard = $this->service->getDashboard();

        $this->assertSame(2, $dashboard['errors']['last_24h']);
        $this->assertSame(1, $dashboard['errors']['critical']);
    }

    public function test_dashboard_counts_slow_queries_in_24h(): void
    {
        $this->makeSlowQuery([
            'project' => 'havuncore', 'query' => 'SELECT * FROM x',
            'time_ms' => 1500, 'created_at' => now()->subHours(2),
        ]);

        $this->assertSame(1, $this->service->getDashboard()['slow_queries']['last_24h']);
    }

    public function test_quality_findings_returns_null_when_no_scans(): void
    {
        Storage::fake('local');

        $this->assertNull($this->service->getQualityFindings());
    }

    public function test_quality_findings_reads_latest_scan_and_filters_high_critical(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
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

        $result = $this->service->getQualityFindings();

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
     * exactly 51 rows and assert a full first page = 50 (the default).
     */
    public function test_get_requests_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->makeRequest(['path' => '/r/' . $i, 'response_time_ms' => 5, 'created_at' => now()->subMinutes($i)]);
        }

        $page = $this->service->getRequests();

        $this->assertSame(50, $page->perPage(), 'default per_page MUST be 50');
        $this->assertCount(50, $page->items());
        $this->assertSame(51, $page->total());
    }

    public function test_get_requests_honors_explicit_per_page_filter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeRequest(['path' => '/p/' . $i, 'response_time_ms' => 5, 'created_at' => now()->subMinutes($i)]);
        }

        $page = $this->service->getRequests(['per_page' => 3]);

        $this->assertSame(3, $page->perPage());
        $this->assertCount(3, $page->items());
    }

    public function test_get_requests_filters_path_substring(): void
    {
        $this->makeRequest(['path' => '/api/ai/chat']);
        $this->makeRequest(['path' => '/api/vault']);

        $page = $this->service->getRequests(['path' => 'ai']);

        $this->assertSame(1, $page->total());
        $this->assertSame('/api/ai/chat', $page->items()[0]->path);
    }

    public function test_get_requests_filter_status_code_matches_exact(): void
    {
        $this->makeRequest(['path' => '/a']);
        $this->makeRequest(['path' => '/b', 'status_code' => 500]);

        $page = $this->service->getRequests(['status_code' => 500]);

        $this->assertSame(1, $page->total());
        $this->assertSame(500, $page->items()[0]->status_code);
    }

    public function test_get_requests_filter_tenant_via_scope(): void
    {
        $this->makeRequest(['path' => '/a', 'tenant' => 'infosyst']);
        $this->makeRequest(['path' => '/b', 'tenant' => 'havuncore']);

        $page = $this->service->getRequests(['tenant' => 'infosyst']);

        $this->assertSame(1, $page->total());
        $this->assertSame('infosyst', $page->items()[0]->tenant);
    }

    public function test_get_requests_method_filter_is_uppercased(): void
    {
        $this->makeRequest(['path' => '/a', 'method' => 'POST']);
        $this->makeRequest(['path' => '/b', 'method' => 'GET']);

        $page = $this->service->getRequests(['method' => 'post']);

        $this->assertSame(1, $page->total(), 'strtoupper must normalize lowercase input');
        $this->assertSame('POST', $page->items()[0]->method);
    }

    public function test_get_requests_errors_only_keeps_4xx_and_5xx(): void
    {
        $this->makeRequest(['path' => '/ok']);
        $this->makeRequest(['path' => '/404', 'status_code' => 404]);
        $this->makeRequest(['path' => '/500', 'status_code' => 500]);

        $page = $this->service->getRequests(['errors_only' => true]);

        $this->assertSame(2, $page->total());
        $statuses = array_map(fn ($r) => $r->status_code, $page->items());
        sort($statuses);
        $this->assertSame([404, 500], $statuses);
    }

    /**
     * Kills Coalesce + Int increment/decrement on getErrors `?? 50`.
     */
    public function test_get_errors_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->makeError([
                'exception_class' => 'E' . $i,
                'fingerprint' => 'fp-' . $i,
                'last_occurred_at' => now()->subMinutes($i),
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = $this->service->getErrors();

        $this->assertSame(50, $page->perPage());
        $this->assertCount(50, $page->items());
    }

    public function test_get_errors_filters_project_and_severity_and_exception_substring(): void
    {
        $this->makeError([
            'project' => 'havuncore', 'exception_class' => 'Illuminate\\Database\\QueryException',
            'message' => 'sql', 'severity' => 'critical',
        ]);
        $this->makeError([
            'project' => 'havuncore', 'exception_class' => 'RuntimeException',
            'message' => 'other', 'file' => '/y', 'line' => 2,
        ]);
        $this->makeError([
            'project' => 'judotoernooi', 'exception_class' => 'Illuminate\\Database\\QueryException',
            'message' => 'other-proj', 'file' => '/z', 'line' => 3, 'severity' => 'critical',
        ]);

        $page = $this->service->getErrors([
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
     * CastFloat mutation on slowerThan((float) $min_time).
     */
    public function test_get_slow_queries_default_per_page_is_exactly_50(): void
    {
        for ($i = 0; $i < 51; $i++) {
            $this->makeSlowQuery([
                'query' => 'SELECT ' . $i,
                'time_ms' => 100 + $i,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $page = $this->service->getSlowQueries();

        $this->assertSame(50, $page->perPage());
        $this->assertCount(50, $page->items());
    }

    public function test_get_slow_queries_min_time_cast_to_float_and_filters(): void
    {
        $this->makeSlowQuery(['query' => 'fast', 'time_ms' => 100]);
        $this->makeSlowQuery(['query' => 'slow', 'time_ms' => 500]);

        // String input must be cast to float for the slowerThan scope.
        $page = $this->service->getSlowQueries(['min_time' => '250']);

        $this->assertSame(1, $page->total());
        $this->assertSame('slow', $page->items()[0]->query);
    }

    public function test_get_slow_queries_filter_by_project(): void
    {
        $this->makeSlowQuery(['project' => 'havuncore', 'query' => 'a', 'time_ms' => 1000]);
        $this->makeSlowQuery(['project' => 'judotoernooi', 'query' => 'b', 'time_ms' => 1000]);

        $page = $this->service->getSlowQueries(['project' => 'havuncore']);

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
            $this->makeMetricsRow([
                'period_start' => now()->subHours($i),
                'avg_response_time_ms' => 10, 'p95_response_time_ms' => 10,
                'p99_response_time_ms' => 10, 'min_response_time_ms' => 10,
                'max_response_time_ms' => 10,
            ]);
        }
        // Path-scoped row that MUST be excluded by `->global()`.
        $this->makeMetricsRow([
            'path' => '/api/ai/chat', 'request_count' => 99,
            'avg_response_time_ms' => 10, 'p95_response_time_ms' => 10,
            'p99_response_time_ms' => 10, 'min_response_time_ms' => 10,
            'max_response_time_ms' => 10,
        ]);

        $rows = $this->service->getMetrics();

        $this->assertCount(48, $rows, 'default limit MUST be exactly 48');
        foreach ($rows as $row) {
            $this->assertNull($row['path']);
        }
    }

    public function test_get_metrics_forPath_excludes_global_rows(): void
    {
        $this->makeMetricsRow();
        $this->makeMetricsRow(['path' => '/x']);

        $rows = $this->service->getMetrics('hourly', '/x');

        $this->assertCount(1, $rows);
        $this->assertSame('/x', $rows[0]['path']);
    }

    public function test_get_metrics_period_filter_isolates_hourly_vs_daily(): void
    {
        $this->makeMetricsRow();
        $this->makeMetricsRow(['period' => 'daily']);

        $rows = $this->service->getMetrics('daily');

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
        $health = $this->service->getSystemHealth();

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
        $health = $this->service->getSystemHealth();

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
        $health = $this->service->getSystemHealth();

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
        $health = $this->service->getSystemHealth();

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
    /**
     * Fixture-based test for the sqlite branch of getDatabaseSize():
     * point the connection to a temp file with a known byte count and
     * assert the rounded-MB return is correct.
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
            $this->assertSame(2.0, $this->exposedService()->callDbSize());
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
            // Strict zero: kills Increment (-> 1), Decrement (-> -1)
            // and ReturnRemoval (-> void) mutations.
            $this->assertSame(0.0, $this->exposedService()->callDbSize());
        } finally {
            config()->set('database.connections.sqlite.database', $originalPath);
        }
    }

    public function test_database_size_non_sqlite_fallback_returns_zero_on_failure(): void
    {
        // Point the default driver at a non-sqlite connection whose
        // information_schema SELECT will throw — the catch returns 0.
        // Uses a non-default name so RefreshDatabase's live sqlite
        // connection remains intact.
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
            $this->assertSame(0.0, $this->exposedService()->callDbSize());
        } finally {
            config()->set('database.default', $original);
        }
    }

    /**
     * Kills ArrayItemRemoval on getObservabilityTableSizes() — each of
     * the four keys must be present in the returned array. Also kills
     * ProtectedVisibility by reaching the method via subclass.
     */
    public function test_observability_table_sizes_contain_each_documented_key(): void
    {
        $this->makeRequest(['path' => '/']);
        $this->makeError();
        $this->makeSlowQuery(['query' => 'q', 'time_ms' => 1]);
        $this->makeMetricsRow();

        $sizes = $this->exposedService()->callTableSizes();

        // Per-key === 1 assertions kill ArrayItemRemoval for ANY of
        // the four rows (removing a key makes that assertion fail).
        $this->assertSame(1, $sizes['request_metrics']);
        $this->assertSame(1, $sizes['error_logs']);
        $this->assertSame(1, $sizes['slow_queries']);
        $this->assertSame(1, $sizes['metrics_aggregated']);
    }

    public function test_dashboard_slowest_endpoints_groups_by_path_with_avg(): void
    {
        // /fast: avg 10ms; /slow: avg 500ms. Must be ordered slow -> fast.
        $this->makeRequest(['path' => '/fast', 'response_time_ms' => 10, 'created_at' => now()->subMinutes(5)]);
        $this->makeRequest(['path' => '/fast', 'response_time_ms' => 10, 'created_at' => now()->subMinutes(10)]);
        $this->makeRequest(['path' => '/slow', 'response_time_ms' => 500, 'created_at' => now()->subMinutes(5)]);

        $dashboard = $this->service->getDashboard();

        $paths = collect($dashboard['slowest_endpoints'])->pluck('path')->all();
        $this->assertSame(['/slow', '/fast'], $paths);
    }

    public function test_dashboard_integer_fields_are_strict_int_type(): void
    {
        $this->makeRequest(['path' => '/a', 'status_code' => 500, 'response_time_ms' => 10, 'created_at' => now()->subMinutes(5)]);
        $this->makeError(['severity' => 'critical', 'last_occurred_at' => now()->subHour(), 'created_at' => now()->subHour()]);
        $this->makeSlowQuery(['time_ms' => 1500, 'created_at' => now()->subHours(2)]);

        $dashboard = $this->service->getDashboard();

        $this->assertIsInt($dashboard['requests']['last_hour']);
        $this->assertIsInt($dashboard['requests']['last_24h']);
        $this->assertIsInt($dashboard['errors']['last_24h']);
        $this->assertIsInt($dashboard['errors']['critical']);
        $this->assertIsInt($dashboard['slow_queries']['last_24h']);
    }

    public function test_dashboard_slowest_endpoints_capped_at_exactly_five(): void
    {
        foreach (['/a', '/b', '/c', '/d', '/e', '/f', '/g'] as $path) {
            $this->makeRequest(['path' => $path, 'response_time_ms' => 100, 'created_at' => now()->subMinutes(5)]);
        }

        $dashboard = $this->service->getDashboard();

        $this->assertCount(5, $dashboard['slowest_endpoints']);
    }

    public function test_dashboard_error_rate_uses_round_not_floor_or_ceil(): void
    {
        // 1/4 = 25.0 exact — kills round/floor/ceil + division mutants in one assertion.
        $this->makeRequest(['path' => '/ok1', 'status_code' => 200, 'response_time_ms' => 10, 'created_at' => now()->subMinutes(10)]);
        $this->makeRequest(['path' => '/ok2', 'status_code' => 200, 'response_time_ms' => 10, 'created_at' => now()->subMinutes(10)]);
        $this->makeRequest(['path' => '/ok3', 'status_code' => 200, 'response_time_ms' => 10, 'created_at' => now()->subMinutes(10)]);
        $this->makeRequest(['path' => '/err', 'status_code' => 500, 'response_time_ms' => 10, 'created_at' => now()->subMinutes(10)]);

        $dashboard = $this->service->getDashboard();

        $this->assertSame(25.0, $dashboard['requests']['error_rate_1h']);
        $this->assertSame(25.0, $dashboard['requests']['error_rate_24h']);
    }

    public function test_quality_findings_totals_are_strict_int_type(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $today = now()->toDateString();
        $disk->put("qv-scans/{$today}/run.json", json_encode([
            'findings' => [],
            'totals' => ['critical' => 3, 'high' => 5, 'errors' => 7],
        ]));

        $result = $this->service->getQualityFindings();

        $this->assertIsInt($result['totals']['critical']);
        $this->assertIsInt($result['totals']['high']);
        $this->assertIsInt($result['totals']['errors']);
    }
}
