<?php

namespace App\Services\Chaos;

use App\Support\Timing\Stopwatch;

/**
 * Base class for chaos experiments.
 *
 * Each experiment follows: hypothesis → inject → measure → report
 */
abstract class ChaosExperiment
{
    protected array $results = [];

    /**
     * Every subclass is resolved through the container and none defines its own
     * constructor, so injecting here reaches all of them without edits.
     */
    public function __construct(protected Stopwatch $stopwatch)
    {
    }

    abstract public function name(): string;

    abstract public function hypothesis(): string;

    abstract public function run(): array;

    /**
     * Execute the experiment with timing.
     */
    public function execute(): array
    {
        $measurement = $this->stopwatch->start();

        try {
            $this->results = $this->run();
        } catch (\Throwable $e) {
            $this->results = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'experiment' => $this->name(),
            'hypothesis' => $this->hypothesis(),
            'duration_ms' => $measurement->elapsedMs(),
            'results' => $this->results,
        ];
    }

    /**
     * Helper: measure execution time of a callable.
     */
    protected function measure(callable $fn): array
    {
        $measurement = $this->stopwatch->start();
        $result = null;
        $error = null;

        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            // Two decimals, as before: latency here is reported finer than a
            // whole millisecond.
            'time_ms' => $measurement->elapsedMsPrecise(),
            'result' => $result,
            'error' => $error,
        ];
    }

    /**
     * Helper: classify result as pass/warn/fail.
     */
    protected function classify(bool $passed, ?string $warning = null): string
    {
        if ($passed) {
            return $warning ? 'warn' : 'pass';
        }

        return 'fail';
    }
}
