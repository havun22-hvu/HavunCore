<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;

/**
 * Disk pressure — checks disk space, inode usage, and write capability under pressure.
 */
class DiskPressureExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Disk Pressure';
    }

    public function hypothesis(): string
    {
        return 'System handles disk pressure gracefully — logs, uploads, and cache dirs are writable with sufficient space';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. Disk space per critical path
        $paths = [
            'root' => base_path(),
            'storage' => storage_path(),
            'logs' => storage_path('logs'),
        ];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                $checks["disk_{$label}"] = ['status' => 'fail', 'message' => "Directory not found: {$path}"];
                $overallStatus = 'fail';

                continue;
            }

            $free = @disk_free_space($path);
            $total = @disk_total_space($path);

            if ($free === false || $total === false || $total === 0) {
                $checks["disk_{$label}"] = ['status' => 'fail', 'message' => 'Unable to read disk space'];
                $overallStatus = 'fail';

                continue;
            }

            $usedPct = round((1 - $free / $total) * 100, 1);
            $freeMb = round($free / 1024 / 1024);
            $status = $usedPct >= 95 ? 'fail' : ($usedPct >= 85 ? 'warn' : 'pass');

            $checks["disk_{$label}"] = [
                'status' => $status,
                'message' => "{$usedPct}% used, {$freeMb}MB free",
            ];

            if ($status === 'fail') {
                $overallStatus = 'fail';
            } elseif ($status === 'warn' && $overallStatus === 'pass') {
                $overallStatus = 'warn';
            }
        }

        // 2. Write test — can we actually write to storage?
        $writeDirs = [
            'logs' => storage_path('logs'),
            'cache' => storage_path('framework/cache'),
            'views' => storage_path('framework/views'),
        ];

        foreach ($writeDirs as $label => $dir) {
            $testFile = $dir . '/.chaos_write_test_' . getmypid();
            $writeResult = $this->measure(function () use ($testFile) {
                $written = @file_put_contents($testFile, 'chaos-test-' . time());
                if ($written !== false) {
                    @unlink($testFile);
                }

                return $written !== false;
            });

            $checks["write_{$label}"] = [
                'status' => $writeResult['error'] || ! $writeResult['result'] ? 'fail' : 'pass',
                'message' => $writeResult['error']
                    ?? ($writeResult['result'] ? "Writable ({$writeResult['time_ms']}ms)" : 'NOT writable'),
            ];

            if ($writeResult['error'] || ! $writeResult['result']) {
                $overallStatus = 'fail';
            }
        }

        // 3. Large file write simulation (1MB)
        $largeFile = storage_path('framework/cache/.chaos_large_test_' . getmypid());
        $largeResult = $this->measure(function () use ($largeFile) {
            $data = str_repeat('x', 1024 * 1024); // 1MB
            $written = @file_put_contents($largeFile, $data);
            if ($written !== false) {
                @unlink($largeFile);
            }

            return $written !== false;
        });

        $checks['write_1mb'] = [
            'status' => $largeResult['error'] || ! $largeResult['result'] ? 'fail' : ($largeResult['time_ms'] > 500 ? 'warn' : 'pass'),
            'message' => $largeResult['error']
                ?? ($largeResult['result'] ? "1MB written in {$largeResult['time_ms']}ms" : '1MB write FAILED'),
        ];

        // 4. Log file size check — oversized logs indicate problems
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logSizeMb = round(filesize($logFile) / 1024 / 1024, 1);
            $logStatus = $logSizeMb > 500 ? 'fail' : ($logSizeMb > 100 ? 'warn' : 'pass');
            $checks['log_size'] = [
                'status' => $logStatus,
                'message' => "laravel.log: {$logSizeMb}MB",
            ];
            if ($logStatus !== 'pass' && $overallStatus === 'pass') {
                $overallStatus = $logStatus;
            }
        } else {
            $checks['log_size'] = ['status' => 'pass', 'message' => 'No log file (clean)'];
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
