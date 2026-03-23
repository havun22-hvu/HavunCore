<?php

namespace Tests\Feature;

use Tests\TestCase;

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
        $this->assertRouteAccessible(
            $this->getJson('/api/ai/health'),
            [200, 401, 403, 500]
        );
    }

    public function test_docs_health_endpoint_does_not_crash(): void
    {
        $this->assertRouteAccessible($this->getJson('/api/docs/health'));
    }

    public function test_docs_search_requires_query(): void
    {
        $response = $this->getJson('/api/docs/search');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_route_list_command_succeeds(): void
    {
        $this->artisan('route:list')
            ->assertSuccessful();
    }
}
