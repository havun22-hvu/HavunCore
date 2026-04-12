<?php

namespace Tests\Unit;

use App\Models\RequestMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_recent_filters_by_hours(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/old', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()->subHours(25)]);

        $this->assertEquals(1, RequestMetric::recent(24)->count());
    }

    public function test_scope_errors_filters_4xx_and_5xx(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/ok', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/not-found', 'status_code' => 404, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/error', 'status_code' => 500, 'response_time_ms' => 50, 'created_at' => now()]);

        $this->assertEquals(2, RequestMetric::errors()->count());
    }

    public function test_scope_server_errors_filters_5xx_only(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/not-found', 'status_code' => 404, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/error', 'status_code' => 500, 'response_time_ms' => 50, 'created_at' => now()]);

        $this->assertEquals(1, RequestMetric::serverErrors()->count());
    }

    public function test_scope_for_tenant_filters_correctly(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'tenant' => 'infosyst', 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/test', 'status_code' => 200, 'response_time_ms' => 50, 'tenant' => 'havunadmin', 'created_at' => now()]);

        $this->assertEquals(1, RequestMetric::forTenant('infosyst')->count());
    }

    public function test_scope_for_path_filters_correctly(): void
    {
        RequestMetric::create(['method' => 'GET', 'path' => 'api/ai/chat', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);
        RequestMetric::create(['method' => 'GET', 'path' => 'api/vault/secrets', 'status_code' => 200, 'response_time_ms' => 50, 'created_at' => now()]);

        $this->assertEquals(1, RequestMetric::forPath('api/ai/chat')->count());
    }
}
