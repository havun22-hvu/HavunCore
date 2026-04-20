<?php

namespace Tests\Feature\Commands;

use App\Models\RequestMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PerformanceBaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Carbon\Carbon::setTestNow('2026-04-20 06:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeMetric(string $project, int $responseMs, string $createdAt, int $statusCode = 200): void
    {
        RequestMetric::create([
            'project' => $project,
            'method' => 'GET',
            'path' => '/api/x',
            'status_code' => $statusCode,
            'response_time_ms' => $responseMs,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Bulk-insert 100 metrics over a 1-hour window — bypasses Eloquent
     * boot/events to keep these regression-alert tests fast.
     */
    private function seedHourlyMetrics(string $project, string $dateHour, callable $msFor): void
    {
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = [
                'project' => $project,
                'method' => 'GET',
                'path' => '/api/x',
                'status_code' => 200,
                'response_time_ms' => $msFor($i),
                'created_at' => sprintf('%s:%02d:%02d', $dateHour, intdiv($i, 60), $i % 60),
            ];
        }
        RequestMetric::insert($rows);
    }

    public function test_runs_with_no_metrics_and_returns_success(): void
    {
        $this->artisan('observability:baseline')->assertExitCode(0);
    }

    public function test_caches_baseline_for_30_days_under_date_key(): void
    {
        // setTestNow=2026-04-20 06:00 → today=2026-04-19, yesterday=2026-04-18.
        $this->makeMetric('havuncore', 100, '2026-04-19 12:00:00');

        $this->artisan('observability:baseline')->assertExitCode(0);

        $cached = Cache::get('performance_baseline:2026-04-19');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('havuncore', $cached);
        $this->assertSame(1, $cached['havuncore']['stats']['count']);
    }

    public function test_compares_today_against_previous_day(): void
    {
        // Today (2026-04-19) — 100ms
        $this->makeMetric('havuncore', 100, '2026-04-19 10:00:00');
        // Yesterday (2026-04-18) — 50ms
        $this->makeMetric('havuncore', 50, '2026-04-18 10:00:00');

        $this->artisan('observability:baseline')->assertExitCode(0);

        $cached = Cache::get('performance_baseline:2026-04-19');
        $this->assertSame(100.0, $cached['havuncore']['stats']['avg']);
        $this->assertSame(50.0, $cached['havuncore']['previous']['avg']);
    }

    public function test_logs_warning_when_p95_more_than_doubles(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'Performance regression'));

        // Yesterday p95=100, today p95=300 (3x → triggers alert)
        $this->seedHourlyMetrics('p1', '2026-04-18 10', fn ($i) => $i + 1);
        $this->seedHourlyMetrics('p1', '2026-04-19 10', fn ($i) => ($i + 1) * 3);

        $this->artisan('observability:baseline')->assertExitCode(0);
    }

    public function test_no_regression_alert_when_p95_within_threshold(): void
    {
        // Yesterday p95=100, today p95=150 (1.5x → under 2x threshold)
        $this->seedHourlyMetrics('p2', '2026-04-18 11', fn ($i) => $i + 1);
        $this->seedHourlyMetrics('p2', '2026-04-19 11', fn ($i) => (int) (($i + 1) * 1.5));

        Log::shouldReceive('warning')->never();
        $this->artisan('observability:baseline')->assertExitCode(0);
    }

    public function test_calculates_error_rate_correctly(): void
    {
        $this->makeMetric('p3', 100, '2026-04-19 09:00:00', 200);
        $this->makeMetric('p3', 100, '2026-04-19 09:01:00', 200);
        $this->makeMetric('p3', 100, '2026-04-19 09:02:00', 200);
        $this->makeMetric('p3', 100, '2026-04-19 09:03:00', 200);
        $this->makeMetric('p3', 100, '2026-04-19 09:04:00', 500);

        $this->artisan('observability:baseline')->assertExitCode(0);

        $cached = Cache::get('performance_baseline:2026-04-19');
        $this->assertSame(20.0, $cached['p3']['stats']['error_rate']);
        $this->assertSame(1, $cached['p3']['stats']['errors']);
    }
}
