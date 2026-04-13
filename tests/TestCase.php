<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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

        // Create doc_intelligence tables when missing (in-memory SQLite resets between tests).
        // Run only the doc-specific migrations, not ALL migrations on this connection.
        if (! Schema::connection('doc_intelligence')->hasTable('doc_embeddings')) {
            foreach ([
                '2026_01_04_000001_create_doc_embeddings_table.php',
                '2026_01_04_000002_create_doc_issues_table.php',
                '2026_01_04_000003_create_doc_relations_table.php',
                '2026_03_11_000001_add_embedding_model_to_doc_embeddings.php',
                '2026_03_17_000001_add_file_type_to_doc_embeddings.php',
            ] as $migration) {
                $this->artisan('migrate', [
                    '--database' => 'doc_intelligence',
                    '--path' => "database/migrations/{$migration}",
                    '--realpath' => false,
                ]);
            }
        }
    }

    /**
     * Assert that a route does not return a 500 error.
     * Useful for smoke tests where the exact status depends on auth/config state.
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
