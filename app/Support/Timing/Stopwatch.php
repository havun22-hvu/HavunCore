<?php

namespace App\Support\Timing;

/**
 * A monotonic source of elapsed time, for measuring how long something took.
 *
 * Deliberately not `Symfony\Component\Clock\MonotonicClock`, which does the
 * same job well: it is only a transitive dependency here, so using it directly
 * would mean adding one — and its `now()` builds a `DateTimeImmutable` per
 * reading, which a duration has to unpack again via `format('U.u')`. A float
 * is all a duration needs.
 */
interface Stopwatch
{
    /**
     * Seconds elapsed since an arbitrary, implementation-defined origin.
     *
     * Only differences between two readings are meaningful; the absolute value
     * carries no date or time-of-day information. Prefer `start()` — it does
     * the subtraction for you.
     */
    public function seconds(): float;

    /**
     * Begin a measurement. Ask the returned handle how long it has been.
     */
    public function start(): Measurement;
}
