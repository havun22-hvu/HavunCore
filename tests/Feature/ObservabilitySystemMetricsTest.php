<?php

namespace Tests\Feature;

use App\Services\ObservabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VP-16 mutation-hardening for ObservabilityService::getSystemHealth().
 * De disk-bytes berekening + array-structuur werden in de baseline-run
 * volledig overleefd door ~20 escaped mutaties.
 */
class ObservabilitySystemMetricsTest extends TestCase
{
    use RefreshDatabase;

    private ObservabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ObservabilityService::class);
    }

    public function test_system_metrics_returns_expected_top_level_keys(): void
    {
        $metrics = $this->service->getSystemHealth();

        $this->assertArrayHasKey('php_version', $metrics);
        $this->assertArrayHasKey('laravel_version', $metrics);
        $this->assertArrayHasKey('environment', $metrics);
        $this->assertArrayHasKey('disk', $metrics);
        $this->assertArrayHasKey('database', $metrics);
        $this->assertArrayHasKey('memory', $metrics);
        $this->assertArrayHasKey('checked_at', $metrics);
    }

    public function test_disk_block_has_exact_three_keys(): void
    {
        $disk = $this->service->getSystemHealth()['disk'];

        $this->assertSame(
            ['free_gb', 'total_gb', 'used_percent'],
            array_keys($disk),
            'Disk metric keys must match exactly — mutation guard.'
        );
    }

    public function test_disk_free_gb_is_bytes_divided_by_1024_cubed(): void
    {
        $disk = $this->service->getSystemHealth()['disk'];

        $this->assertIsFloat($disk['free_gb']);
        $this->assertGreaterThan(0, $disk['free_gb']);

        $expected = round(disk_free_space(base_path()) / 1024 / 1024 / 1024, 2);
        $this->assertEqualsWithDelta($expected, $disk['free_gb'], 0.5);
    }

    public function test_disk_total_gb_is_larger_than_or_equal_free(): void
    {
        $disk = $this->service->getSystemHealth()['disk'];

        $this->assertGreaterThanOrEqual($disk['free_gb'], $disk['total_gb']);
    }

    public function test_disk_used_percent_is_between_zero_and_one_hundred(): void
    {
        $disk = $this->service->getSystemHealth()['disk'];

        $this->assertGreaterThanOrEqual(0, $disk['used_percent']);
        $this->assertLessThanOrEqual(100, $disk['used_percent']);
    }

    public function test_disk_values_have_two_decimal_precision(): void
    {
        $disk = $this->service->getSystemHealth()['disk'];

        $freeStr = (string) $disk['free_gb'];
        $this->assertMatchesRegularExpression(
            '/^\d+(\.\d{1,2})?$/',
            $freeStr,
            'free_gb must use round(..., 2) — mutation guard against RoundingFamily/precision mutations.'
        );
    }

    public function test_memory_block_uses_expected_structure(): void
    {
        $memory = $this->service->getSystemHealth()['memory'];

        $this->assertSame(['current_mb', 'peak_mb'], array_keys($memory));
        $this->assertGreaterThan(0, $memory['current_mb']);
        $this->assertGreaterThanOrEqual($memory['current_mb'], $memory['peak_mb']);
    }
}
