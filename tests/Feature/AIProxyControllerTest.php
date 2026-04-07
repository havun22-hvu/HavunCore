<?php

namespace Tests\Feature;

use App\Models\AIUsageLog;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIProxyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.claude.api_key' => 'test-api-key',
            'services.claude.model' => 'claude-3-haiku-20240307',
            'services.claude.rate_limit' => 100,
        ]);

        Cache::flush();
    }

    // -- Health Endpoint --

    public function test_health_endpoint_returns_ok_when_configured(): void
    {
        $response = $this->getJson('/api/ai/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'ok',
                'api_configured' => true,
            ])
            ->assertJsonStructure(['model']);
    }

    public function test_health_endpoint_returns_degraded_without_api_key(): void
    {
        config(['services.claude.api_key' => '']);

        $response = $this->getJson('/api/ai/health');

        $response->assertStatus(503)
            ->assertJson([
                'success' => true,
                'status' => 'degraded',
                'api_configured' => false,
            ]);
    }

    // -- Chat Endpoint --

    public function test_chat_with_valid_tenant_returns_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Hello from Claude']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200),
        ]);

        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'infosyst',
            'message' => 'What is Laravel?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'response' => 'Hello from Claude',
            ])
            ->assertJsonStructure(['usage']);
    }

    public function test_chat_with_invalid_tenant_returns_422(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'nonexistent',
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('tenant');
    }

    public function test_chat_without_message_returns_422(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'infosyst',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_with_empty_message_returns_422(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'infosyst',
            'message' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_chat_without_tenant_returns_422(): void
    {
        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tenant');
    }

    public function test_chat_returns_503_on_api_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'havunadmin',
            'message' => 'Test question',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error']);
    }

    public function test_chat_rate_limiting_returns_429(): void
    {
        config(['services.claude.rate_limit' => 2]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Use up the rate limit
        $this->postJson('/api/ai/chat', ['tenant' => 'infosyst', 'message' => 'First']);
        $this->postJson('/api/ai/chat', ['tenant' => 'infosyst', 'message' => 'Second']);

        // Third request should be rate limited
        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'infosyst',
            'message' => 'Third',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['retry_after']);
    }

    public function test_chat_rate_limiting_is_per_tenant(): void
    {
        config(['services.claude.rate_limit' => 1]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Use up limit for infosyst
        $this->postJson('/api/ai/chat', ['tenant' => 'infosyst', 'message' => 'Hello']);

        // havunadmin should still work
        $response = $this->postJson('/api/ai/chat', [
            'tenant' => 'havunadmin',
            'message' => 'Hello',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -- Usage Endpoint --

    public function test_usage_returns_stats_for_tenant(): void
    {
        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'total_tokens' => 300,
            'execution_time_ms' => 500,
            'model' => 'claude-3-haiku',
        ]);

        AIUsageLog::create([
            'tenant' => 'infosyst',
            'input_tokens' => 50,
            'output_tokens' => 75,
            'total_tokens' => 125,
            'execution_time_ms' => 300,
            'model' => 'claude-3-haiku',
        ]);

        $response = $this->getJson('/api/ai/usage?tenant=infosyst');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tenant' => 'infosyst',
                'period' => 'day',
            ])
            ->assertJsonPath('stats.total_requests', 2)
            ->assertJsonPath('stats.total_input_tokens', 150)
            ->assertJsonPath('stats.total_output_tokens', 275)
            ->assertJsonPath('stats.total_tokens', 425);
    }

    public function test_usage_with_period_filter(): void
    {
        AIUsageLog::create([
            'tenant' => 'havunadmin',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'claude-3-haiku',
        ]);

        $response = $this->getJson('/api/ai/usage?tenant=havunadmin&period=month');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'tenant' => 'havunadmin',
                'period' => 'month',
            ])
            ->assertJsonPath('stats.total_requests', 1);
    }

    public function test_usage_without_tenant_returns_422(): void
    {
        $response = $this->getJson('/api/ai/usage');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_usage_with_invalid_period_returns_422(): void
    {
        $response = $this->getJson('/api/ai/usage?tenant=infosyst&period=invalid');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_usage_returns_zeros_for_tenant_without_data(): void
    {
        $response = $this->getJson('/api/ai/usage?tenant=judotoernooi');

        $response->assertStatus(200)
            ->assertJsonPath('stats.total_requests', 0)
            ->assertJsonPath('stats.total_tokens', 0);
    }

    // -- Chat logs usage --

    public function test_chat_creates_usage_log_record(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Logged response']],
                'usage' => ['input_tokens' => 25, 'output_tokens' => 50],
            ], 200),
        ]);

        $this->postJson('/api/ai/chat', [
            'tenant' => 'herdenkingsportaal',
            'message' => 'Test logging',
        ]);

        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant' => 'herdenkingsportaal',
            'input_tokens' => 25,
            'output_tokens' => 50,
            'total_tokens' => 75,
        ]);
    }

    // -- All valid tenants --

    public function test_chat_accepts_all_valid_tenants(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $tenants = ['infosyst', 'herdenkingsportaal', 'havunadmin', 'havuncore', 'judotoernooi'];

        foreach ($tenants as $tenant) {
            $response = $this->postJson('/api/ai/chat', [
                'tenant' => $tenant,
                'message' => 'Test for ' . $tenant,
            ]);

            $response->assertStatus(200, "Tenant '{$tenant}' should be accepted");
        }
    }
}
