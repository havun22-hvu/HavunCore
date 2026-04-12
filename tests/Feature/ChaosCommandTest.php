<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Services\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChaosCommandTest extends TestCase
{
    use RefreshDatabase;

    // -- Command --

    public function test_chaos_list_shows_experiments(): void
    {
        $this->artisan('chaos:run', ['--list' => true])
            ->expectsOutputToContain('health-deep')
            ->expectsOutputToContain('endpoint-probe')
            ->expectsOutputToContain('error-flood')
            ->expectsOutputToContain('db-slow')
            ->expectsOutputToContain('api-timeout')
            ->assertExitCode(0);
    }

    public function test_chaos_unknown_experiment_fails(): void
    {
        $this->artisan('chaos:run', ['experiment' => 'nonexistent'])
            ->expectsOutputToContain('Unknown experiment')
            ->assertExitCode(1);
    }

    public function test_chaos_no_argument_fails(): void
    {
        $this->artisan('chaos:run')
            ->expectsOutputToContain('Specify an experiment')
            ->assertExitCode(1);
    }

    // -- Health Deep --

    public function test_health_deep_runs_successfully(): void
    {
        $this->artisan('chaos:run', ['experiment' => 'health-deep'])
            ->assertExitCode(0);
    }

    public function test_deep_health_endpoint_returns_json(): void
    {
        config(['services.claude.api_key' => 'test-key']);

        $response = $this->getJson('/api/health/deep');

        $response->assertJsonStructure([
            'status',
            'checks',
            'duration_ms',
        ]);
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    // -- Error Flood --

    public function test_error_flood_runs_and_cleans_up(): void
    {
        $countBefore = ErrorLog::count();

        $this->artisan('chaos:run', ['experiment' => 'error-flood'])
            ->assertExitCode(0);

        // Chaos entries should be cleaned up
        $chaosEntries = ErrorLog::where('message', 'like', 'Chaos test:%')->count();
        $this->assertEquals(0, $chaosEntries);
    }

    // -- Database Slow --

    public function test_db_slow_runs_successfully(): void
    {
        $this->artisan('chaos:run', ['experiment' => 'db-slow'])
            ->assertExitCode(0);
    }

    // -- Circuit Breaker --

    public function test_circuit_breaker_starts_closed(): void
    {
        Cache::flush();
        $cb = new CircuitBreaker('test_service');

        $this->assertEquals('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    public function test_circuit_breaker_opens_after_failures(): void
    {
        Cache::flush();
        $cb = new CircuitBreaker('test_service');

        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        $this->assertEquals('open', $cb->getState());
        $this->assertFalse($cb->isAvailable());
    }

    public function test_circuit_breaker_resets_on_success(): void
    {
        Cache::flush();
        $cb = new CircuitBreaker('test_service');

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordSuccess();

        $this->assertEquals('closed', $cb->getState());
    }

    public function test_circuit_breaker_reset_method(): void
    {
        Cache::flush();
        $cb = new CircuitBreaker('test_service');

        for ($i = 0; $i < 5; $i++) {
            $cb->recordFailure();
        }

        $this->assertEquals('open', $cb->getState());

        $cb->reset();

        $this->assertEquals('closed', $cb->getState());
        $this->assertTrue($cb->isAvailable());
    }

    // -- Run All -- (skip: calls external APIs, run manually)

}
