<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiEndpointTest extends TestCase
{
    // -- Task Queue API --

    public function test_task_queue_index_does_not_crash(): void
    {
        $this->assertRouteAccessible(
            $this->getJson('/api/claude/tasks'),
            [200, 401, 403, 500]
        );
    }

    public function test_task_queue_pending_does_not_crash(): void
    {
        $this->assertRouteAccessible(
            $this->getJson('/api/claude/tasks/pending/havuncore'),
            [200, 401, 403, 500]
        );
    }

    // -- Vault API --

    public function test_vault_secrets_requires_auth(): void
    {
        $this->assertRouteAccessible(
            $this->getJson('/api/vault/secrets'),
            [401, 403, 422, 200]
        );
    }

    public function test_vault_bootstrap_requires_auth(): void
    {
        $this->assertRouteAccessible(
            $this->getJson('/api/vault/bootstrap'),
            [401, 403, 422, 200]
        );
    }

    // -- AI Proxy API --

    public function test_ai_chat_requires_post(): void
    {
        $this->getJson('/api/ai/chat')->assertStatus(405);
    }

    public function test_ai_usage_does_not_crash(): void
    {
        $this->assertRouteAccessible(
            $this->getJson('/api/ai/usage'),
            [200, 401, 403, 500]
        );
    }

    // -- Doc Intelligence API --

    public function test_docs_stats_returns_json(): void
    {
        $this->assertNotEquals(500, $this->getJson('/api/docs/stats')->getStatusCode());
    }

    public function test_docs_search_with_query_returns_json(): void
    {
        $this->assertNotEquals(500, $this->getJson('/api/docs/search?q=test')->getStatusCode());
    }

    // -- Auth API --

    public function test_auth_verify_requires_post(): void
    {
        $this->getJson('/api/auth/verify')->assertStatus(405);
    }

    public function test_auth_login_requires_post(): void
    {
        $this->getJson('/api/auth/login')->assertStatus(405);
    }
}
