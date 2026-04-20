<?php

namespace Tests\Unit\Services;

use App\Models\ErrorLog;
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
}
