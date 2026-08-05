<?php

namespace App\Support\Timing;

/**
 * The production Stopwatch, backed by the system's monotonic timer.
 */
final class SystemStopwatch implements Stopwatch
{
    use StartsMeasurements;

    public function seconds(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
