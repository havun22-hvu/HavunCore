<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * State-machine coverage voor de CircuitBreaker:
 *   closed → open (na failureThreshold)
 *   open   → half-open (na recoveryTimeout)
 *   half-open → closed (na successThreshold) of → open (op failure)
 *
 * Toegevoegd 2026-04-20 om HavunCore CI-coverage richting 80 % te tillen
 * (baseline 22 % Unit; deze service was untested).
 */
class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('chaos.circuit_breaker.failure_threshold', 3);
        config()->set('chaos.circuit_breaker.recovery_timeout', 60);
        config()->set('chaos.circuit_breaker.success_threshold', 2);
    }

    public function test_default_state_is_closed_and_available(): void
    {
        $cb = new CircuitBreaker('test');

        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function test_circuit_opens_after_failure_threshold(): void
    {
        $cb = new CircuitBreaker('test');

        for ($i = 0; $i < 3; $i++) {
            $cb->recordFailure();
        }

        $this->assertSame('open', $cb->getState());
        $this->assertFalse($cb->isAvailable());
    }

    public function test_success_resets_failure_count_when_closed(): void
    {
        $cb = new CircuitBreaker('test');

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordSuccess();
        $cb->recordFailure();
        $cb->recordFailure();

        // 4 failures total, but counter reset → still 2 → not yet open.
        $this->assertSame('closed', $cb->getState());
    }

    public function test_open_circuit_transitions_to_half_open_after_recovery_timeout(): void
    {
        $cb = new CircuitBreaker('test');

        // Trip
        for ($i = 0; $i < 3; $i++) {
            $cb->recordFailure();
        }

        // Pretend recovery time passed by writing an old opened_at.
        Cache::put('circuit_breaker:test:opened_at', time() - 120, 60);

        $this->assertTrue($cb->isAvailable(), 'Recovery window expired → must allow probe.');
        $this->assertSame('half-open', $cb->getState());
    }

    public function test_half_open_returns_to_closed_after_enough_successes(): void
    {
        $cb = new CircuitBreaker('test');

        // Force half-open state directly
        Cache::put('circuit_breaker:test:state', 'half-open', 60);

        $cb->recordSuccess();
        $cb->recordSuccess();

        $this->assertSame('closed', $cb->getState(), 'Two successes in half-open → recover.');
    }

    public function test_half_open_trips_open_again_on_single_failure(): void
    {
        $cb = new CircuitBreaker('test');
        Cache::put('circuit_breaker:test:state', 'half-open', 60);

        $cb->recordFailure();

        $this->assertSame('open', $cb->getState());
    }

    public function test_reset_returns_to_closed_unconditionally(): void
    {
        $cb = new CircuitBreaker('test');
        for ($i = 0; $i < 3; $i++) {
            $cb->recordFailure();
        }
        $this->assertSame('open', $cb->getState());

        $cb->reset();

        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function test_separate_service_names_have_independent_state(): void
    {
        $cbA = new CircuitBreaker('service-a');
        $cbB = new CircuitBreaker('service-b');

        for ($i = 0; $i < 3; $i++) {
            $cbA->recordFailure();
        }

        $this->assertSame('open', $cbA->getState());
        $this->assertSame('closed', $cbB->getState(), 'Service B must not be affected by Service A failures.');
    }
}
