<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;

/**
 * Tests database performance under simulated load.
 */
class DatabaseSlowExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Database Slow Query Test';
    }

    public function hypothesis(): string
    {
        return 'Database handles concurrent reads and aggregation queries within thresholds';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // Simple SELECT performance
        $simpleQuery = $this->measure(fn () => DB::selectOne('SELECT 1'));
        $checks['simple_select'] = [
            'status' => $simpleQuery['time_ms'] < 10 ? 'pass' : ($simpleQuery['time_ms'] < 50 ? 'warn' : 'fail'),
            'message' => "{$simpleQuery['time_ms']}ms (threshold: <10ms)",
        ];

        // Count query on largest observability table
        $countQuery = $this->measure(fn () => DB::table('request_metrics')->count());
        $checks['count_request_metrics'] = [
            'status' => $countQuery['time_ms'] < 100 ? 'pass' : ($countQuery['time_ms'] < 500 ? 'warn' : 'fail'),
            'message' => "{$countQuery['result']} rows in {$countQuery['time_ms']}ms",
        ];

        // Aggregation query (simulates dashboard)
        $aggQuery = $this->measure(function () {
            return DB::table('request_metrics')
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('project, COUNT(*) as cnt, AVG(response_time_ms) as avg_time')
                ->groupBy('project')
                ->get();
        });
        $checks['aggregation_query'] = [
            'status' => $aggQuery['time_ms'] < 200 ? 'pass' : ($aggQuery['time_ms'] < 1000 ? 'warn' : 'fail'),
            'message' => "{$aggQuery['time_ms']}ms for daily aggregation",
        ];

        // Write performance (insert + delete)
        $writeQuery = $this->measure(function () {
            $id = DB::table('request_metrics')->insertGetId([
                'project' => 'chaos_test',
                'method' => 'GET',
                'path' => 'chaos/test',
                'status_code' => 200,
                'response_time_ms' => 0,
                'created_at' => now(),
            ]);
            DB::table('request_metrics')->where('id', $id)->delete();

            return $id;
        });
        $checks['write_performance'] = [
            'status' => $writeQuery['time_ms'] < 50 ? 'pass' : ($writeQuery['time_ms'] < 200 ? 'warn' : 'fail'),
            'message' => "Insert+delete in {$writeQuery['time_ms']}ms",
        ];

        // Table sizes
        $tableSizes = $this->measure(function () {
            return DB::select("
                SELECT table_name as tbl_name, table_rows as tbl_rows,
                       ROUND((data_length + index_length) / 1024 / 1024, 2) as tbl_size_mb
                FROM information_schema.TABLES
                WHERE table_schema = ?
                AND table_name IN ('request_metrics', 'error_logs', 'slow_queries', 'metrics_aggregated')
                ORDER BY table_rows DESC
            ", [config('database.connections.mysql.database', 'havuncore')]);
        });

        if (! $tableSizes['error'] && $tableSizes['result']) {
            foreach ($tableSizes['result'] as $table) {
                $checks["table_{$table->tbl_name}"] = [
                    'status' => 'pass',
                    'message' => "{$table->tbl_rows} rows, {$table->tbl_size_mb}MB",
                ];
            }
        }

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
