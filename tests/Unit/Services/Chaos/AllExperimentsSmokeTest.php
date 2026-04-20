<?php

namespace Tests\Unit\Services\Chaos;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Smoke-test voor alle 13 ChaosExperiment-implementaties.
 *
 * Doel: elke experiment-class moet `execute()` overleven zonder crash en
 * een gestructureerd result returnen (experiment + hypothesis + duration_ms +
 * results). Dat dekt:
 *   - de base-class try/catch + timing + structurering
 *   - elke concrete name()/hypothesis()/run()-flow
 *
 * Specifieke happy/sad-path assertions per experiment staan in eigen
 * *ExperimentTest files (zie EndpointProbeExperimentTest als voorbeeld).
 *
 * Dependencies (HTTP, Cache) zijn defaults gefakte zodat tests snel + offline
 * draaien; experiments die externe systemen nodig hebben rapporteren `warn`/
 * `fail` in run() — dat is precies wat de execute()-flow moet kunnen.
 */
class AllExperimentsSmokeTest extends TestCase
{
    // No RefreshDatabase — sommige experiments (DatabaseDisconnect /
    // BackupIntegrity) draaien zelf migrate, wat botst met de trait.
    // execute() vangt errors zelf op en levert structured `error`-status,
    // dus DB-state hoeft niet gegarandeerd schoon te zijn.

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response('OK', 200)]);
        // chaos.endpoints lijst leeg houden — EndpointProbe heeft dan niets
        // te checken maar overleeft execute() prima.
        config()->set('chaos.endpoints', []);
    }

    /**
     * @dataProvider experimentClasses
     */
    public function test_experiment_execute_returns_well_formed_result(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, ChaosExperiment::class),
            "{$class} must extend ChaosExperiment.");

        $experiment = new $class();
        $result = $experiment->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('experiment', $result);
        $this->assertArrayHasKey('hypothesis', $result);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertIsString($result['experiment']);
        $this->assertNotEmpty($result['experiment']);
        $this->assertIsString($result['hypothesis']);
        $this->assertNotEmpty($result['hypothesis']);
        $this->assertIsInt($result['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);
        $this->assertIsArray($result['results']);
    }

    public static function experimentClasses(): array
    {
        return [
            ['App\Services\Chaos\Experiments\ApiTimeoutExperiment'],
            ['App\Services\Chaos\Experiments\BackupIntegrityExperiment'],
            ['App\Services\Chaos\Experiments\CacheCorruptionExperiment'],
            ['App\Services\Chaos\Experiments\DatabaseDisconnectExperiment'],
            ['App\Services\Chaos\Experiments\DatabaseSlowExperiment'],
            ['App\Services\Chaos\Experiments\DiskPressureExperiment'],
            ['App\Services\Chaos\Experiments\DnsResolutionExperiment'],
            ['App\Services\Chaos\Experiments\EndpointProbeExperiment'],
            ['App\Services\Chaos\Experiments\ErrorFloodExperiment'],
            ['App\Services\Chaos\Experiments\HealthDeepExperiment'],
            ['App\Services\Chaos\Experiments\LatencyInjectionExperiment'],
            ['App\Services\Chaos\Experiments\MemoryPressureExperiment'],
            ['App\Services\Chaos\Experiments\PaymentProviderExperiment'],
        ];
    }
}
