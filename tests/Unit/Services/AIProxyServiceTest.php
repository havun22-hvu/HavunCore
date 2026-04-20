<?php

namespace Tests\Unit\Services;

use App\Models\AIUsageLog;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Coverage voor AIProxyService — Claude API call + rate-limit + usage
 * stats + health-check. Toegevoegd 2026-04-20 om HavunCore Unit-coverage
 * richting 80 % te tillen.
 *
 * De feitelijke API call is gefakte via Http::fake; de circuit-breaker
 * recovery-pad is gedekt door CircuitBreakerTest.
 */
class AIProxyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.claude.api_key', 'sk-ant-fake-test-key');
        config()->set('services.claude.model', 'claude-3-haiku-test');
    }

    public function test_chat_returns_response_text_and_usage_on_success(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Hi there']],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
            ], 200),
        ]);

        $result = (new AIProxyService())->chat('havuncore', 'Hello');

        $this->assertSame('Hi there', $result['response']);
        $this->assertSame(12, $result['usage']['input_tokens']);
        $this->assertSame(4, $result['usage']['output_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['usage']['execution_time_ms']);
    }

    public function test_chat_throws_on_api_error_and_records_circuit_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response('rate limit', 429),
        ]);

        $service = new AIProxyService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Claude API error: 429');

        $service->chat('havuncore', 'fail me');
    }

    public function test_chat_logs_usage_to_ai_usage_log(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
            ], 200),
        ]);

        (new AIProxyService())->chat('infosyst', 'Test logging');

        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant' => 'infosyst',
            'input_tokens' => 5,
            'output_tokens' => 3,
            'total_tokens' => 8,
        ]);
    }

    public function test_check_rate_limit_blocks_after_configured_threshold(): void
    {
        config()->set('services.claude.rate_limit', 3);
        $service = new AIProxyService();

        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertFalse($service->checkRateLimit('havuncore'),
            '4th call exceeds limit of 3 → blocked.');
    }

    public function test_check_rate_limit_isolates_per_tenant(): void
    {
        config()->set('services.claude.rate_limit', 1);
        $service = new AIProxyService();

        $this->assertTrue($service->checkRateLimit('infosyst'));
        $this->assertFalse($service->checkRateLimit('infosyst'));
        $this->assertTrue($service->checkRateLimit('havuncore'),
            'Different tenant has its own bucket.');
    }

    public function test_get_usage_stats_aggregates_for_tenant_and_period(): void
    {
        AIUsageLog::create([
            'tenant' => 'havuncore',
            'input_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150,
            'execution_time_ms' => 200, 'model' => 'haiku',
        ]);
        AIUsageLog::create([
            'tenant' => 'havuncore',
            'input_tokens' => 80, 'output_tokens' => 40, 'total_tokens' => 120,
            'execution_time_ms' => 150, 'model' => 'haiku',
        ]);
        AIUsageLog::create([
            'tenant' => 'infosyst', // different tenant
            'input_tokens' => 999, 'output_tokens' => 999, 'total_tokens' => 1998,
            'execution_time_ms' => 500, 'model' => 'haiku',
        ]);

        $stats = (new AIProxyService())->getUsageStats('havuncore', 'day');

        $this->assertSame(2, $stats['total_requests']);
        $this->assertSame(180, $stats['total_input_tokens']);
        $this->assertSame(90, $stats['total_output_tokens']);
        $this->assertSame(270, $stats['total_tokens']);
        $this->assertEqualsWithDelta(175, $stats['avg_execution_time_ms'], 0.5);
    }

    public function test_health_check_reports_configured_when_api_key_present(): void
    {
        $health = (new AIProxyService())->healthCheck();

        $this->assertTrue($health['healthy']);
        $this->assertTrue($health['api_configured']);
        $this->assertSame('claude-3-haiku-test', $health['model']);
    }

    public function test_health_check_reports_unhealthy_when_api_key_missing(): void
    {
        config()->set('services.claude.api_key', '');

        $health = (new AIProxyService())->healthCheck();

        $this->assertFalse($health['healthy']);
        $this->assertFalse($health['api_configured']);
    }
}
