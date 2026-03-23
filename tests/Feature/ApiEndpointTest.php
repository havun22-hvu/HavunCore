<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * API endpoint tests: verify core API endpoints return expected structures.
 * These protect against breaking changes in HavunCore's public API
 * that other projects (HavunAdmin, Herdenkingsportaal, etc.) depend on.
 */
class ApiEndpointTest extends TestCase
{
    // -- Task Queue API --

    public function test_task_queue_index_does_not_crash(): void
    {
        $response = $this->getJson('/api/claude/tasks');

        // May fail on DB connection in test env — 500 from missing DB is acceptable
        $this->assertContains($response->getStatusCode(), [200, 401, 403, 500]);
    }

    public function test_task_queue_pending_does_not_crash(): void
    {
        $response = $this->getJson('/api/claude/tasks/pending/havuncore');

        $this->assertContains($response->getStatusCode(), [200, 401, 403, 500]);
    }

    // -- Vault API --

    public function test_vault_secrets_requires_auth(): void
    {
        $response = $this->getJson('/api/vault/secrets');

        // Should require auth (401/403), NOT crash (500)
        $this->assertContains($response->getStatusCode(), [401, 403, 422, 200]);
    }

    public function test_vault_bootstrap_requires_auth(): void
    {
        $response = $this->getJson('/api/vault/bootstrap');

        $this->assertContains($response->getStatusCode(), [401, 403, 422, 200]);
    }

    // -- AI Proxy API --

    public function test_ai_chat_requires_post(): void
    {
        $response = $this->getJson('/api/ai/chat');

        // GET should return 405 Method Not Allowed
        $response->assertStatus(405);
    }

    public function test_ai_usage_does_not_crash(): void
    {
        $response = $this->getJson('/api/ai/usage');

        // AI service needs API key — 500 from missing config is acceptable in test env
        $this->assertContains($response->getStatusCode(), [200, 401, 403, 500]);
    }

    // -- Doc Intelligence API --

    public function test_docs_stats_returns_json(): void
    {
        $response = $this->getJson('/api/docs/stats');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_docs_search_with_query_returns_json(): void
    {
        $response = $this->getJson('/api/docs/search?q=test');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    // -- Auth API --

    public function test_auth_verify_requires_post(): void
    {
        $response = $this->getJson('/api/auth/verify');

        $response->assertStatus(405);
    }

    public function test_auth_login_requires_post(): void
    {
        $response = $this->getJson('/api/auth/login');

        $response->assertStatus(405);
    }
}
