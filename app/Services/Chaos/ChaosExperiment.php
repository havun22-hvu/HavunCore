<?php

namespace App\Services\Chaos;

/**
 * Base class for chaos experiments.
 *
 * Each experiment follows: hypothesis → inject → measure → report
 */
abstract class ChaosExperiment
{
    protected array $results = [];

    protected float $startTime;

    abstract public function name(): string;

    abstract public function hypothesis(): string;

    abstract public function run(): array;

    /**
     * Execute the experiment with timing.
     */
    public function execute(): array
    {
        $this->startTime = microtime(true);

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
            'duration_ms' => (int) round((microtime(true) - $this->startTime) * 1000),
            'results' => $this->results,
        ];
    }

    /**
     * Helper: measure execution time of a callable.
     */
    protected function measure(callable $fn): array
    {
        $start = microtime(true);
        $result = null;
        $error = null;

        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
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
