<?php

namespace Tests\Unit;

use App\Models\ChaosResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChaosResultTest extends TestCase
{
    use RefreshDatabase;

    private function makeResult(array $overrides = []): ChaosResult
    {
        return ChaosResult::create(array_merge([
            'experiment' => 'health-deep',
            'status' => 'pass',
            'duration_ms' => 100,
            'checks' => ['db' => 'ok'],
            'created_at' => now(),
        ], $overrides));
    }

    public function test_for_experiment_scope_filters_correctly(): void
    {
        $this->makeResult(['experiment' => 'health-deep']);
        $this->makeResult(['experiment' => 'health-deep']);
        $this->makeResult(['experiment' => 'endpoint-probe']);

        $this->assertSame(2, ChaosResult::forExperiment('health-deep')->count());
    }

    public function test_failed_scope_filters_only_fail_status(): void
    {
        $this->makeResult(['status' => 'pass']);
        $this->makeResult(['status' => 'fail']);
        $this->makeResult(['status' => 'fail']);

        $this->assertSame(2, ChaosResult::failed()->count());
    }

    public function test_checks_array_is_cast_back_from_json(): void
    {
        $result = $this->makeResult(['checks' => ['db' => 'ok', 'cache' => 'fail']]);

        $this->assertSame(['db' => 'ok', 'cache' => 'fail'], $result->fresh()->checks);
    }

    public function test_no_auto_timestamps(): void
    {
        $result = $this->makeResult(['created_at' => now()->subHour()]);

        // Eloquent default = updated_at gets auto-set; this model disables it.
        $this->assertNull($result->fresh()->updated_at);
    }
}
