<?php

namespace Tests\Feature\Commands;

use App\Models\MetricsAggregated;
use App\Models\RequestMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin "now" so subHour()->startOfHour() is deterministic.
        \Carbon\Carbon::setTestNow('2026-04-20 14:35:00');
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeMetric(array $overrides = []): RequestMetric
    {
        return RequestMetric::create(array_merge([
            'project' => 'havuncore',
            'method' => 'GET',
            'path' => '/api/x',
            'status_code' => 200,
            'response_time_ms' => 100,
            // Default: 13:30:00 (within previous-hour window 13:00-13:59).
            'created_at' => '2026-04-20 13:30:00',
        ], $overrides));
    }

    public function test_invalid_period_returns_failure(): void
    {
        $this->artisan('observability:aggregate', ['--period' => 'weekly'])
            ->expectsOutputToContain('Invalid period: weekly')
            ->assertExitCode(1);
    }

    public function test_aggregate_with_no_metrics_returns_success_silently(): void
    {
        $this->artisan('observability:aggregate', ['--period' => 'hourly'])
            ->expectsOutputToContain('No metrics to aggregate')
            ->assertExitCode(0);
    }

    public function test_hourly_aggregation_creates_per_endpoint_and_global_rows(): void
    {
        // Window with setTestNow=14:35 → previous hour = 13:00-13:59.
        for ($i = 0; $i < 3; $i++) {
            $this->makeMetric([
                'path' => '/api/foo',
                'response_time_ms' => 100 + $i * 50,
                'created_at' => '2026-04-20 13:' . (10 + $i) . ':00',
            ]);
        }

        $this->artisan('observability:aggregate', ['--period' => 'hourly'])
            ->assertExitCode(0);

        // Per-endpoint row + global (path=null) row
        $this->assertSame(2, MetricsAggregated::where('project', 'havuncore')->count());

        $endpointRow = MetricsAggregated::where('path', '/api/foo')->first();
        $this->assertNotNull($endpointRow);
        $this->assertSame(3, $endpointRow->request_count);
        $this->assertEqualsWithDelta(150, (float) $endpointRow->avg_response_time_ms, 1.0);
    }

    public function test_aggregate_counts_errors_separately_from_total(): void
    {
        $this->makeMetric(['status_code' => 200, 'created_at' => '2026-04-20 13:01:00']);
        $this->makeMetric(['status_code' => 404, 'created_at' => '2026-04-20 13:02:00']);
        $this->makeMetric(['status_code' => 500, 'created_at' => '2026-04-20 13:03:00']);

        $this->artisan('observability:aggregate', ['--period' => 'hourly'])->assertExitCode(0);

        $row = MetricsAggregated::where('path', '/api/x')->first();
        $this->assertSame(3, $row->request_count);
        $this->assertSame(2, $row->error_count, '404 + 500 = 2 errors');
        $this->assertSame(1, $row->server_error_count, '500 only');
    }

    public function test_daily_aggregation_uses_yesterday_window(): void
    {
        // setTestNow=2026-04-20 14:35 → daily window = 2026-04-19 00:00-23:59
        $this->makeMetric(['created_at' => '2026-04-19 12:00:00']);

        $this->artisan('observability:aggregate', ['--period' => 'daily'])->assertExitCode(0);

        $this->assertSame(1, MetricsAggregated::where('period', 'daily')->where('path', '/api/x')->count());
    }

    public function test_percentile_calculation_picks_correct_value(): void
    {
        // 100 metrics in window 13:00-13:59 — bulk insert om ~100 INSERT-calls
        // te bundelen tot één query
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = [
                'project' => 'havuncore',
                'method' => 'GET',
                'path' => '/api/x',
                'status_code' => 200,
                'response_time_ms' => $i,
                'created_at' => sprintf('2026-04-20 13:%02d:%02d', intdiv($i, 60), $i % 60),
            ];
        }
        RequestMetric::insert($rows);

        $this->artisan('observability:aggregate', ['--period' => 'hourly'])->assertExitCode(0);

        $row = MetricsAggregated::where('path', '/api/x')->first();
        // ceil(100 * 95/100) - 1 = 94 → index 94 → value 95
        $this->assertEqualsWithDelta(95, (float) $row->p95_response_time_ms, 0.5);
        $this->assertEqualsWithDelta(99, (float) $row->p99_response_time_ms, 0.5);
        $this->assertSame('1.00', (string) $row->min_response_time_ms);
        $this->assertSame('100.00', (string) $row->max_response_time_ms);
    }
}
