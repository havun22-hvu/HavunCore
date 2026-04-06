<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
