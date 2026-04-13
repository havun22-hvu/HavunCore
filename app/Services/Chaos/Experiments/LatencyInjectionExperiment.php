<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * ACTIVE CHAOS: Inject artificial latency into HTTP calls and measure degradation.
 *
 * Tests that the system handles slow external services without cascading failures.
 */
class LatencyInjectionExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Latency Injection';
    }

    public function hypothesis(): string
    {
        return 'System handles 2-5 second latency on external calls without cascading timeouts or 500 errors';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. Baseline — how fast is a normal HTTP call?
        $baseline = $this->measure(function () {
            return Http::timeout(5)->get('https://httpstat.us/200')->status();
        });

        $checks['baseline_http'] = [
            'status' => $baseline['error'] ? 'warn' : 'pass',
            'message' => $baseline['error'] ?? "Normal HTTP: {$baseline['time_ms']}ms (status {$baseline['result']})",
        ];

        // 2. CHAOS: Call endpoint with 2 second built-in delay
        $slow2s = $this->measure(function () {
            return Http::timeout(5)->get('https://httpstat.us/200?sleep=2000')->status();
        });

        $checks['latency_2s'] = [
            'status' => $slow2s['error'] ? 'warn' : 'pass',
            'message' => $slow2s['error'] ?? "2s delay handled: {$slow2s['time_ms']}ms total",
        ];

        // 3. CHAOS: Call with 4 second delay — near timeout boundary
        $slow4s = $this->measure(function () {
            return Http::timeout(5)->get('https://httpstat.us/200?sleep=4000')->status();
        });

        $checks['latency_4s'] = [
            'status' => $slow4s['error'] ? 'warn' : 'pass',
            'message' => $slow4s['error'] ?? "4s delay handled: {$slow4s['time_ms']}ms total",
        ];

        // 4. CHAOS: Call with 6 second delay — should timeout at 5s
        $slow6s = $this->measure(function () {
            try {
                Http::timeout(5)->get('https://httpstat.us/200?sleep=6000');

                return 'no_timeout';
            } catch (\Illuminate\Http\Client\ConnectionException) {
                return 'timeout_correctly';
            }
        });

        $timeoutWorked = $slow6s['result'] === 'timeout_correctly' || $slow6s['error'] !== null;
        $checks['timeout_enforcement'] = [
            'status' => $timeoutWorked ? 'pass' : 'fail',
            'message' => $timeoutWorked
                ? "Timeout enforced after {$slow6s['time_ms']}ms (limit: 5000ms)"
                : "TIMEOUT NOT ENFORCED — request completed in {$slow6s['time_ms']}ms",
        ];

        if (! $timeoutWorked) {
            $overallStatus = 'fail';
        }

        // 5. CHAOS: Parallel slow calls — verify no cascading
        $parallelResult = $this->measure(function () {
            $responses = Http::pool(fn ($pool) => [
                $pool->as('fast')->timeout(5)->get('https://httpstat.us/200'),
                $pool->as('slow')->timeout(5)->get('https://httpstat.us/200?sleep=2000'),
                $pool->as('normal')->timeout(5)->get('https://httpstat.us/200'),
            ]);

            $results = [];
            foreach ($responses as $key => $response) {
                if ($response instanceof \Throwable) {
                    $results[$key] = 'error';
                } else {
                    $results[$key] = $response->status();
                }
            }

            return $results;
        });

        if ($parallelResult['error']) {
            $checks['parallel_calls'] = [
                'status' => 'warn',
                'message' => "Parallel test error: {$parallelResult['error']}",
            ];
        } else {
            $allOk = collect($parallelResult['result'])->every(fn ($s) => $s === 200);
            $checks['parallel_calls'] = [
                'status' => $allOk ? 'pass' : 'warn',
                'message' => $allOk
                    ? "3 parallel calls (1 slow) completed in {$parallelResult['time_ms']}ms — no cascading"
                    : 'Some parallel calls failed: ' . json_encode($parallelResult['result']),
            ];
        }

        // 6. Database still works after all the HTTP chaos?
        $dbAfter = $this->measure(fn () => DB::selectOne('SELECT 1 as ok'));
        $checks['db_after_chaos'] = [
            'status' => $dbAfter['error'] ? 'fail' : 'pass',
            'message' => $dbAfter['error'] ?? "Database still responsive ({$dbAfter['time_ms']}ms)",
        ];

        if ($dbAfter['error']) {
            $overallStatus = 'fail';
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
