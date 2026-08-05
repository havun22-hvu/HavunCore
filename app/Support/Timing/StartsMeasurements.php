<?php

namespace App\Support\Timing;

/**
 * The one sensible implementation of `Stopwatch::start()`: read the clock now,
 * and hand the reading to a Measurement. Every Stopwatch wants this.
 */
trait StartsMeasurements
{
    public function start(): Measurement
    {
        return new Measurement($this, $this->seconds());
    }
}
