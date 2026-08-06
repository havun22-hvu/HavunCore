<?php

namespace App\Support\Timing;

/**
 * One duration, running from the moment `Stopwatch::start()` was called.
 *
 * This is where the arithmetic lives — the subtraction, the scale to
 * milliseconds and the rounding — so that every caller gets the same answer
 * and none of them has to get it right on their own.
 */
final class Measurement
{
    public function __construct(
        private readonly Stopwatch $stopwatch,
        private readonly float $startedAt,
    ) {
    }

    public function elapsedSeconds(): float
    {
        return $this->stopwatch->seconds() - $this->startedAt;
    }

    /**
     * The duration in whole milliseconds, rounded to nearest.
     */
    public function elapsedMs(): int
    {
        return (int) round($this->elapsedSeconds() * 1000);
    }

    /**
     * Milliseconds with decimals, for callers that report finer latency than a
     * whole millisecond — chaos experiments, for one.
     */
    public function elapsedMsPrecise(int $decimals = 2): float
    {
        return round($this->elapsedSeconds() * 1000, $decimals);
    }
}
