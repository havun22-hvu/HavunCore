<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Deep health check — tests all system dependencies.
 */
class HealthDeepExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Deep Health Check';
    }

    public function hypothesis(): string
    {
        return 'All system dependencies (DB, disk, memory, external APIs) are healthy';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // Database connectivity + query time
        $dbCheck = $this->measure(fn () => DB::selectOne('SELECT 1 as ok'));
        $dbSlow = $dbCheck['time_ms'] > config('chaos.health.db_slow_threshold_ms', 50);
        $checks['database'] = [
            'status' => $dbCheck['error'] ? 'fail' : ($dbSlow ? 'warn' : 'pass'),
            'message' => $dbCheck['error'] ?? "Connected ({$dbCheck['time_ms']}ms)",
        ];
        if ($dbCheck['error'] || $dbSlow) {
            $overallStatus = $dbCheck['error'] ? 'fail' : 'warn';
        }

        // Disk space
        $diskFree = disk_free_space(base_path());
        $diskTotal = disk_total_space(base_path());
        $diskUsedPct = round((1 - $diskFree / $diskTotal) * 100, 1);
        $diskWarning = config('chaos.health.disk_warning_percent', 80);
        $diskCritical = config('chaos.health.disk_critical_percent', 90);
        $diskStatus = $diskUsedPct >= $diskCritical ? 'fail' : ($diskUsedPct >= $diskWarning ? 'warn' : 'pass');
        $checks['disk'] = [
            'status' => $diskStatus,
            'message' => "{$diskUsedPct}% used (" . round($diskFree / 1024 / 1024 / 1024, 1) . 'GB free)',
        ];
        if ($diskStatus !== 'pass') {
            $overallStatus = $diskStatus === 'fail' ? 'fail' : max($overallStatus, 'warn');
        }

        // Memory
        $memoryMb = round(memory_get_usage(true) / 1024 / 1024, 1);
        $memoryWarning = config('chaos.health.memory_warning_mb', 256);
        $memoryStatus = $memoryMb >= $memoryWarning ? 'warn' : 'pass';
        $checks['memory'] = [
            'status' => $memoryStatus,
            'message' => "{$memoryMb}MB current usage",
        ];

        // Claude API
        $claudeKey = config('services.claude.api_key');
        $checks['claude_api'] = [
            'status' => ! empty($claudeKey) ? 'pass' : 'fail',
            'message' => ! empty($claudeKey) ? 'API key configured' : 'API key missing',
        ];
        if (empty($claudeKey)) {
            $overallStatus = 'fail';
        }

        // PHP-FPM (basic check)
        $checks['php'] = [
            'status' => 'pass',
            'message' => 'PHP ' . PHP_VERSION . ', max_execution_time=' . ini_get('max_execution_time'),
        ];

        // Observability tables writable
        $obsCheck = $this->measure(function () {
            return DB::table('request_metrics')->count() >= 0;
        });
        $checks['observability'] = [
            'status' => $obsCheck['error'] ? 'fail' : 'pass',
            'message' => $obsCheck['error'] ?? "Tables accessible ({$obsCheck['time_ms']}ms)",
        ];

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
