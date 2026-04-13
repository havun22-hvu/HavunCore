<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Use in-memory SQLite for the doc_intelligence connection during tests
        $app['config']->set('database.connections.doc_intelligence.database', ':memory:');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Force doc_intelligence to in-memory SQLite and purge stale connections.
        config(['database.connections.doc_intelligence.database' => ':memory:']);
        DB::purge('doc_intelligence');

        // Create tables directly via SQL — artisan migrate hangs in CI.
        // IF NOT EXISTS prevents errors when tables already exist.
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
    }

    /**
     * Assert that a route does not return a 500 error.
     */
    protected function assertRouteAccessible(TestResponse $response, array $allowedCodes = [200, 401, 403]): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            $allowedCodes,
            "Route returned unexpected status {$response->getStatusCode()}"
        );
    }
}
