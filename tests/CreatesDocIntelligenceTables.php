<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Trait for tests that need the doc_intelligence database tables.
 * Creates tables via raw SQL instead of artisan migrate (which hangs in CI).
 */
trait CreatesDocIntelligenceTables
{
    protected function setUpDocIntelligenceTables(): void
    {
        // Use a temp file instead of :memory: to avoid connection issues in CI.
        $dbPath = sys_get_temp_dir() . '/doc_intelligence_test_' . getmypid() . '.sqlite';
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        config(['database.connections.doc_intelligence.database' => $dbPath]);
        DB::purge('doc_intelligence');

        $db = DB::connection('doc_intelligence');

        $db->statement('CREATE TABLE IF NOT EXISTS doc_embeddings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project VARCHAR(50) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            content TEXT NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            embedding TEXT NULL,
            token_count INTEGER NOT NULL DEFAULT 0,
            file_modified_at TIMESTAMP NULL,
            embedding_model VARCHAR(100) NULL,
            file_type VARCHAR(20) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        $db->statement('CREATE TABLE IF NOT EXISTS doc_issues (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project VARCHAR(50) NULL,
            issue_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL DEFAULT "medium",
            title VARCHAR(255) NOT NULL,
            details TEXT NOT NULL,
            affected_files TEXT NOT NULL,
            suggested_action TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "open",
            resolved_by VARCHAR(100) NULL,
            resolved_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        $db->statement('CREATE TABLE IF NOT EXISTS doc_relations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_project VARCHAR(50) NOT NULL,
            source_file VARCHAR(500) NOT NULL,
            target_project VARCHAR(50) NOT NULL,
            target_file VARCHAR(500) NOT NULL,
            relation_type VARCHAR(50) NOT NULL,
            confidence DECIMAL(3,2) NOT NULL DEFAULT 1.00,
            auto_detected INTEGER NOT NULL DEFAULT 1,
            details TEXT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        $db->statement('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL
        )');

        // Clean tables for fresh test state
        $db->statement('DELETE FROM doc_embeddings');
        $db->statement('DELETE FROM doc_issues');
        $db->statement('DELETE FROM doc_relations');
    }
}
