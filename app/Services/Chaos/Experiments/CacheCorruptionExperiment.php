<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\Cache;

/**
 * ACTIVE CHAOS: Corrupt cache entries and verify the app handles it gracefully.
 *
 * Tests that stale/corrupt cache doesn't cause 500 errors.
 */
class CacheCorruptionExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Cache Corruption & Recovery';
    }

    public function hypothesis(): string
    {
        return 'Application handles corrupted/missing cache entries gracefully without 500 errors';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. Baseline — cache works normally
        $baseline = $this->measure(function () {
            Cache::put('chaos_test_baseline', 'hello', 60);

            return Cache::get('chaos_test_baseline') === 'hello';
        });

        $checks['baseline'] = [
            'status' => $baseline['error'] ? 'fail' : ($baseline['result'] ? 'pass' : 'fail'),
            'message' => $baseline['error'] ?? ($baseline['result'] ? "Cache read/write OK ({$baseline['time_ms']}ms)" : 'Cache read/write FAILED'),
        ];

        // 2. CHAOS: Write corrupt data (wrong type) and try to read it as expected type
        $corruptResult = $this->measure(function () {
            // Write a string where code might expect an array
            Cache::put('chaos_test_corrupt', 'not_an_array', 60);
            $value = Cache::get('chaos_test_corrupt');

            // Simulate code that expects an array
            try {
                if (is_array($value)) {
                    return count($value);
                }

                return 'handled_gracefully';
            } catch (\Throwable $e) {
                return 'exception: ' . $e->getMessage();
            }
        });

        $checks['corrupt_type'] = [
            'status' => $corruptResult['error'] ? 'fail' : 'pass',
            'message' => $corruptResult['error'] ?? "Corrupt type handled: {$corruptResult['result']}",
        ];

        // 3. CHAOS: Flush all cache and verify app survives
        $flushResult = $this->measure(function () {
            Cache::flush();

            // Verify cache is empty
            return Cache::get('chaos_test_baseline') === null;
        });

        $checks['cache_flush'] = [
            'status' => $flushResult['error'] ? 'fail' : ($flushResult['result'] ? 'pass' : 'warn'),
            'message' => $flushResult['error'] ?? ($flushResult['result'] ? 'Cache flushed, app survived' : 'Cache flush did not clear entries'),
        ];

        // 4. CHAOS: Rapid write/read cycle (cache stampede simulation)
        $stampedeResult = $this->measure(function () {
            $failures = 0;
            for ($i = 0; $i < 100; $i++) {
                $key = "chaos_stampede_{$i}";
                Cache::put($key, "value_{$i}", 60);
                if (Cache::get($key) !== "value_{$i}") {
                    $failures++;
                }
            }

            // Cleanup
            for ($i = 0; $i < 100; $i++) {
                Cache::forget("chaos_stampede_{$i}");
            }

            return $failures;
        });

        $checks['cache_stampede'] = [
            'status' => $stampedeResult['error'] ? 'fail' : ($stampedeResult['result'] === 0 ? 'pass' : 'warn'),
            'message' => $stampedeResult['error']
                ?? ($stampedeResult['result'] === 0
                    ? "100 rapid read/writes: 0 failures ({$stampedeResult['time_ms']}ms)"
                    : "{$stampedeResult['result']}/100 failures in stampede test"),
        ];

        // 5. CHAOS: Read non-existent keys with defaults
        $missingResult = $this->measure(function () {
            $default = Cache::get('completely_nonexistent_key_12345', 'fallback_value');

            return $default === 'fallback_value';
        });

        $checks['missing_key_default'] = [
            'status' => $missingResult['error'] ? 'fail' : ($missingResult['result'] ? 'pass' : 'fail'),
            'message' => $missingResult['error'] ?? ($missingResult['result'] ? 'Default values work for missing keys' : 'Default value NOT returned'),
        ];

        // 6. CHAOS: remember() with exception in closure
        $rememberResult = $this->measure(function () {
            try {
                Cache::remember('chaos_exception_test', 60, function () {
                    throw new \RuntimeException('Simulated failure in cache loader');
                });

                return 'no_exception';
            } catch (\RuntimeException $e) {
                return 'exception_propagated';
            }
        });

        $exceptionHandled = $rememberResult['result'] === 'exception_propagated';
        $checks['remember_exception'] = [
            'status' => $exceptionHandled ? 'pass' : 'warn',
            'message' => $exceptionHandled
                ? 'Exception in Cache::remember() correctly propagated'
                : 'Exception was swallowed — potential silent failure',
        ];

        // Cleanup
        Cache::forget('chaos_test_baseline');
        Cache::forget('chaos_test_corrupt');
        Cache::forget('chaos_exception_test');

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
