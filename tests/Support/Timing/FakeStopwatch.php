<?php

namespace Tests\Support\Timing;

use App\Support\Timing\StartsMeasurements;
use App\Support\Timing\Stopwatch;

/**
 * A Stopwatch the test drives by hand.
 *
 * Lets a test state a duration exactly instead of sleeping for it: no wall
 * clock, no jitter, no seconds spent waiting.
 */
final class FakeStopwatch implements Stopwatch
{
    use StartsMeasurements;

    /**
     * Deliberately not zero. A real monotonic clock starts at an arbitrary
     * origin, and starting at zero would make `end - start` and `end + start`
     * produce the same number — hiding exactly the kind of mistake these
     * tests exist to catch.
     */
    private const ORIGIN = 1234.5;

    private float $elapsed = self::ORIGIN;

    public function seconds(): float
    {
        return $this->elapsed;
    }

    /**
     * Move the clock forward. Only forward — a monotonic clock never rewinds.
     */
    public function advance(float $seconds): void
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('A monotonic stopwatch cannot move backwards.');
        }

        $this->elapsed += $seconds;
    }
}
