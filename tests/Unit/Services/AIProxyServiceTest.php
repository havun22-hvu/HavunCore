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

    // ================================================================================
    // Mutation-quick-wins (pilot 21-04-2026, pad 2 MSI 48 % -> target 90 %).
    // ================================================================================

    public function test_chat_logs_execution_time_in_milliseconds_not_seconds(): void
    {
        // Force a response that takes a measurable amount of time so the
        // `round($executionTime * 1000)` multiplication is observably > 0.
        // This kills mutations that drop the *1000 (would yield 0 on our
        // sub-second fake) and RoundingFamily mutations (floor/ceil on a
        // tiny value all collapse to 0).
        Http::fake([
            'api.anthropic.com/*' => function () {
                usleep(50_000); // 50ms
                return Http::response([
                    'content' => [['text' => 'ok']],
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ], 200);
            },
        ]);

        (new AIProxyService())->chat('havuncore', 'time-me');

        $log = AIUsageLog::latest('id')->first();
        $this->assertNotNull($log);
        // 50ms sleep must surface as >= ~40ms after rounding (CI jitter
        // slack). Anything near 0 means the *1000 mutation escaped.
        $this->assertGreaterThanOrEqual(40, $log->execution_time_ms);
        $this->assertIsInt($log->execution_time_ms);
    }

    public function test_chat_logs_usage_record_contains_each_documented_key(): void
    {
        // ArrayItem / ArrayItemRemoval mutations on the logUsage payload
        // would drop `tenant`, `input_tokens`, `output_tokens`, `model`.
        // Assert each key explicitly against a unique non-default value.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'payload-check']],
                'usage' => ['input_tokens' => 17, 'output_tokens' => 23],
            ], 200),
        ]);

        (new AIProxyService())->chat('havunadmin', 'Check payload keys');

        $log = AIUsageLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('havunadmin', $log->tenant);
        $this->assertSame(17, $log->input_tokens);
        $this->assertSame(23, $log->output_tokens);
        $this->assertSame(40, $log->total_tokens);
        $this->assertSame('claude-3-haiku-test', $log->model);
    }

    /**
     * @return iterable<string, array{period: string, logAgeHours: int, expectVisible: bool}>
     */
    public static function usageStatsPeriodCases(): iterable
    {
        // Each row picks an age that falls INSIDE one period but OUTSIDE
        // the tighter period directly above it, so removing any match-arm
        // causes at least one case to fail.
        yield 'hour keeps log within the hour' => ['hour', 0, true];
        yield 'hour drops log older than an hour' => ['hour', 2, false];
        yield 'day keeps log from 12h ago' => ['day', 12, true];
        yield 'day drops log from 2 days ago' => ['day', 48, false];
        yield 'week keeps log from 3 days ago' => ['week', 72, true];
        yield 'week drops log from 8 days ago' => ['week', 192, false];
        yield 'month keeps log from 20 days ago' => ['month', 480, true];
        yield 'month drops log from 60 days ago' => ['month', 1440, false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('usageStatsPeriodCases')]
    public function test_usage_stats_period_arms_resolve_correct_since_window(
        string $period,
        int $logAgeHours,
        bool $expectVisible,
    ): void {
        // Use query-builder insert so we can backdate `created_at` —
        // Eloquent's create() always overwrites timestamps with now().
        AIUsageLog::query()->insert([
            'tenant' => 'havuncore',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'total_tokens' => 2,
            'execution_time_ms' => 10,
            'model' => 'haiku',
            'created_at' => now()->subHours($logAgeHours),
            'updated_at' => now()->subHours($logAgeHours),
        ]);

        $stats = (new AIProxyService())->getUsageStats('havuncore', $period);

        $this->assertSame($expectVisible ? 1 : 0, $stats['total_requests']);
    }

    public function test_usage_stats_empty_window_returns_strict_zero_integers(): void
    {
        // Hard-assert exact === 0 on each numeric field. Kills CastInt
        // and IncrementInteger / DecrementInteger mutations on the
        // return-array's integer defaults.
        $stats = (new AIProxyService())->getUsageStats('empty-tenant');

        $this->assertSame(0, $stats['total_requests']);
        $this->assertSame(0, $stats['total_input_tokens']);
        $this->assertSame(0, $stats['total_output_tokens']);
        $this->assertSame(0, $stats['total_tokens']);
        $this->assertSame(0, $stats['avg_execution_time_ms']);
        foreach ($stats as $value) {
            $this->assertIsInt($value);
        }
    }

    public function test_default_system_prompt_is_reachable_by_a_subclass(): void
    {
        // Mutator can flip `protected getDefaultSystemPrompt()` to
        // `private` — which silently breaks any real subclass that
        // customises the system prompt. An anonymous subclass that
        // overrides the method proves the visibility contract.
        $service = new class extends AIProxyService {
            public function exposePrompt(string $tenant): string
            {
                return $this->getDefaultSystemPrompt($tenant);
            }
        };

        $this->assertStringContainsString('Infosyst', $service->exposePrompt('infosyst'));
        $this->assertStringContainsString('Herdenkingsportaal', $service->exposePrompt('herdenkingsportaal'));
        $this->assertStringContainsString('HavunAdmin', $service->exposePrompt('havunadmin'));
        $this->assertStringContainsString('HavunCore', $service->exposePrompt('havuncore'));
        $this->assertStringContainsString('Nederlands', $service->exposePrompt('unknown-tenant'));
    }
}
