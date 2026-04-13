<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Use in-memory SQLite for the doc_intelligence connection during tests
        // Must be set before RefreshDatabase runs migrations
        $app['config']->set('database.connections.doc_intelligence.database', ':memory:');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create doc_intelligence tables via raw SQL — artisan migrate hangs in CI.
        if (! Schema::connection('doc_intelligence')->hasTable('doc_embeddings')) {
            $db = DB::connection('doc_intelligence');

            $db->statement('CREATE TABLE doc_embeddings (
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
            $db->statement('CREATE UNIQUE INDEX doc_embeddings_project_file ON doc_embeddings(project, file_path)');
            $db->statement('CREATE INDEX doc_embeddings_project ON doc_embeddings(project)');
            $db->statement('CREATE INDEX doc_embeddings_hash ON doc_embeddings(content_hash)');
            $db->statement('CREATE INDEX doc_embeddings_file_type ON doc_embeddings(file_type)');

            $db->statement('CREATE TABLE doc_issues (
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
            $db->statement('CREATE INDEX doc_issues_project ON doc_issues(project)');
            $db->statement('CREATE INDEX doc_issues_type ON doc_issues(issue_type)');
            $db->statement('CREATE INDEX doc_issues_severity ON doc_issues(severity)');
            $db->statement('CREATE INDEX doc_issues_status ON doc_issues(status)');

            $db->statement('CREATE TABLE doc_relations (
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
            $db->statement('CREATE INDEX doc_relations_source ON doc_relations(source_project, source_file)');
            $db->statement('CREATE INDEX doc_relations_target ON doc_relations(target_project, target_file)');
            $db->statement('CREATE INDEX doc_relations_type ON doc_relations(relation_type)');

            // Create migrations table so Laravel doesn't complain
            $db->statement('CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )');
        }
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
