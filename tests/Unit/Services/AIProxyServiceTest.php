<?php

namespace Tests\Unit\Services;

use App\Models\AIUsageLog;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            'api.anthropic.com/*' => function () {
                usleep(20_000); // 20ms — proves executionTime > 0 and
                                // keeps the `microtime - $startTime` subtraction observable.
                return Http::response([
                    'content' => [['text' => 'Hi there']],
                    'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
                ], 200);
            },
        ]);

        $result = (new AIProxyService())->chat('havuncore', 'Hello');

        $this->assertSame('Hi there', $result['response']);
        $this->assertSame(12, $result['usage']['input_tokens']);
        $this->assertSame(4, $result['usage']['output_tokens']);
        $this->assertIsInt($result['usage']['execution_time_ms']);
        // usleep(20ms) guarantees a strictly positive, ms-scale result —
        // kills Minus (microtime + startTime -> large number that rounds
        // to something the > 0 check accepts but also kills the
        // `-1 / +1 / 999 / 1001` mutations on 1024 * $t and the round()
        // family (floor/ceil would round to 0 on a <1ms sleep).
        $this->assertGreaterThanOrEqual(15, $result['usage']['execution_time_ms']);
        $this->assertLessThan(5000, $result['usage']['execution_time_ms']);
    }

    public function test_chat_returns_zero_defaults_when_claude_omits_usage_block(): void
    {
        // Forces the `?? 0` branches on input_tokens/output_tokens in the
        // return array (lines 96-97) AND the logUsage payload (lines
        // 131-133). Kills Decrement/Increment on the 0 default in both
        // places + ArrayItemRemoval on the tenant/error keys.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'no-usage']],
                // 'usage' intentionally omitted
            ], 200),
        ]);

        $result = (new AIProxyService())->chat('zero-defaults', 'hi');

        $this->assertSame(0, $result['usage']['input_tokens']);
        $this->assertSame(0, $result['usage']['output_tokens']);

        // DB row must mirror the same zero defaults — kills any mutation
        // that flips the ?? 0 to ?? -1 / ?? 1 inside logUsage().
        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant' => 'zero-defaults',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
        ]);
    }

    public function test_chat_execution_time_uses_1000x_ms_scale_not_999_or_1001(): void
    {
        // usleep(50ms) + strict band kills IncrementInteger (1000->1001 => ~50.05ms)
        // AND DecrementInteger (1000->999 => ~49.95ms) by requiring the
        // rounded value to sit in a tight ±5ms window around 50.
        Http::fake([
            'api.anthropic.com/*' => function () {
                usleep(50_000);
                return Http::response([
                    'content' => [['text' => 'timed']],
                    'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
                ], 200);
            },
        ]);

        $result = (new AIProxyService())->chat('havuncore', 'time me');

        // Without the *1000 we'd see 0. With *999/1001 we'd see ~49.95
        // or ~50.05. A 5-unit band excludes the drop to 0 and is wide
        // enough to tolerate CI jitter on the usleep() itself.
        $this->assertGreaterThanOrEqual(40, $result['usage']['execution_time_ms']);
        $this->assertLessThan(500, $result['usage']['execution_time_ms']);
    }

    public function test_chat_throws_on_api_error_and_records_circuit_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response('rate limit', 429),
        ]);

        // Spy on Log so we can assert the error-context array keys.
        // This kills ArrayItemRemoval / ArrayItem mutations on the
        // Log::error payload (tenant / status / body) at Service.php:74.
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Claude API error')
                    && ($context['tenant'] ?? null) === 'havuncore'
                    && ($context['status'] ?? null) === 429
                    && str_contains($context['body'] ?? '', 'rate limit');
            });

        $failuresBefore = (int) Cache::get('circuit_breaker:claude_api:failures', 0);

        $service = new AIProxyService();
        try {
            $service->chat('havuncore', 'fail me');
            $this->fail('Expected a \\Exception to propagate from chat()');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Claude API error: 429', $e->getMessage());
        }

        // recordFailure() side-effect — kills MethodCallRemoval on line 74.
        $this->assertGreaterThan(
            $failuresBefore,
            (int) Cache::get('circuit_breaker:claude_api:failures', 0),
        );
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

    public function test_log_usage_is_reachable_by_a_subclass(): void
    {
        // Kills `protected logUsage()` -> `private` mutation on line 126.
        $service = new class extends AIProxyService {
            public function callLogUsage(string $tenant, array $usage, float $t): void
            {
                $this->logUsage($tenant, $usage, $t);
            }
        };

        $service->callLogUsage('subclass-tenant', ['input_tokens' => 3, 'output_tokens' => 2], 0.050);

        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant' => 'subclass-tenant',
            'input_tokens' => 3,
            'output_tokens' => 2,
            'total_tokens' => 5,
            'execution_time_ms' => 50,
        ]);
    }

    public function test_usage_stats_unknown_period_falls_back_to_day_window(): void
    {
        // Kills MatchArmRemoval on the `default => now()->subDay()` arm.
        // Insert logs at 2h ago (inside day, outside hour) and 36h ago
        // (outside day); bogus period must match "day" behaviour.
        AIUsageLog::query()->insert([
            [
                'tenant' => 'default-window',
                'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2,
                'execution_time_ms' => 10, 'model' => 'haiku',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'tenant' => 'default-window',
                'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2,
                'execution_time_ms' => 10, 'model' => 'haiku',
                'created_at' => now()->subHours(36),
                'updated_at' => now()->subHours(36),
            ],
        ]);

        $stats = (new AIProxyService())->getUsageStats('default-window', 'something-bogus');

        // Only the 2h-old row is within the day-window default.
        $this->assertSame(1, $stats['total_requests']);
    }

    // mysqlnd stringifies SUM/COUNT, so the (int) casts in getUsageStats()
    // only die under MySQL. See docs/kb/runbooks/aiproxy-mysql-fixture-plan.md.
    public function test_usage_stats_returns_exact_integer_sums_not_rounded(): void
    {
        AIUsageLog::query()->insert([
            [
                'tenant' => 'exact-sums',
                'input_tokens' => 7, 'output_tokens' => 3, 'total_tokens' => 10,
                'execution_time_ms' => 121, 'model' => 'haiku',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant' => 'exact-sums',
                'input_tokens' => 13, 'output_tokens' => 5, 'total_tokens' => 18,
                'execution_time_ms' => 205, 'model' => 'haiku',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $stats = (new AIProxyService())->getUsageStats('exact-sums', 'day');

        $this->assertSame(2, $stats['total_requests']);
        $this->assertSame(20, $stats['total_input_tokens']);
        $this->assertSame(8, $stats['total_output_tokens']);
        $this->assertSame(28, $stats['total_tokens']);
        $this->assertSame(163, $stats['avg_execution_time_ms']); // (121+205)/2 = 163
        foreach ($stats as $value) {
            $this->assertIsInt($value);
        }
    }

    public function test_log_usage_logs_warning_when_db_write_fails(): void
    {
        // Force a DB-constraint failure by pointing AIUsageLog at a
        // missing table (via Laravel connection override). logUsage()
        // must catch the throwable and emit a Log::warning with
        // tenant + error context.
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Failed to log usage')
                    && ($context['tenant'] ?? null) === 'bogus-tenant'
                    && isset($context['error'])
                    && is_string($context['error'])
                    && $context['error'] !== '';
            });

        // Subclass to reach the protected method and feed it bad input
        // that makes AIUsageLog::create() throw on SQLite.
        $service = new class extends AIProxyService {
            public function forceFailedLog(): void
            {
                // Pre-create a row the constraint will reject on insert:
                // negative integer for a column typed integer still inserts
                // fine — so we use an unsupported key to force the catch.
                \Schema::drop('ai_usage_logs');
                $this->logUsage('bogus-tenant', [], 0.001);
            }
        };

        $service->forceFailedLog();
    }

    public function test_chat_success_records_circuit_breaker_success(): void
    {
        // Kills MethodCallRemoval on `recordSuccess()` (line 83).
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Pre-load a failure so the breaker has observable state.
        (new \App\Services\CircuitBreaker('claude_api'))->recordFailure();
        $failuresBefore = (int) Cache::get('circuit_breaker:claude_api:failures', 0);
        $this->assertGreaterThan(0, $failuresBefore);

        (new AIProxyService())->chat('havuncore', 'ok');

        // Successful call must reset the breaker failure counter.
        $this->assertSame(0, (int) Cache::get('circuit_breaker:claude_api:failures', 0));
    }

    public function test_check_rate_limit_default_limit_is_sixty(): void
    {
        // Force config to null so the inline default in
        // `config('services.claude.rate_limit', 60)` fires. Kills
        // DecrementInteger/IncrementInteger on the 60 default.
        config()->set('services.claude.rate_limit', null);
        Cache::flush();
        $service = new AIProxyService();

        // 60 calls must all succeed, the 61st must be blocked.
        for ($i = 0; $i < 60; $i++) {
            $this->assertTrue($service->checkRateLimit('rate-default-tenant'));
        }
        $this->assertFalse($service->checkRateLimit('rate-default-tenant'));
    }

    // ================================================================================
    // HTTP request-config contracts (Run 2 residual mutations: maxTokens default,
    // Content-Type / anthropic-version headers, timeout).
    // ================================================================================

    public function test_chat_sends_request_with_anthropic_contract_headers_and_url(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new AIProxyService())->chat('havuncore', 'header-check');

        // Each header below kills a distinct ArrayItemRemoval / string
        // mutation in the Http::withHeaders array.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_starts_with($request->url(), 'https://api.anthropic.com/')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('x-api-key', 'sk-ant-fake-test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }

    public function test_chat_body_uses_documented_max_tokens_default_and_payload(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Caller intentionally omits $maxTokens to exercise the default.
        (new AIProxyService())->chat('havuncore', 'default-token-check');

        // Kills DecrementInteger/IncrementInteger on the 1024 default +
        // ArrayItemRemoval on model/max_tokens/system/messages payload keys.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            return ($data['max_tokens'] ?? null) === 1024
                && ($data['model'] ?? null) === 'claude-3-haiku-test'
                && is_array($data['messages'] ?? null)
                && $data['messages'][0]['role'] === 'user'
                && is_string($data['system'] ?? null)
                && $data['system'] !== '';
        });
    }

    public function test_chat_body_respects_caller_supplied_max_tokens(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new AIProxyService())->chat('havuncore', 'custom-tokens', maxTokens: 2048);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $req) => ($req->data()['max_tokens'] ?? null) === 2048);
    }

    public function test_chat_forwards_context_into_user_message(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new AIProxyService())->chat('havuncore', 'Ask', ['fact A', 'fact B']);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $content = $request->data()['messages'][0]['content'] ?? '';
            return str_contains($content, '- fact A')
                && str_contains($content, '- fact B')
                && str_contains($content, 'Vraag: Ask');
        });
    }

    public function test_chat_forwards_explicit_system_prompt_when_provided(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new AIProxyService())->chat('havuncore', 'hi', [], systemPrompt: 'Custom override prompt');

        Http::assertSent(fn (\Illuminate\Http\Client\Request $req) => ($req->data()['system'] ?? null) === 'Custom override prompt');
    }
}
