<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;

/**
 * ACTIVE CHAOS: Force-disconnect the database and verify auto-reconnection.
 *
 * This is a DESTRUCTIVE experiment — only runs on local/staging.
 */
class DatabaseDisconnectExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Database Disconnect & Recovery';
    }

    public function hypothesis(): string
    {
        return 'After a forced database disconnect, Laravel auto-reconnects and queries succeed without data loss';
    }

    public function run(): array
    {
        $this->guardEnvironment();

        $checks = [];
        $overallStatus = 'pass';

        // 1. Baseline — verify DB works before we break it
        $baseline = $this->measure(fn () => DB::selectOne('SELECT 1 as ok'));
        $checks['baseline'] = [
            'status' => $baseline['error'] ? 'fail' : 'pass',
            'message' => $baseline['error'] ?? "Baseline query OK ({$baseline['time_ms']}ms)",
        ];

        if ($baseline['error']) {
            return ['status' => 'fail', 'checks' => $checks];
        }

        // 2. Write a marker row before disconnect
        $markerId = 'chaos_test_' . time();
        $writeResult = $this->measure(function () use ($markerId) {
            DB::table('chaos_results')->insert([
                'experiment' => $markerId,
                'status' => 'marker',
                'duration_ms' => 0,
                'created_at' => now(),
            ]);

            return true;
        });

        $checks['pre_disconnect_write'] = [
            'status' => $writeResult['error'] ? 'fail' : 'pass',
            'message' => $writeResult['error'] ?? 'Marker row written',
        ];

        // 3. CHAOS: Force disconnect
        DB::disconnect();
        $checks['disconnect'] = [
            'status' => 'pass',
            'message' => 'Database connection forcefully closed',
        ];

        // 4. Attempt query AFTER disconnect — should auto-reconnect
        $reconnect = $this->measure(fn () => DB::selectOne('SELECT 1 as ok'));
        $checks['auto_reconnect'] = [
            'status' => $reconnect['error'] ? 'fail' : 'pass',
            'message' => $reconnect['error'] ?? "Auto-reconnected ({$reconnect['time_ms']}ms)",
        ];

        if ($reconnect['error']) {
            $overallStatus = 'fail';
        }

        // 5. Verify marker row survived the disconnect
        $verifyResult = $this->measure(function () use ($markerId) {
            return DB::table('chaos_results')
                ->where('experiment', $markerId)
                ->where('status', 'marker')
                ->exists();
        });

        $checks['data_integrity'] = [
            'status' => $verifyResult['error'] ? 'fail' : ($verifyResult['result'] ? 'pass' : 'fail'),
            'message' => $verifyResult['error']
                ?? ($verifyResult['result'] ? 'Marker row intact after reconnect' : 'MARKER ROW LOST — data integrity failure'),
        ];

        if (! $verifyResult['result']) {
            $overallStatus = 'fail';
        }

        // 6. Cleanup marker
        DB::table('chaos_results')->where('experiment', $markerId)->delete();

        // 7. Rapid disconnect/reconnect cycle (stress test)
        $cycleFailures = 0;
        for ($i = 0; $i < 5; $i++) {
            DB::disconnect();
            try {
                DB::selectOne('SELECT 1');
            } catch (\Throwable) {
                $cycleFailures++;
            }
        }

        $checks['rapid_reconnect'] = [
            'status' => $cycleFailures === 0 ? 'pass' : ($cycleFailures <= 1 ? 'warn' : 'fail'),
            'message' => $cycleFailures === 0
                ? '5/5 rapid reconnect cycles passed'
                : "{$cycleFailures}/5 reconnect cycles FAILED",
        ];

        if ($cycleFailures > 1) {
            $overallStatus = 'fail';
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }

    private function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('REFUSED: database-disconnect experiment cannot run in production');
        }
    }
}
