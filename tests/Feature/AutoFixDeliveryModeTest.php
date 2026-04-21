<?php

namespace Tests\Feature;

use App\Models\AutofixProposal;
use App\Services\AIProxyService;
use App\Services\AutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoFixDeliveryModeTest extends TestCase
{
    use RefreshDatabase;

    private AutoFixService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'autofix.branch_model' => true,
            'autofix.branch_prefix' => 'hotfix/autofix-',
            'autofix.dry_run_on_risk' => ['medium', 'high'],
            'autofix.snapshot_enabled' => true,
        ]);

        $proxy = $this->createMock(AIProxyService::class);
        $this->service = new class($proxy) extends AutoFixService
        {
            public function callResolveDeliveryMode(string $risk): string
            {
                return $this->resolveDeliveryMode($risk);
            }
        };
    }

    public function test_low_risk_resolves_to_branch_pr(): void
    {
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('low'));
    }

    public function test_medium_risk_resolves_to_dry_run(): void
    {
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('medium'));
    }

    public function test_high_risk_resolves_to_dry_run(): void
    {
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
    }

    public function test_branch_model_disabled_falls_back_to_direct(): void
    {
        config(['autofix.branch_model' => false]);

        $this->assertSame('direct', $this->service->callResolveDeliveryMode('low'));
        $this->assertSame('direct', $this->service->callResolveDeliveryMode('high'));
    }

    public function test_direct_push_to_main_is_disabled_by_default_config(): void
    {
        $this->assertTrue(config('autofix.branch_model'));
        $this->assertContains('medium', config('autofix.dry_run_on_risk'));
        $this->assertContains('high', config('autofix.dry_run_on_risk'));
    }

    public function test_fallback_default_when_config_missing_still_blocks_medium(): void
    {
        // Simuleer ontbrekende config-key — moet vallen op hard-coded default
        // ['medium', 'high'] in resolveDeliveryMode(). Mutation guard voor
        // ArrayItemRemoval op die default-literal.
        $all = config('autofix');
        unset($all['dry_run_on_risk']);
        config()->set('autofix', $all);

        $this->assertFalse(array_key_exists('dry_run_on_risk', config('autofix')));
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('medium'));
        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('low'));
    }

    public function test_string_config_value_is_normalised_to_array(): void
    {
        // Iemand zet per ongeluk AUTOFIX_DRY_RUN_ON_RISK=high (string) in .env
        // via een non-array env parser. (array)-cast moet dat opvangen.
        config()->set('autofix.dry_run_on_risk', 'high');

        $this->assertSame('dry_run', $this->service->callResolveDeliveryMode('high'));
        $this->assertSame('branch_pr', $this->service->callResolveDeliveryMode('medium'));
    }

    // ================================================================================
    // System-prompt content (kills ConcatOperandRemoval / Concat mutations
    // on the 4-part prompt string, line 161 in AutoFixService).
    // ================================================================================

    public function test_system_prompt_assembles_all_four_concatenated_fragments_exactly(): void
    {
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeSystemPrompt(): string
            {
                return $this->getSystemPrompt();
            }
        };

        // assertSame on the full 4-fragment concatenation — any ConcatOperandRemoval
        // or Concat mutation on line 161 drops or swaps one of the operands.
        $expected = 'Je bent een ervaren Laravel developer die productie-errors analyseert en fixes voorstelt. '
            . 'Geef alleen fixes voor duidelijke bugs, geen refactoring. '
            . 'Wees conservatief: liever geen fix dan een riskante fix. '
            . 'Markeer risk level: low (typo, null check), medium (logica wijziging), high (database/auth/betaling).';

        $this->assertSame($expected, $service->exposeSystemPrompt());
    }

    // ================================================================================
    // Risk assessment — assessRisk() parses the fix-proposal body.
    // Kills the preg_match-derived branches + heuristic fallback.
    // ================================================================================

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function riskAssessmentCases(): iterable
    {
        yield 'explicit low' => ["ANALYSIS: nothing\nRISK: low\n", 'low'];
        yield 'explicit medium uppercase' => ["RISK: MEDIUM", 'medium'];
        yield 'explicit high' => ["RISK: high\nfix here", 'high'];
        yield 'no risk + migration keyword' => ["Run the migration script", 'high'];
        yield 'no risk + auth keyword' => ["Fix for auth bypass", 'high'];
        yield 'no risk + delete keyword' => ["Issue DELETE statement", 'high'];
        yield 'no risk + benign change' => ["Fixed a typo in the view", 'medium'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('riskAssessmentCases')]
    public function test_assess_risk_handles_explicit_tags_and_keyword_heuristics(
        string $proposal,
        string $expectedRisk,
    ): void {
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeAssessRisk(string $p): string
            {
                return $this->assessRisk($p);
            }
        };

        $this->assertSame($expectedRisk, $service->exposeAssessRisk($proposal));
    }

    // ================================================================================
    // analyze() integration — kills concat mutations on branch_name, the
    // Ternary on initialStatus, and ArrayItem mutations on the context payload.
    // ================================================================================

    private function makeServiceWithProposalResponse(string $aiResponse): AutoFixService
    {
        $proxy = $this->createMock(AIProxyService::class);
        $proxy->method('chat')->willReturn(['response' => $aiResponse, 'usage' => []]);

        return new AutoFixService($proxy);
    }

    public function test_analyze_low_risk_creates_branch_pr_proposal_with_structured_branch_name(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-04-21 15:30:00');

        $service = $this->makeServiceWithProposalResponse("RISK: low\nFIX: typo");

        $proposal = $service->analyze([
            'project' => 'judotoernooi',
            'exception_class' => 'TypeError',
            'message' => 'Undefined variable $x',
            'file' => 'app/Http/Controllers/Foo.php',
            'line' => 42,
        ]);

        $this->assertNotNull($proposal);
        $this->assertSame('branch_pending', $proposal->status);
        $this->assertSame('low', $proposal->risk_level);
        $this->assertSame('branch_pr', $proposal->context['delivery_mode']);
        // The branch name concatenates prefix + project + '-' + timestamp.
        // Kills ConcatOperandRemoval / Concat on line 70.
        $this->assertSame('hotfix/autofix-judotoernooi-2026-04-21-1530', $proposal->context['branch_name']);
        $this->assertTrue($proposal->context['snapshot_required']);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_analyze_medium_risk_skips_branch_creation_and_stays_dry_run(): void
    {
        $service = $this->makeServiceWithProposalResponse("RISK: medium\nFIX: maybe");

        $proposal = $service->analyze([
            'project' => 'herdenkingsportaal',
            'exception_class' => 'RuntimeException',
            'message' => 'something',
        ]);

        $this->assertNotNull($proposal);
        $this->assertSame('dry_run', $proposal->status);
        $this->assertSame('medium', $proposal->risk_level);
        $this->assertSame('dry_run', $proposal->context['delivery_mode']);
        $this->assertNull($proposal->context['branch_name']);
    }

    public function test_analyze_caps_message_at_mb_substr_limit_sixty_five_kb(): void
    {
        // Kills IncrementInteger/DecrementInteger on the 65535 default in
        // `mb_substr($message, 0, 65535)` (line 60 / 118).
        $hugeMessage = str_repeat('xüy', 30000); // ~90 000 mb-chars

        $service = $this->makeServiceWithProposalResponse("RISK: low");

        $proposal = $service->analyze([
            'project' => 'havuncore',
            'exception_class' => 'E',
            'message' => $hugeMessage,
        ]);

        $this->assertNotNull($proposal);
        // Result is exactly the 65535-char prefix, not the full input
        // and not 65534/65536.
        $this->assertSame(65535, mb_strlen($proposal->message));
        $this->assertSame(mb_substr($hugeMessage, 0, 65535), $proposal->message);
    }

    public function test_record_fallback_stores_with_local_fallback_source_and_applied_default(): void
    {
        $proxy = $this->createMock(AIProxyService::class);
        $service = new AutoFixService($proxy);

        $proposal = $service->recordFallback([
            'project' => 'havunadmin',
            'exception_class' => 'E',
            'message' => 'local fix used',
        ]);

        $this->assertSame('havunadmin', $proposal->project);
        $this->assertSame('local_fallback', $proposal->source);
        $this->assertSame('applied', $proposal->status);
        $this->assertSame('unknown', $proposal->risk_level);
        $this->assertSame('Applied via local fallback', $proposal->result_message);
    }

    public function test_record_fallback_also_truncates_message_to_sixty_five_kb(): void
    {
        $proxy = $this->createMock(AIProxyService::class);
        $service = new AutoFixService($proxy);

        $proposal = $service->recordFallback([
            'project' => 'havuncore',
            'exception_class' => 'E',
            'message' => str_repeat('a', 70000),
        ]);

        $this->assertSame(65535, mb_strlen($proposal->message));
    }

    // ================================================================================
    // buildPrompt() — exercise each required prompt section so Concat /
    // Assignment / ConcatOperandRemoval mutations on lines 132-154 get killed.
    // ================================================================================

    public function test_build_prompt_assembles_all_documented_sections(): void
    {
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeBuildPrompt(
                string $project,
                string $class,
                string $message,
                ?string $file,
                ?int $line,
                string $trace,
                array $context,
            ): string {
                return $this->buildPrompt($project, $class, $message, $file, $line, $trace, $context);
            }
        };

        $prompt = $service->exposeBuildPrompt(
            'judotoernooi',
            'TypeError',
            'cannot sum string',
            'app/Foo.php',
            42,
            "#0 stack\n#1 frame",
            ['user_id' => 7],
        );

        // Header + 3 always-on fields.
        $this->assertStringContainsString('Analyseer deze productie-error', $prompt);
        $this->assertStringContainsString('Project: judotoernooi', $prompt);
        $this->assertStringContainsString('Exception: TypeError', $prompt);
        $this->assertStringContainsString('Message: cannot sum string', $prompt);

        // Optional fields (file/line/trace/context).
        $this->assertStringContainsString('File: app/Foo.php', $prompt);
        $this->assertStringContainsString('Line: 42', $prompt);
        $this->assertStringContainsString('Stack trace', $prompt);
        $this->assertStringContainsString('#0 stack', $prompt);
        $this->assertStringContainsString('"user_id": 7', $prompt);

        // Fixed-format footer — each line (151-154) must be present.
        $this->assertStringContainsString('RISK: low|medium|high', $prompt);
        $this->assertStringContainsString('FILE: pad/naar/bestand.php', $prompt);
        $this->assertStringContainsString('FIX:', $prompt);
        $this->assertStringContainsString('UITLEG:', $prompt);
    }

    public function test_build_prompt_truncates_stack_trace_at_two_thousand_chars(): void
    {
        // Kills Increment/Decrement on `mb_substr(..., 0, 2000)`.
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeBuildPrompt(
                string $project, string $class, string $message,
                ?string $file, ?int $line, string $trace, array $context,
            ): string {
                return $this->buildPrompt($project, $class, $message, $file, $line, $trace, $context);
            }
        };

        // Single-char trace so we can count it precisely.
        $hugeTrace = str_repeat('X', 3000);
        $prompt = $service->exposeBuildPrompt(
            'p', 'E', 'm', null, null, $hugeTrace, [],
        );

        // 2000 X's must appear; 2001 X's must not.
        $this->assertStringContainsString(str_repeat('X', 2000), $prompt);
        $this->assertStringNotContainsString(str_repeat('X', 2001), $prompt);
    }

    public function test_build_prompt_skips_empty_context_section(): void
    {
        // `if (!empty($context))` branch. Kills IfNegation on line 146.
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeBuildPrompt(
                string $project, string $class, string $message,
                ?string $file, ?int $line, string $trace, array $context,
            ): string {
                return $this->buildPrompt($project, $class, $message, $file, $line, $trace, $context);
            }
        };

        $prompt = $service->exposeBuildPrompt('p', 'E', 'm', null, null, '', []);

        $this->assertStringNotContainsString('Context:', $prompt);
    }

    // ================================================================================
    // analyze() merges caller context into proposal.context (UnwrapArrayMerge).
    // ================================================================================

    public function test_analyze_merges_caller_context_into_proposal_context(): void
    {
        $service = $this->makeServiceWithProposalResponse("RISK: low");

        $proposal = $service->analyze([
            'project' => 'havuncore',
            'exception_class' => 'E',
            'message' => 'm',
            'context' => ['caller_key' => 'caller-value', 'request_id' => 'req-42'],
        ]);

        $this->assertNotNull($proposal);
        // Kills UnwrapArrayMerge — without merge only delivery_mode/branch_name
        // would be in context and caller_key would vanish.
        $this->assertSame('caller-value', $proposal->context['caller_key']);
        $this->assertSame('req-42', $proposal->context['request_id']);
        // The service's own keys must also survive the merge.
        $this->assertArrayHasKey('delivery_mode', $proposal->context);
        $this->assertArrayHasKey('snapshot_required', $proposal->context);
    }

    // ================================================================================
    // Risk-assessment preg_match /i flag: a test that distinguishes the
    // regex-hit path from the heuristic-fallback path.
    // ================================================================================

    public function test_assess_risk_uppercase_low_tag_matches_case_insensitively(): void
    {
        // Origineel: /RISK:\s*(low|medium|high)/i matcht "LOW" -> returns 'low'.
        // Mutatie: drop /i flag -> no match -> heuristic fallback -> 'medium'.
        // Strict 'low' assertion kills the PregMatchRemoveFlags mutation.
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeAssessRisk(string $p): string
            {
                return $this->assessRisk($p);
            }
        };

        $this->assertSame('low', $service->exposeAssessRisk("RISK: LOW\nFIX: trivial"));
    }

    // ================================================================================
    // sendNotification — protected method, Log::info payload contract.
    // ================================================================================

    public function test_send_notification_is_reachable_by_a_subclass_and_logs_full_context(): void
    {
        // Kills ProtectedVisibility on line 206 + ArrayItem/ArrayItemRemoval
        // on the Log::info context payload (lines 209-212).
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function callSendNotification(AutofixProposal $p): void
            {
                $this->sendNotification($p);
            }
        };

        $proposal = AutofixProposal::create([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'm',
            'status' => 'branch_pending',
            'risk_level' => 'low',
            'source' => 'central',
        ]);

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($proposal) {
                return str_contains($message, 'AutoFix [branch_pending]')
                    && str_contains($message, 'havuncore')
                    && str_contains($message, 'RuntimeException')
                    && ($context['proposal_id'] ?? null) === $proposal->id
                    && ($context['risk'] ?? null) === 'low'
                    && ($context['source'] ?? null) === 'central';
            });

        $service->callSendNotification($proposal);
    }

    public function test_analyze_logs_info_with_proposal_id_and_risk_keys(): void
    {
        // Kills ArrayItem / ArrayItemRemoval on Log::info context (lines
        // 76-77) plus the CastBool on `(bool) config('autofix.snapshot_enabled')`
        // via an explicit true assertion.
        $service = $this->makeServiceWithProposalResponse("RISK: low");

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'AutoFix: proposal created for havuncore')
                    && isset($context['proposal_id'])
                    && is_int($context['proposal_id'])
                    && $context['proposal_id'] > 0
                    && ($context['risk'] ?? null) === 'low';
            });

        $proposal = $service->analyze([
            'project' => 'havuncore',
            'exception_class' => 'E',
            'message' => 'm',
        ]);

        // Strict === true kills CastBool mutation: without (bool) on a
        // truthy non-bool config value, assertTrue($x === true) breaks
        // on int(1) / string('1') / etc.
        $this->assertTrue($proposal->context['snapshot_required'] === true);
    }

    public function test_build_prompt_emits_stack_trace_section_with_exact_label(): void
    {
        // Kills Concat + ConcatOperandRemoval on line 144
        // (`"\nStack trace (eerste 2000 tekens):\n" . mb_substr(...)`).
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeBuildPrompt(
                string $project, string $class, string $message,
                ?string $file, ?int $line, string $trace, array $context,
            ): string {
                return $this->buildPrompt($project, $class, $message, $file, $line, $trace, $context);
            }
        };

        $prompt = $service->exposeBuildPrompt('p', 'E', 'm', null, null, 'TRACE-BODY-MARK', []);

        // assertStringContainsString on the *literal* label text kills
        // ConcatOperandRemoval (drops the label) and the MBString mutator
        // that swaps mb_substr for substr (which would still return
        // 'TRACE-BODY-MARK', so a label-presence check is what separates
        // the two paths).
        $this->assertStringContainsString("\nStack trace (eerste 2000 tekens):\nTRACE-BODY-MARK\n", $prompt);
    }

    public function test_build_prompt_context_block_uses_literal_label_and_pretty_json(): void
    {
        // Kills Concat on line 147 — both parts of `"\nContext: " . json_encode(...)`.
        $service = new class($this->createMock(AIProxyService::class)) extends AutoFixService
        {
            public function exposeBuildPrompt(
                string $project, string $class, string $message,
                ?string $file, ?int $line, string $trace, array $context,
            ): string {
                return $this->buildPrompt($project, $class, $message, $file, $line, $trace, $context);
            }
        };

        $prompt = $service->exposeBuildPrompt(
            'p', 'E', 'm', null, null, '', ['flag' => true, 'count' => 3],
        );

        // Literal label + JSON pretty output — both operands must survive.
        $this->assertMatchesRegularExpression(
            '/\nContext: \{\s+"flag": true,\s+"count": 3\s+\}\n/',
            $prompt,
        );
    }

    public function test_record_fallback_null_coalesce_defaults_file_line_context(): void
    {
        // Kills Coalesce mutations on lines 123-124 (`?? null` vs `?? ''`)
        // by calling recordFallback without file/line/context and asserting
        // that the DB column ends up as `null`, not an empty string.
        $proxy = $this->createMock(AIProxyService::class);
        $service = new AutoFixService($proxy);

        $proposal = $service->recordFallback([
            'project' => 'havuncore',
            'exception_class' => 'E',
            'message' => 'no-file-or-line',
        ]);

        $this->assertNull($proposal->file);
        $this->assertNull($proposal->line);
        $this->assertNull($proposal->context);
    }

    public function test_analyze_returns_null_when_rate_limited(): void
    {
        // Kills Coalesce on line 33-34 by exercising the null-path in
        // $errorData['file']/['line'] via the rate-limit early-return.
        // Contract: same fingerprint within the window -> second call
        // returns null, regardless of file being null.
        $service = $this->makeServiceWithProposalResponse("RISK: low");

        $first = $service->analyze([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'boom',
            // no file / no line
        ]);
        $this->assertNotNull($first);

        $second = $service->analyze([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'boom',
        ]);
        $this->assertNull($second);
    }
}
