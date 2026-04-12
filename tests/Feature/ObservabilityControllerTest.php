<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use App\Models\SlowQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected string $token = 'test-observability-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'observability.enabled' => false, // Disable middleware logging during tests
            'observability.admin_token' => $this->token,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // -- Authorization --

    public function test_endpoints_require_auth_token(): void
    {
        $endpoints = [
            '/api/observability/dashboard',
            '/api/observability/requests',
            '/api/observability/errors',
            '/api/observability/slow-queries',
            '/api/observability/system',
            '/api/observability/metrics',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertStatus(401);
        }
    }

    public function test_endpoints_reject_wrong_token(): void
    {
        $this->getJson('/api/observability/dashboard', [
            'Authorization' => 'Bearer wrong-token',
        ])->assertStatus(401);
    }

    public function test_endpoints_reject_when_no_token_configured(): void
    {
        config(['observability.admin_token' => null]);

        $this->getJson('/api/observability/dashboard', $this->authHeaders())
            ->assertStatus(401);
    }

    // -- Dashboard --

    public function test_dashboard_returns_summary(): void
    {
        RequestMetric::create([
            'method' => 'GET',
            'path' => 'api/ai/chat',
            'status_code' => 200,
            'response_time_ms' => 150,
            'created_at' => now(),
        ]);

        RequestMetric::create([
            'method' => 'POST',
            'path' => 'api/vault/secrets',
            'status_code' => 500,
            'response_time_ms' => 300,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/observability/dashboard', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'requests' => ['last_hour', 'last_24h', 'error_rate_1h', 'error_rate_24h'],
                    'performance' => ['avg_response_time_ms'],
                    'slowest_endpoints',
                    'errors' => ['last_24h', 'critical'],
                    'slow_queries' => ['last_24h'],
                    'generated_at',
                ],
            ]);
    }

    public function test_dashboard_calculates_error_rate(): void
    {
        // 3 requests, 1 error = 33.33% error rate
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 60, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 500, 'response_time_ms' => 100, 'created_at' => now()]);

        $response = $this->getJson('/api/observability/dashboard', $this->authHeaders());

        $data = $response->json('data');
        $this->assertEquals(33.33, $data['requests']['error_rate_1h']);
    }

    // -- Requests --

    public function test_requests_returns_paginated_metrics(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);

        $response = $this->getJson('/api/observability/requests', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 1);
    }

    public function test_requests_filters_by_path(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/ai/chat', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/vault/secrets', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);

        $response = $this->getJson('/api/observability/requests?path=ai', $this->authHeaders());

        $response->assertJsonPath('data.total', 1);
    }

    public function test_requests_filters_errors_only(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 500, 'response_time_ms' => 100, 'created_at' => now()]);

        $response = $this->getJson('/api/observability/requests?errors_only=1', $this->authHeaders());

        $response->assertJsonPath('data.total', 1);
    }

    // -- Errors --

    public function test_errors_returns_paginated_errors(): void
    {
        ErrorLog::create([
            'exception_class' => 'RuntimeException',
            'message' => 'Test error',
            'file' => '/app/test.php',
            'line' => 42,
            'severity' => 'error',
            'fingerprint' => hash('sha256', 'test'),
            'last_occurred_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/observability/errors', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 1);
    }

    public function test_errors_filters_by_severity(): void
    {
        ErrorLog::create(['exception_class' => 'Error', 'message' => 'Fatal', 'severity' => 'critical', 'fingerprint' => hash('sha256', 'critical'), 'last_occurred_at' => now(), 'created_at' => now()]);
        ErrorLog::create(['exception_class' => 'Exception', 'message' => 'Warning', 'severity' => 'warning', 'fingerprint' => hash('sha256', 'warning'), 'last_occurred_at' => now(), 'created_at' => now()]);

        $response = $this->getJson('/api/observability/errors?severity=critical', $this->authHeaders());

        $response->assertJsonPath('data.total', 1);
    }

    // -- Slow Queries --

    public function test_slow_queries_returns_paginated(): void
    {
        SlowQuery::create(['query' => 'SELECT * FROM users', 'time_ms' => 250.50, 'connection' => 'mysql', 'created_at' => now()]);

        $response = $this->getJson('/api/observability/slow-queries', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 1);
    }

    public function test_slow_queries_filters_by_min_time(): void
    {
        SlowQuery::create(['query' => 'SELECT 1', 'time_ms' => 110, 'created_at' => now()]);
        SlowQuery::create(['query' => 'SELECT * FROM big_table', 'time_ms' => 500, 'created_at' => now()]);

        $response = $this->getJson('/api/observability/slow-queries?min_time=200', $this->authHeaders());

        $response->assertJsonPath('data.total', 1);
    }

    // -- System Health --

    public function test_system_returns_health_info(): void
    {
        $response = $this->getJson('/api/observability/system', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'php_version',
                    'laravel_version',
                    'environment',
                    'disk' => ['free_gb', 'total_gb', 'used_percent'],
                    'database' => ['size_mb', 'observability_tables'],
                    'memory' => ['current_mb', 'peak_mb'],
                    'checked_at',
                ],
            ]);
    }

    // -- Aggregated Metrics --

    public function test_metrics_returns_aggregated_data(): void
    {
        MetricsAggregated::create([
            'period' => 'hourly',
            'period_start' => now()->subHour()->startOfHour(),
            'path' => null,
            'request_count' => 100,
            'error_count' => 5,
            'server_error_count' => 2,
            'avg_response_time_ms' => 120.50,
            'p95_response_time_ms' => 350.00,
            'p99_response_time_ms' => 800.00,
            'min_response_time_ms' => 10.00,
            'max_response_time_ms' => 1200.00,
        ]);

        $response = $this->getJson('/api/observability/metrics?period=hourly', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('data'));
    }
}
