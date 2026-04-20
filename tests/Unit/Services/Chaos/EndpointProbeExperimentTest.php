<?php

namespace Tests\Unit\Services\Chaos;

use App\Services\Chaos\Experiments\EndpointProbeExperiment;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage voor EndpointProbeExperiment + de base-class execute() flow.
 * Eén test per pad: pass / slow-warn / non-200-warn / connection-fail.
 */
class EndpointProbeExperimentTest extends TestCase
{
    public function test_execute_returns_structured_response(): void
    {
        config()->set('chaos.endpoints', []);

        $result = (new EndpointProbeExperiment())->execute();

        $this->assertSame('Endpoint Probe', $result['experiment']);
        $this->assertSame(
            'All project endpoints respond with 200 within 5 seconds',
            $result['hypothesis']
        );
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('results', $result);
    }

    public function test_pass_status_when_all_endpoints_respond_200_quickly(): void
    {
        config()->set('chaos.endpoints', [
            'havuncore' => 'https://havuncore.havun.nl/health',
        ]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $result = (new EndpointProbeExperiment())->execute();

        $this->assertSame('pass', $result['results']['status']);
        $this->assertSame('pass', $result['results']['checks']['havuncore']['status']);
    }

    public function test_warn_status_when_endpoint_returns_non_200(): void
    {
        config()->set('chaos.endpoints', [
            'broken' => 'https://broken.example/health',
        ]);
        Http::fake(['*' => Http::response('Server Error', 500)]);

        $result = (new EndpointProbeExperiment())->execute();

        $this->assertSame('warn', $result['results']['status']);
        $this->assertSame('warn', $result['results']['checks']['broken']['status']);
        $this->assertStringContainsString('HTTP 500', $result['results']['checks']['broken']['message']);
    }

    public function test_fail_status_when_endpoint_throws_connection_error(): void
    {
        config()->set('chaos.endpoints', [
            'down' => 'https://down.example/health',
        ]);
        Http::fake([
            '*' => fn () => throw new \Exception('Connection refused'),
        ]);

        $result = (new EndpointProbeExperiment())->execute();

        $this->assertSame('fail', $result['results']['status']);
        $this->assertSame('fail', $result['results']['checks']['down']['status']);
        $this->assertStringContainsString('Connection refused', $result['results']['checks']['down']['message']);
    }

    public function test_execute_catches_run_exceptions_and_reports_error(): void
    {
        // Force an exception by setting endpoints to a non-iterable value;
        // execute() must catch it and put status=error in the results.
        config()->set('chaos.endpoints', 'not-an-array');

        $result = (new EndpointProbeExperiment())->execute();

        $this->assertSame('error', $result['results']['status']);
        $this->assertArrayHasKey('error', $result['results']);
    }
}
