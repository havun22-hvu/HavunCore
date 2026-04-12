<?php

namespace App\Services\Chaos\Experiments;

use App\Models\ErrorLog;
use App\Services\Chaos\ChaosExperiment;

/**
 * Floods the error tracking system with errors to test deduplication and performance.
 */
class ErrorFloodExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Error Flood';
    }

    public function hypothesis(): string
    {
        return 'Error tracking handles 100 errors in <1s with deduplication working';
    }

    public function run(): array
    {
        $checks = [];
        $countBefore = ErrorLog::count();

        // Generate 100 identical errors — should deduplicate to 1 entry
        $dedupTest = $this->measure(function () {
            $exception = new \RuntimeException('Chaos test: error flood dedup');
            for ($i = 0; $i < 100; $i++) {
                ErrorLog::capture($exception);
            }
        });

        $countAfterDedup = ErrorLog::count();
        $newEntries = $countAfterDedup - $countBefore;

        $checks['dedup_performance'] = [
            'status' => $dedupTest['time_ms'] < 1000 ? 'pass' : 'warn',
            'message' => "100 identical errors in {$dedupTest['time_ms']}ms",
        ];

        $checks['dedup_effectiveness'] = [
            'status' => $newEntries <= 1 ? 'pass' : 'fail',
            'message' => "Created {$newEntries} entries (expected 1)",
        ];

        // Generate 50 unique errors — should create 50 entries
        $uniqueTest = $this->measure(function () {
            for ($i = 0; $i < 50; $i++) {
                try {
                    throw new \RuntimeException("Chaos test: unique error #{$i}");
                } catch (\Throwable $e) {
                    ErrorLog::capture($e);
                }
            }
        });

        $countAfterUnique = ErrorLog::count();
        $uniqueEntries = $countAfterUnique - $countAfterDedup;

        $checks['unique_performance'] = [
            'status' => $uniqueTest['time_ms'] < 2000 ? 'pass' : 'warn',
            'message' => "50 unique errors in {$uniqueTest['time_ms']}ms",
        ];

        $checks['unique_count'] = [
            'status' => $uniqueEntries === 50 ? 'pass' : 'warn',
            'message' => "Created {$uniqueEntries} entries (expected 50)",
        ];

        // Cleanup chaos test entries
        ErrorLog::where('message', 'like', 'Chaos test:%')->delete();

        $overallStatus = 'pass';
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
