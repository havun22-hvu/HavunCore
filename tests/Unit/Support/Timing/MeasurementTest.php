<?php

namespace Tests\Unit\Support\Timing;

use App\Support\Timing\SystemStopwatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Timing\FakeStopwatch;

/**
 * The arithmetic of a duration lives here, so this is where it gets pinned
 * down: the subtraction, the scale to milliseconds, and the rounding.
 */
class MeasurementTest extends TestCase
{
    public function test_it_reports_the_time_that_passed_since_it_started(): void
    {
        $stopwatch = new FakeStopwatch();
        $measurement = $stopwatch->start();

        $stopwatch->advance(2.0);

        $this->assertSame(2.0, $measurement->elapsedSeconds());
        // The fake starts at a non-zero origin on purpose: adding the start
        // instead of subtracting it would land in the millions, not on 2000.
        $this->assertSame(2000, $measurement->elapsedMs());
    }

    public function test_it_starts_at_zero_before_any_time_passes(): void
    {
        $stopwatch = new FakeStopwatch();

        $this->assertSame(0, $stopwatch->start()->elapsedMs());
    }

    /**
     * @return iterable<string, array{advance: float, expectedMs: int}>
     */
    public static function scaleAndRoundingCases(): iterable
    {
        // Ten seconds rather than fifty milliseconds, because at 50 ms an
        // off-by-one scale factor is invisible: *999 and *1001 both round
        // back to 50. At this size they land on 9990 and 10010.
        yield 'rounds up past the half' => ['advance' => 10.0006, 'expectedMs' => 10001];
        yield 'rounds down below the half' => ['advance' => 10.0004, 'expectedMs' => 10000];
    }

    #[DataProvider('scaleAndRoundingCases')]
    public function test_it_scales_to_milliseconds_and_rounds_to_nearest(float $advance, int $expectedMs): void
    {
        $stopwatch = new FakeStopwatch();
        $measurement = $stopwatch->start();

        $stopwatch->advance($advance);

        // Between the two cases: floor() misses the first, ceil() the second,
        // a dropped *1000 gives 10, and *999/*1001 miss both.
        $this->assertSame($expectedMs, $measurement->elapsedMs());
    }

    public function test_it_can_report_fractional_milliseconds(): void
    {
        // Chaos experiments report latency with two decimals. Rounding that to
        // whole milliseconds first would throw the detail away, so they need
        // their own accessor rather than a different scale factor.
        $stopwatch = new FakeStopwatch();
        $measurement = $stopwatch->start();

        $stopwatch->advance(0.0123456);

        $this->assertSame(12.35, $measurement->elapsedMsPrecise(2));
        $this->assertSame(12.3, $measurement->elapsedMsPrecise(1));
        // ChaosExperiment::measure() calls this without an argument, so the
        // default is part of the contract: changing it silently changes the
        // shape of every chaos report.
        $this->assertSame(12.35, $measurement->elapsedMsPrecise());
        // The whole-millisecond accessor is unaffected by the precise one.
        $this->assertSame(12, $measurement->elapsedMs());
    }

    public function test_the_system_stopwatch_only_moves_forward(): void
    {
        $measurement = (new SystemStopwatch())->start();

        // No sleep, so the honest claim is "not negative" — which is the
        // property a monotonic clock guarantees and a wall clock does not.
        $this->assertGreaterThanOrEqual(0.0, $measurement->elapsedSeconds());
        $this->assertGreaterThanOrEqual(0, $measurement->elapsedMs());
    }
}
