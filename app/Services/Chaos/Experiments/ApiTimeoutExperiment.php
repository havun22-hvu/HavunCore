<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\Http;

/**
 * Tests behavior when external APIs are slow or unreachable.
 */
class ApiTimeoutExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'API Timeout Resilience';
    }

    public function hypothesis(): string
    {
        return 'System handles external API timeouts gracefully without cascading failures';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // Test Claude API reachability (HEAD request only, no tokens used)
        $claudeCheck = $this->measure(function () {
            return Http::timeout(5)
                ->withHeaders(['anthropic-version' => '2023-06-01'])
                ->head('https://api.anthropic.com/')
                ->status();
        });
        $checks['claude_api_reachable'] = [
            'status' => $claudeCheck['error'] ? 'warn' : 'pass',
            'message' => $claudeCheck['error']
                ? "Unreachable: {$claudeCheck['error']}"
                : "Reachable ({$claudeCheck['time_ms']}ms, HTTP {$claudeCheck['result']})",
        ];

        // Test PDOK API (postcode lookup service)
        $pdokCheck = $this->measure(function () {
            return Http::timeout(5)
                ->get('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free', ['q' => 'test'])
                ->status();
        });
        $checks['pdok_api'] = [
            'status' => $pdokCheck['error'] ? 'warn' : 'pass',
            'message' => $pdokCheck['error']
                ? "Unreachable: {$pdokCheck['error']}"
                : "Reachable ({$pdokCheck['time_ms']}ms)",
        ];

        // Test that HavunCore itself handles timeout scenarios
        // Simulate a slow external call with httpbin (if available) or localhost
        $timeoutHandling = $this->measure(function () {
            try {
                Http::timeout(2)->get('https://httpbin.org/delay/3');

                return 'no_timeout';
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return 'timeout_caught';
            }
        });
        $checks['timeout_handling'] = [
            'status' => $timeoutHandling['result'] === 'timeout_caught' ? 'pass' : 'warn',
            'message' => $timeoutHandling['result'] === 'timeout_caught'
                ? "Timeouts properly caught ({$timeoutHandling['time_ms']}ms)"
                : "Timeout not triggered or not caught ({$timeoutHandling['time_ms']}ms)",
        ];

        // Check circuit breaker state
        $cbState = cache()->get('circuit_breaker:claude_api', 'closed');
        $checks['circuit_breaker'] = [
            'status' => $cbState === 'closed' ? 'pass' : ($cbState === 'half-open' ? 'warn' : 'fail'),
            'message' => "State: {$cbState}",
        ];

        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $overallStatus = 'fail';
            } elseif ($check['status'] === 'warn' && $overallStatus !== 'fail') {
                $overallStatus = 'warn';
            }
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
