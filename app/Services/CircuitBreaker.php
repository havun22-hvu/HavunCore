<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight circuit breaker using cache for state management.
 *
 * States: closed (normal) → open (failing, skip calls) → half-open (testing recovery)
 */
class CircuitBreaker
{
    protected string $service;

    protected int $failureThreshold;

    protected int $recoveryTimeout;

    protected int $successThreshold;

    public function __construct(string $service)
    {
        $this->service = $service;
        $this->failureThreshold = config('chaos.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = config('chaos.circuit_breaker.recovery_timeout', 60);
        $this->successThreshold = config('chaos.circuit_breaker.success_threshold', 2);
    }

    /**
     * Check if the circuit allows the request.
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state === 'closed') {
            return true;
        }

        if ($state === 'open') {
            $openedAt = Cache::get($this->key('opened_at'), 0);
            if (time() - $openedAt >= $this->recoveryTimeout) {
                $this->setState('half-open');

                return true;
            }

            return false;
        }

        // half-open: allow one request to test
        return true;
    }

    /**
     * Record a successful call.
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === 'half-open') {
            $successes = Cache::increment($this->key('half_open_successes'));
            if ($successes >= $this->successThreshold) {
                $this->reset();
            }
        } elseif ($state === 'closed') {
            // Reset failure count on success
            Cache::forget($this->key('failures'));
        }
    }

    /**
     * Record a failed call.
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === 'half-open') {
            $this->trip();

            return;
        }

        $failures = Cache::increment($this->key('failures'));
        if ($failures >= $this->failureThreshold) {
            $this->trip();
        }
    }

    /**
     * Get current circuit state.
     */
    public function getState(): string
    {
        return Cache::get($this->key('state'), 'closed');
    }

    /**
     * Trip the circuit to open state.
     */
    protected function trip(): void
    {
        $this->setState('open');
        Cache::put($this->key('opened_at'), time(), $this->recoveryTimeout * 2);
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('half_open_successes'));
    }

    /**
     * Reset to closed state.
     */
    public function reset(): void
    {
        $this->setState('closed');
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('opened_at'));
        Cache::forget($this->key('half_open_successes'));
    }

    /**
     * Set circuit state.
     */
    protected function setState(string $state): void
    {
        Cache::put($this->key('state'), $state, $this->recoveryTimeout * 3);
    }

    /**
     * Generate cache key.
     */
    protected function key(string $suffix): string
    {
        return "circuit_breaker:{$this->service}:{$suffix}";
    }
}
