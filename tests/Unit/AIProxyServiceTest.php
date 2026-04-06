<?php

namespace Tests\Unit;

use App\Models\AIUsageLog;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIProxyServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIProxyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config has non-null defaults for the service constructor
        config([
            'services.claude.api_key' => 'test-default-key',
            'services.claude.model' => 'claude-3-haiku-20240307',
        ]);

        $this->service = new AIProxyService();
    }

    // -- Health Check --

    public function test_health_check_reports_unhealthy_without_api_key(): void
    {
        config(['services.claude.api_key' => '']);
        $emptyKeyService = new AIProxyService();

        $result = $emptyKeyService->healthCheck();

        $this->assertFalse($result['healthy']);
        $this->assertFalse($result['api_configured']);
    }

    public function test_health_check_reports_healthy_with_api_key(): void
    {
        $result = $this->service->healthCheck();

        $this->assertTrue($result['healthy']);
        $this->assertTrue($result['api_configured']);
        $this->assertArrayHasKey('model', $result);
    }

    // -- Rate Limiting --

    public function test_rate_limit_allows_requests_within_limit(): void
    {
        Cache::flush();
        config(['services.claude.rate_limit' => 10]);

        $this->assertTrue($this->service->checkRateLimit('infosyst'));
    }

    public function test_rate_limit_blocks_requests_over_limit(): void
    {
        Cache::flush();
        config(['services.claude.rate_limit' => 3]);

        // Use up the limit
        $this->assertTrue($this->service->checkRateLimit('infosyst'));
        $this->assertTrue($this->service->checkRateLimit('infosyst'));
        $this->assertTrue($this->service->checkRateLimit('infosyst'));

        // Fourth request should be blocked
        $this->assertFalse($this->service->checkRateLimit('infosyst'));
    }

    public function test_rate_limit_is_per_tenant(): void
    {
        Cache::flush();
        config(['services.claude.rate_limit' => 1]);

        $this->assertTrue($this->service->checkRateLimit('infosyst'));
        $this->assertFalse($this->service->checkRateLimit('infosyst'));

        // Different tenant should still be allowed
        $this->assertTrue($this->service->checkRateLimit('havunadmin'));
    }

    // -- Chat (with HTTP fake) --

    public function test_chat_sends_correct_request_to_anthropic(): void
    {
        config(['services.claude.api_key' => 'test-key']);
        $service = new AIProxyService();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Hello from Claude']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200),
        ]);

        $result = $service->chat('infosyst', 'Test question');

        $this->assertEquals('Hello from Claude', $result['response']);
        $this->assertEquals(10, $result['usage']['input_tokens']);
        $this->assertEquals(20, $result['usage']['output_tokens']);
        $this->assertArrayHasKey('execution_time_ms', $result['usage']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }

    public function test_chat_includes_context_in_message(): void
    {
        config(['services.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Response']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 5],
            ], 200),
        ]);

        $this->service->chat('infosyst', 'Question', ['context item 1', 'context item 2']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $userMessage = $body['messages'][0]['content'] ?? '';
            return str_contains($userMessage, 'Context:')
                && str_contains($userMessage, '- context item 1')
                && str_contains($userMessage, 'Vraag: Question');
        });
    }

    public function test_chat_throws_on_api_error(): void
    {
        config(['services.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Claude API error: 401');

        $this->service->chat('infosyst', 'Test question');
    }

    public function test_chat_logs_usage_to_database(): void
    {
        config(['services.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Hello']],
                'usage' => ['input_tokens' => 15, 'output_tokens' => 25],
            ], 200),
        ]);

        $this->service->chat('havunadmin', 'Test');

        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant' => 'havunadmin',
            'input_tokens' => 15,
            'output_tokens' => 25,
            'total_tokens' => 40,
        ]);
    }

    public function test_chat_uses_custom_system_prompt(): void
    {
        config(['services.claude.api_key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'OK']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service->chat('infosyst', 'Test', [], 'Custom system prompt');

        Http::assertSent(function ($request) {
            return ($request->data()['system'] ?? '') === 'Custom system prompt';
        });
    }

    // -- Usage Stats --

    public function test_get_usage_stats_returns_correct_structure(): void
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

        $stats = $this->service->getUsageStats('infosyst', 'day');

        $this->assertEquals(2, $stats['total_requests']);
        $this->assertEquals(150, $stats['total_input_tokens']);
        $this->assertEquals(275, $stats['total_output_tokens']);
        $this->assertEquals(425, $stats['total_tokens']);
        $this->assertEquals(400, $stats['avg_execution_time_ms']);
    }

    public function test_get_usage_stats_returns_zeros_for_no_data(): void
    {
        $stats = $this->service->getUsageStats('nonexistent', 'day');

        $this->assertEquals(0, $stats['total_requests']);
        $this->assertEquals(0, $stats['total_tokens']);
    }

    public function test_get_usage_stats_supports_different_periods(): void
    {
        AIUsageLog::create([
            'tenant' => 'test',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
            'execution_time_ms' => 100,
            'model' => 'test',
        ]);

        foreach (['hour', 'day', 'week', 'month'] as $period) {
            $stats = $this->service->getUsageStats('test', $period);
            $this->assertArrayHasKey('total_requests', $stats);
        }
    }
}
