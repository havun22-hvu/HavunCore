<?php

namespace Tests\Feature;

use App\Models\AutofixProposal;
use App\Services\AIProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Coverage voor AutoFixController + AutoFixService — analyze/report/fallback/list
 * via HTTP. AIProxyService is gemockt zodat we geen externe Anthropic call doen.
 */
class AutoFixApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'autofix.branch_model' => true,
            'autofix.branch_prefix' => 'hotfix/autofix-',
            'autofix.dry_run_on_risk' => ['medium', 'high'],
            'autofix.snapshot_enabled' => true,
            // AIProxyService constructor leest deze key — vereist non-null in tests
            'services.claude.api_key' => 'test-key',
        ]);

        // Default neutral mock: voorkomt dat enkel-leesroutes (proposals) de
        // echte Anthropic-binding raken via container DI ladder.
        $this->fakeAi('RISK: low');
    }

    private function fakeAi(string $reply): void
    {
        $mock = Mockery::mock(AIProxyService::class);
        $mock->shouldReceive('chat')->andReturn(['response' => $reply]);
        $this->app->instance(AIProxyService::class, $mock);
    }

    public function test_analyze_validates_required_fields(): void
    {
        $this->postJson('/api/autofix/analyze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project', 'exception_class', 'message']);
    }

    public function test_analyze_creates_proposal_for_low_risk(): void
    {
        $this->fakeAi("RISK: low\nFILE: app/X.php\nFIX:\n```php\n// fix\n```\nUITLEG: typo");

        $response = $this->postJson('/api/autofix/analyze', [
            'project' => 'judotoernooi',
            'exception_class' => 'TypeError',
            'message' => 'null arg',
            'file' => '/app/X.php',
            'line' => 42,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('proposal.risk_level', 'low')
            ->assertJsonPath('proposal.status', 'branch_pending');

        $this->assertDatabaseHas('autofix_proposals', [
            'project' => 'judotoernooi',
            'exception_class' => 'TypeError',
            'risk_level' => 'low',
            'source' => 'central',
        ]);
    }

    public function test_analyze_returns_dry_run_status_for_high_risk(): void
    {
        $this->fakeAi("RISK: high\nFIX: DROP TABLE users;");

        $response = $this->postJson('/api/autofix/analyze', [
            'project' => 'havuncore',
            'exception_class' => 'QueryException',
            'message' => 'foo',
            'file' => '/app/Q.php',
            'line' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('proposal.risk_level', 'high')
            ->assertJsonPath('proposal.status', 'dry_run');
    }

    public function test_analyze_returns_429_when_rate_limited(): void
    {
        // Voorgaand proposal binnen rate-limit window blokkeert nieuwe analyse
        AutofixProposal::create([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'prev',
            'file' => '/app/A.php',
            'line' => 7,
            'status' => 'pending',
        ]);

        $this->postJson('/api/autofix/analyze', [
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'new',
            'file' => '/app/A.php',
            'line' => 7,
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'rate_limited_or_analysis_failed');
    }

    public function test_analyze_returns_429_when_ai_throws(): void
    {
        $mock = Mockery::mock(AIProxyService::class);
        $mock->shouldReceive('chat')->andThrow(new \RuntimeException('upstream down'));
        $this->app->instance(AIProxyService::class, $mock);

        $this->postJson('/api/autofix/analyze', [
            'project' => 'havuncore',
            'exception_class' => 'X',
            'message' => 'y',
            'file' => '/app/Z.php',
            'line' => 1,
        ])->assertStatus(429);
    }

    public function test_report_updates_proposal_status(): void
    {
        $proposal = AutofixProposal::create([
            'project' => 'havuncore',
            'exception_class' => 'X',
            'message' => 'm',
            'status' => 'branch_pending',
        ]);

        $this->postJson('/api/autofix/report', [
            'proposal_id' => $proposal->id,
            'status' => 'applied',
            'result_message' => 'OK',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('applied', $proposal->fresh()->status);
        $this->assertSame('OK', $proposal->fresh()->result_message);
    }

    public function test_report_validates_status_enum(): void
    {
        $this->postJson('/api/autofix/report', [
            'proposal_id' => 1,
            'status' => 'haxxor',
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_report_silently_ignores_unknown_proposal_id(): void
    {
        // Bewust geen 404 — voorkomt enumeratie van geldige proposal-IDs
        $this->postJson('/api/autofix/report', [
            'proposal_id' => 99999,
            'status' => 'applied',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_fallback_records_local_proposal(): void
    {
        $response = $this->postJson('/api/autofix/fallback', [
            'project' => 'judotoernooi',
            'exception_class' => 'OutOfMemoryError',
            'message' => 'OOM',
            'status' => 'applied',
            'risk_level' => 'medium',
            'fix_proposal' => 'increase memory_limit',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('autofix_proposals', [
            'project' => 'judotoernooi',
            'source' => 'local_fallback',
            'status' => 'applied',
            'result_message' => 'Applied via local fallback',
        ]);
    }

    public function test_proposals_filters_by_project_and_status(): void
    {
        AutofixProposal::create([
            'project' => 'a', 'exception_class' => 'X', 'message' => 'm', 'status' => 'pending',
        ]);
        AutofixProposal::create([
            'project' => 'a', 'exception_class' => 'X', 'message' => 'm', 'status' => 'applied',
        ]);
        AutofixProposal::create([
            'project' => 'b', 'exception_class' => 'X', 'message' => 'm', 'status' => 'pending',
        ]);

        $response = $this->getJson('/api/autofix/proposals?project=a&status=pending');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1);
    }

    public function test_proposals_returns_paginated_list(): void
    {
        for ($i = 0; $i < 3; $i++) {
            AutofixProposal::create([
                'project' => 'p', 'exception_class' => 'X',
                'message' => 'm', 'status' => 'pending',
            ]);
        }

        $this->getJson('/api/autofix/proposals?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 3);
    }
}
