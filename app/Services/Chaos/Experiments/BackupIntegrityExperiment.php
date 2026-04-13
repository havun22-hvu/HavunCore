<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\DB;

/**
 * Backup integrity — checks that database is consistent and key tables are intact.
 */
class BackupIntegrityExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Backup & Data Integrity';
    }

    public function hypothesis(): string
    {
        return 'Database is consistent, key tables exist with expected structure, and SQLite integrity check passes';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. SQLite integrity check (main database)
        $integrityResult = $this->measure(function () {
            $result = DB::select('PRAGMA integrity_check');

            return $result[0]->integrity_check ?? 'unknown';
        });

        $integrityOk = $integrityResult['result'] === 'ok';
        $checks['sqlite_integrity'] = [
            'status' => $integrityResult['error'] ? 'fail' : ($integrityOk ? 'pass' : 'fail'),
            'message' => $integrityResult['error']
                ?? ($integrityOk ? "PRAGMA integrity_check: OK ({$integrityResult['time_ms']}ms)" : "INTEGRITY FAILURE: {$integrityResult['result']}"),
        ];
        if (! $integrityOk || $integrityResult['error']) {
            $overallStatus = 'fail';
        }

        // 2. Key tables exist
        $requiredTables = [
            'users',
            'tenants',
            'vault_entries',
            'task_queue_jobs',
            'error_logs',
            'request_metrics',
            'chaos_results',
        ];

        $tableResult = $this->measure(function () use ($requiredTables) {
            $existing = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $tableNames = array_map(fn ($t) => $t->name, $existing);
            $missing = array_diff($requiredTables, $tableNames);

            return ['total' => count($tableNames), 'missing' => $missing];
        });

        if ($tableResult['error']) {
            $checks['tables'] = ['status' => 'fail', 'message' => $tableResult['error']];
            $overallStatus = 'fail';
        } else {
            $missing = $tableResult['result']['missing'];
            $total = $tableResult['result']['total'];
            $checks['tables'] = [
                'status' => empty($missing) ? 'pass' : 'fail',
                'message' => empty($missing)
                    ? "{$total} tables found, all required present"
                    : 'Missing: ' . implode(', ', $missing),
            ];
            if (! empty($missing)) {
                $overallStatus = 'fail';
            }
        }

        // 3. Foreign key consistency
        $fkResult = $this->measure(function () {
            $violations = DB::select('PRAGMA foreign_key_check');

            return count($violations);
        });

        $checks['foreign_keys'] = [
            'status' => $fkResult['error'] ? 'warn' : ($fkResult['result'] === 0 ? 'pass' : 'fail'),
            'message' => $fkResult['error']
                ?? ($fkResult['result'] === 0 ? 'No foreign key violations' : "{$fkResult['result']} FK violations found"),
        ];
        if ($fkResult['result'] > 0) {
            $overallStatus = 'fail';
        }

        // 4. Database file size
        $dbPath = config('database.connections.sqlite.database', database_path('database.sqlite'));
        if (file_exists($dbPath)) {
            $dbSizeMb = round(filesize($dbPath) / 1024 / 1024, 1);
            $checks['db_size'] = [
                'status' => $dbSizeMb > 500 ? 'warn' : 'pass',
                'message' => "Database: {$dbSizeMb}MB",
            ];
        } else {
            $checks['db_size'] = ['status' => 'warn', 'message' => 'Database file not found at configured path'];
        }

        // 5. Doc Intelligence database integrity
        try {
            $docDb = DB::connection('doc_intelligence');
            $docIntegrity = $docDb->select('PRAGMA integrity_check');
            $docOk = ($docIntegrity[0]->integrity_check ?? '') === 'ok';
            $checks['doc_intelligence_db'] = [
                'status' => $docOk ? 'pass' : 'fail',
                'message' => $docOk ? 'Doc Intelligence DB: OK' : 'Doc Intelligence DB: INTEGRITY FAILURE',
            ];
        } catch (\Throwable $e) {
            $checks['doc_intelligence_db'] = [
                'status' => 'warn',
                'message' => 'Doc Intelligence DB not available: ' . $e->getMessage(),
            ];
        }

        // 6. WAL mode check (performance + crash recovery)
        $walResult = $this->measure(function () {
            $result = DB::selectOne('PRAGMA journal_mode');

            return $result->journal_mode ?? 'unknown';
        });

        $checks['journal_mode'] = [
            'status' => $walResult['result'] === 'wal' ? 'pass' : 'warn',
            'message' => "Journal mode: {$walResult['result']}" . ($walResult['result'] !== 'wal' ? ' (WAL recommended for crash recovery)' : ''),
        ];

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
