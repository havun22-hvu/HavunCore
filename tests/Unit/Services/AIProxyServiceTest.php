<?php

namespace Tests\Unit\Services;

use App\Models\AIUsageLog;
use App\Models\HealthAlert;
use App\Services\AIProxyService;
use App\Services\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\Timing\FakeStopwatch;
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

    /**
     * Drives the service's duration measurement. Tests that care about timing
     * advance it explicitly; the rest leave it where it starts.
     */
    private FakeStopwatch $stopwatch;

    /** Injected rather than reached for: the test can ask it what it thinks. */
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.claude.api_key', 'sk-ant-fake-test-key');
        config()->set('services.claude.model', 'claude-3-haiku-test');
        $this->stopwatch = new FakeStopwatch();
        $this->breaker = new CircuitBreaker('claude_api');
    }

    private function service(): AIProxyService
    {
        return new AIProxyService($this->stopwatch, $this->breaker);
    }

    public function test_chat_returns_response_text_and_usage_on_success(): void
    {
        Http::fake([
            'api.anthropic.com/*' => function () {
                // Time passes while the call is in flight.
                $this->stopwatch->advance(0.02);

                return Http::response([
                    'content' => [['type' => 'text', 'text' => 'Hi there']],
                    'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
                ], 200);
            },
        ]);

        $result = $this->service()->chat('havuncore', 'Hello');

        $this->assertSame('Hi there', $result['response']);
        $this->assertSame(12, $result['usage']['input_tokens']);
        $this->assertSame(4, $result['usage']['output_tokens']);
        // The clock has to start before the call, not after: only then does
        // the 20 ms that passed during it end up in the result. (What that
        // number is made of, is MeasurementTest's job.)
        $this->assertSame(20, $result['usage']['execution_time_ms']);
    }

    public function test_chat_returns_zero_defaults_when_claude_omits_usage_block(): void
    {
        // Forces the `?? 0` branches on input_tokens/output_tokens in the
        // return array (lines 96-97) AND the logUsage payload (lines
        // 131-133). Kills Decrement/Increment on the 0 default in both
        // places + ArrayItemRemoval on the tenant/error keys.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'no-usage']],
                // 'usage' intentionally omitted
            ], 200),
        ]);

        $result = $this->service()->chat('zero-defaults', 'hi');

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

        // The breaker is asked, not inspected afterwards.
        $breaker = \Mockery::mock(CircuitBreaker::class);
        $breaker->shouldReceive('isAvailable')->andReturnTrue();
        $breaker->shouldReceive('recordFailure')->once();
        $breaker->shouldNotReceive('recordSuccess');

        try {
            (new AIProxyService($this->stopwatch, $breaker))->chat('havuncore', 'fail me');
            $this->fail('Expected a \\Exception to propagate from chat()');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Claude API error: 429', $e->getMessage());
        }
    }

    public function test_a_missing_model_raises_a_health_alert_instead_of_only_a_log_line(): void
    {
        // On 2026-08-05 the configured model had been retired months earlier.
        // Every call 404'd for 19 hours; the log had it, nobody was told.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'type' => 'error',
                'error' => ['type' => 'not_found_error', 'message' => 'model: claude-3-haiku-20240307'],
            ], 404),
        ]);

        try {
            $this->service()->chat('havuncore', 'anyone there?');
        } catch (\Exception) {
            // The throw is the existing contract; the alert is what's being tested.
        }

        $alert = HealthAlert::where('key', 'ai-proxy-model')->first();
        $this->assertNotNull($alert, 'A 404 from the model endpoint must reach a person.');
        $this->assertSame('open', $alert->status);
        $this->assertSame('critical', $alert->severity);
        // The alert has to name the model, or whoever reads it cannot act on it.
        $this->assertStringContainsString('claude-3-haiku-test', $alert->body);
    }

    public function test_a_working_call_closes_a_previously_raised_model_alert(): void
    {
        // An alert that stays open after the fix is the same lie in reverse.
        HealthAlert::create([
            'key' => 'ai-proxy-model',
            'scope' => 'server',
            'severity' => 'critical',
            'title' => 'AI-model niet beschikbaar',
            'status' => 'open',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now()->subHour(),
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'back']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service()->chat('havuncore', 'still there?');

        $this->assertSame('resolved', HealthAlert::where('key', 'ai-proxy-model')->first()->status);
    }

    public function test_chat_reads_the_text_block_even_when_thinking_comes_first(): void
    {
        // A thinking model puts thinking blocks ahead of the answer, so
        // content[0] is not the text block. Measured on production 2026-08-05:
        // 583 output tokens generated and an empty string returned.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'thinking', 'thinking' => '', 'signature' => 'abc'],
                    ['type' => 'text', 'text' => 'The actual answer.'],
                ],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 200],
            ], 200),
        ]);

        $result = $this->service()->chat('havuncore', 'think first');

        $this->assertSame('The actual answer.', $result['response']);
    }

    public function test_chat_returns_empty_string_when_the_reply_carries_no_text(): void
    {
        // A refusal or a thinking-only turn has no text block at all. Empty
        // string, not a crash and not a stray thinking field.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'thinking', 'thinking' => 'internal', 'signature' => 'x']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 50],
            ], 200),
        ]);

        $result = $this->service()->chat('havuncore', 'no answer');

        $this->assertSame('', $result['response']);
    }

    public function test_chat_logs_usage_to_ai_usage_log(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 3],
            ], 200),
        ]);

        $this->service()->chat('infosyst', 'Test logging');

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
        $service = $this->service();

        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertTrue($service->checkRateLimit('havuncore'));
        $this->assertFalse($service->checkRateLimit('havuncore'),
            '4th call exceeds limit of 3 → blocked.');
    }

    public function test_check_rate_limit_isolates_per_tenant(): void
    {
        config()->set('services.claude.rate_limit', 1);
        $service = $this->service();

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

        $stats = $this->service()->getUsageStats('havuncore', 'day');

        $this->assertSame(2, $stats['total_requests']);
        $this->assertSame(180, $stats['total_input_tokens']);
        $this->assertSame(90, $stats['total_output_tokens']);
        $this->assertSame(270, $stats['total_tokens']);
        $this->assertEqualsWithDelta(175, $stats['avg_execution_time_ms'], 0.5);
    }

    public function test_health_check_reports_configured_when_api_key_present(): void
    {
        $health = $this->service()->healthCheck();

        $this->assertTrue($health['healthy']);
        $this->assertTrue($health['api_configured']);
        $this->assertSame('claude-3-haiku-test', $health['model']);
    }

    public function test_health_check_reports_unhealthy_when_api_key_missing(): void
    {
        config()->set('services.claude.api_key', '');

        $health = $this->service()->healthCheck();

        $this->assertFalse($health['healthy']);
        $this->assertFalse($health['api_configured']);
    }

    // ================================================================================
    // Mutation-quick-wins (pilot 21-04-2026, pad 2 MSI 48 % -> target 90 %).
    // ================================================================================

    public function test_chat_logs_execution_time_in_milliseconds_not_seconds(): void
    {
        // The logged value has its own `round($executionTime * 1000)` in
        // logUsage(), separate from the one in the return array — so it needs
        // its own assertion. Two seconds of stopwatch time is 2000 ms; had the
        // *1000 been dropped it would be 2.
        Http::fake([
            'api.anthropic.com/*' => function () {
                $this->stopwatch->advance(2.0);

                return Http::response([
                    'content' => [['type' => 'text', 'text' => 'ok']],
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ], 200);
            },
        ]);

        $this->service()->chat('havuncore', 'time-me');

        $log = AIUsageLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(2000, $log->execution_time_ms);
        $this->assertIsInt($log->execution_time_ms);
    }

    public function test_chat_logs_usage_record_contains_each_documented_key(): void
    {
        // ArrayItem / ArrayItemRemoval mutations on the logUsage payload
        // would drop `tenant`, `input_tokens`, `output_tokens`, `model`.
        // Assert each key explicitly against a unique non-default value.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'payload-check']],
                'usage' => ['input_tokens' => 17, 'output_tokens' => 23],
            ], 200),
        ]);

        $this->service()->chat('havunadmin', 'Check payload keys');

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

        $stats = $this->service()->getUsageStats('havuncore', $period);

        $this->assertSame($expectVisible ? 1 : 0, $stats['total_requests']);
    }

    public function test_usage_stats_empty_window_returns_strict_zero_integers(): void
    {
        // Hard-assert exact === 0 on each numeric field. Kills CastInt
        // and IncrementInteger / DecrementInteger mutations on the
        // return-array's integer defaults.
        $stats = $this->service()->getUsageStats('empty-tenant');

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
        $service = new class($this->stopwatch, $this->breaker) extends AIProxyService {
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
        $service = new class($this->stopwatch, $this->breaker) extends AIProxyService {
            public function callLogUsage(string $tenant, array $usage, int $ms): void
            {
                $this->logUsage($tenant, $usage, $ms);
            }
        };

        $service->callLogUsage('subclass-tenant', ['input_tokens' => 3, 'output_tokens' => 2], 50);

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

        $stats = $this->service()->getUsageStats('default-window', 'something-bogus');

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

        $stats = $this->service()->getUsageStats('exact-sums', 'day');

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
        $service = new class($this->stopwatch, $this->breaker) extends AIProxyService {
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
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Asking the breaker directly, now that it is injected. Reading its
        // cache keys was the only way before, and that pinned the test to a
        // storage detail rather than to the behaviour.
        $breaker = \Mockery::mock(CircuitBreaker::class);
        $breaker->shouldReceive('isAvailable')->andReturnTrue();
        $breaker->shouldReceive('recordSuccess')->once();
        $breaker->shouldNotReceive('recordFailure');

        (new AIProxyService($this->stopwatch, $breaker))->chat('havuncore', 'ok');
    }

    public function test_chat_does_not_call_the_api_at_all_when_the_breaker_is_open(): void
    {
        Http::fake();

        $breaker = \Mockery::mock(CircuitBreaker::class);
        $breaker->shouldReceive('isAvailable')->andReturnFalse();

        try {
            (new AIProxyService($this->stopwatch, $breaker))->chat('havuncore', 'blocked');
            $this->fail('An open breaker must stop the call.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('circuit breaker is open', $e->getMessage());
        }

        // The point of a breaker is not making the request — asserting only on
        // the exception would pass even if the call had gone out first.
        Http::assertNothingSent();
    }

    public function test_check_rate_limit_default_limit_is_sixty(): void
    {
        // Force config to null so the inline default in
        // `config('services.claude.rate_limit', 60)` fires. Kills
        // DecrementInteger/IncrementInteger on the 60 default.
        config()->set('services.claude.rate_limit', null);
        Cache::flush();
        $service = $this->service();

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
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service()->chat('havuncore', 'header-check');

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
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        // Caller intentionally omits $maxTokens to exercise the default.
        $this->service()->chat('havuncore', 'default-token-check');

        // The number is asserted literally, not via the constant: this test
        // exists to catch the ceiling silently drifting away from what
        // reference/ai-proxy.md promises callers. Reading the constant would
        // make it agree with any change by construction.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $data = $request->data();
            return ($data['max_tokens'] ?? null) === 16000
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
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service()->chat('havuncore', 'custom-tokens', maxTokens: 2048);

        Http::assertSent(fn (\Illuminate\Http\Client\Request $req) => ($req->data()['max_tokens'] ?? null) === 2048);
    }

    public function test_chat_forwards_context_into_user_message(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service()->chat('havuncore', 'Ask', ['fact A', 'fact B']);

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
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        $this->service()->chat('havuncore', 'hi', [], systemPrompt: 'Custom override prompt');

        Http::assertSent(fn (\Illuminate\Http\Client\Request $req) => ($req->data()['system'] ?? null) === 'Custom override prompt');
    }
}
