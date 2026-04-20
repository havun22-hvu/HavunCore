<?php

namespace Tests\Unit;

use App\Models\MetricsAggregated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsAggregatedTest extends TestCase
{
    use RefreshDatabase;

    private function makeAggregate(array $overrides = []): MetricsAggregated
    {
        return MetricsAggregated::create(array_merge([
            'project' => 'havuncore',
            'period' => 'hourly',
            'period_start' => now()->startOfHour(),
            'path' => '/api/test',
            'request_count' => 100,
            'error_count' => 5,
            'server_error_count' => 2,
            'avg_response_time_ms' => 150.50,
            'p95_response_time_ms' => 300.00,
            'p99_response_time_ms' => 500.00,
            'min_response_time_ms' => 10.00,
            'max_response_time_ms' => 1000.00,
        ], $overrides));
    }

    public function test_hourly_scope_filters_period(): void
    {
        // Composite unique key: project + period + period_start + path —
        // vary path/period_start to avoid collision.
        $this->makeAggregate(['period' => 'hourly', 'path' => '/a']);
        $this->makeAggregate(['period' => 'hourly', 'path' => '/b']);
        $this->makeAggregate(['period' => 'daily', 'path' => '/a',
            'period_start' => now()->startOfDay()]);

        $this->assertSame(2, MetricsAggregated::hourly()->count());
        $this->assertSame(1, MetricsAggregated::daily()->count());
    }

    public function test_global_scope_filters_null_path(): void
    {
        $this->makeAggregate(['path' => null]);
        $this->makeAggregate(['path' => '/api/x']);
        $this->makeAggregate(['path' => '/api/y']);

        $this->assertSame(1, MetricsAggregated::global()->count());
    }

    public function test_for_path_scope_filters_correctly(): void
    {
        // Same-path aggregates need different period_start to satisfy the
        // composite unique key.
        $this->makeAggregate(['path' => '/api/x', 'period_start' => now()->startOfHour()]);
        $this->makeAggregate(['path' => '/api/x', 'period_start' => now()->startOfHour()->subHour()]);
        $this->makeAggregate(['path' => '/api/y']);

        $this->assertSame(2, MetricsAggregated::forPath('/api/x')->count());
    }

    public function test_decimal_casts_preserve_precision(): void
    {
        $agg = $this->makeAggregate(['avg_response_time_ms' => 123.45]);

        $this->assertEqualsWithDelta(123.45, (float) $agg->fresh()->avg_response_time_ms, 0.01);
    }
}
