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

        // Run doc_intelligence migrations on the in-memory DB when tables are missing.
        // This replaces per-test artisan migrate calls that hang in CI.
        if (
            in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this))
            && ! Schema::connection('doc_intelligence')->hasTable('doc_embeddings')
        ) {
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations/2026_01_04_000001_create_doc_embeddings_table.php',
                '--realpath' => false,
            ]);
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations/2026_01_04_000002_create_doc_issues_table.php',
                '--realpath' => false,
            ]);
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations/2026_01_04_000003_create_doc_relations_table.php',
                '--realpath' => false,
            ]);
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations/2026_03_11_000001_add_embedding_model_to_doc_embeddings.php',
                '--realpath' => false,
            ]);
            $this->artisan('migrate', [
                '--database' => 'doc_intelligence',
                '--path' => 'database/migrations/2026_03_17_000001_add_file_type_to_doc_embeddings.php',
                '--realpath' => false,
            ]);
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
