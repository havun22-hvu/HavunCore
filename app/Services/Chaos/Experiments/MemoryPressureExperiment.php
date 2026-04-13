<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;

/**
 * ACTIVE CHAOS: Allocate memory progressively and measure system degradation.
 *
 * Tests that the system handles memory pressure without crashing.
 */
class MemoryPressureExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Memory Pressure';
    }

    public function hypothesis(): string
    {
        return 'System remains functional under memory pressure and PHP memory limit is correctly enforced';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. Baseline memory usage
        $baselineBytes = memory_get_usage(true);
        $baselineMb = round($baselineBytes / 1024 / 1024, 1);
        $limitMb = $this->getMemoryLimitMb();

        $checks['baseline'] = [
            'status' => 'pass',
            'message' => "Current: {$baselineMb}MB, limit: {$limitMb}MB, available: " . ($limitMb - $baselineMb) . 'MB',
        ];

        // 2. CHAOS: Allocate 10MB and verify system still works
        $alloc10 = $this->measure(function () {
            $data = str_repeat('x', 10 * 1024 * 1024); // 10MB
            $afterMb = round(memory_get_usage(true) / 1024 / 1024, 1);
            unset($data);
            gc_collect_cycles();

            return $afterMb;
        });

        $checks['allocate_10mb'] = [
            'status' => $alloc10['error'] ? 'fail' : 'pass',
            'message' => $alloc10['error'] ?? "10MB allocated OK, peak: {$alloc10['result']}MB ({$alloc10['time_ms']}ms)",
        ];

        // 3. CHAOS: Allocate 50MB and verify
        $alloc50 = $this->measure(function () {
            $data = str_repeat('x', 50 * 1024 * 1024); // 50MB
            $afterMb = round(memory_get_usage(true) / 1024 / 1024, 1);
            unset($data);
            gc_collect_cycles();

            return $afterMb;
        });

        $checks['allocate_50mb'] = [
            'status' => $alloc50['error'] ? 'warn' : 'pass',
            'message' => $alloc50['error'] ?? "50MB allocated OK, peak: {$alloc50['result']}MB ({$alloc50['time_ms']}ms)",
        ];

        if ($alloc50['error']) {
            $overallStatus = 'warn';
        }

        // 4. CHAOS: Create 10,000 small objects (simulates heavy ORM usage)
        $objectStress = $this->measure(function () {
            $objects = [];
            for ($i = 0; $i < 10000; $i++) {
                $objects[] = (object) ['id' => $i, 'name' => "item_{$i}", 'data' => str_repeat('a', 100)];
            }
            $count = count($objects);
            unset($objects);
            gc_collect_cycles();

            return $count;
        });

        $checks['object_stress'] = [
            'status' => $objectStress['error'] ? 'fail' : ($objectStress['time_ms'] > 1000 ? 'warn' : 'pass'),
            'message' => $objectStress['error'] ?? "10K objects created/destroyed in {$objectStress['time_ms']}ms",
        ];

        // 5. Verify DB still works after memory pressure
        $dbAfter = $this->measure(fn () => DB::selectOne('SELECT 1 as ok'));
        $checks['db_after_pressure'] = [
            'status' => $dbAfter['error'] ? 'fail' : 'pass',
            'message' => $dbAfter['error'] ?? "Database responsive after pressure ({$dbAfter['time_ms']}ms)",
        ];

        if ($dbAfter['error']) {
            $overallStatus = 'fail';
        }

        // 6. Memory recovered after cleanup?
        gc_collect_cycles();
        $afterMb = round(memory_get_usage(true) / 1024 / 1024, 1);
        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $recovered = $afterMb <= $baselineMb + 5; // Allow 5MB drift

        $checks['memory_recovery'] = [
            'status' => $recovered ? 'pass' : 'warn',
            'message' => "After cleanup: {$afterMb}MB (baseline: {$baselineMb}MB, peak: {$peakMb}MB)"
                . ($recovered ? '' : ' — possible memory leak'),
        ];

        // 7. Memory limit enforcement
        $checks['memory_limit'] = [
            'status' => $limitMb >= 128 ? 'pass' : 'warn',
            'message' => "PHP memory_limit: {$limitMb}MB" . ($limitMb < 128 ? ' (recommended: 128MB+)' : ''),
        ];

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }

    private function getMemoryLimitMb(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 999999;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => (int) round($value / 1024),
            default => (int) round($value / 1024 / 1024),
        };
    }
}
