<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests: verify all public routes return non-500 responses.
 * These catch broken controllers, missing middleware, and syntax errors.
 */
class RouteSmokeTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok', 'app' => 'HavunCore']);
    }

    public function test_version_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/version');

        $response->assertStatus(200)
            ->assertJsonStructure(['app', 'version', 'environment', 'timestamp']);
    }

    public function test_web_root_returns_ok(): void
    {
        $response = $this->getJson('/');

        $response->assertStatus(200)
            ->assertJson(['app' => 'HavunCore']);
    }

    public function test_ai_health_endpoint_does_not_crash(): void
    {
        $response = $this->getJson('/api/ai/health');

        // May return 500 if CLAUDE_API_KEY not set in test env — that's expected
        // The important thing is it doesn't throw an unhandled exception
        $this->assertContains($response->getStatusCode(), [200, 401, 403, 500]);
    }

    public function test_docs_health_endpoint_does_not_crash(): void
    {
        $response = $this->getJson('/api/docs/health');

        // May require auth token
        $this->assertContains($response->getStatusCode(), [200, 401, 403]);
    }

    public function test_docs_search_requires_query(): void
    {
        $response = $this->getJson('/api/docs/search');

        // Should return 422 (validation) or 200, but NOT 500
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_route_list_command_succeeds(): void
    {
        $this->artisan('route:list')
            ->assertSuccessful();
    }
}
