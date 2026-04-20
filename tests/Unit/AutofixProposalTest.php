<?php

namespace Tests\Unit;

use App\Models\AutofixProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor AutofixProposal scopes + rate-limit. Toegevoegd 2026-04-20
 * voor HavunCore Unit-coverage push.
 */
class AutofixProposalTest extends TestCase
{
    use RefreshDatabase;

    private function makeProposal(array $overrides = []): AutofixProposal
    {
        return AutofixProposal::create(array_merge([
            'project' => 'havuncore',
            'exception_class' => 'RuntimeException',
            'message' => 'test',
            'file' => '/app/X.php',
            'line' => 10,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_for_project_scope_filters_by_project(): void
    {
        $this->makeProposal(['project' => 'havuncore']);
        $this->makeProposal(['project' => 'havuncore']);
        $this->makeProposal(['project' => 'judotoernooi']);

        $this->assertSame(2, AutofixProposal::forProject('havuncore')->count());
        $this->assertSame(1, AutofixProposal::forProject('judotoernooi')->count());
    }

    public function test_pending_scope_filters_by_status(): void
    {
        $this->makeProposal(['status' => 'pending']);
        $this->makeProposal(['status' => 'pending']);
        $this->makeProposal(['status' => 'applied']);

        $this->assertSame(2, AutofixProposal::pending()->count());
    }

    public function test_context_array_is_cast_back_from_json(): void
    {
        $proposal = $this->makeProposal(['context' => ['key' => 'value', 'n' => 42]]);

        $fresh = AutofixProposal::find($proposal->id);
        $this->assertSame(['key' => 'value', 'n' => 42], $fresh->context);
    }

    public function test_is_rate_limited_returns_true_for_recent_match(): void
    {
        $this->makeProposal([
            'project' => 'p',
            'exception_class' => 'RuntimeException',
            'file' => '/x.php',
            'line' => 99,
        ]);

        $this->assertTrue(
            AutofixProposal::isRateLimited('p', 'RuntimeException', '/x.php', 99)
        );
    }

    public function test_is_rate_limited_returns_false_for_old_proposal(): void
    {
        $proposal = $this->makeProposal([
            'project' => 'p',
            'exception_class' => 'RuntimeException',
            'file' => '/x.php',
            'line' => 99,
        ]);
        // Bypass auto-timestamp on update by going via the underlying table.
        \DB::table('autofix_proposals')
            ->where('id', $proposal->id)
            ->update(['created_at' => now()->subHours(2)]);

        $this->assertFalse(
            AutofixProposal::isRateLimited('p', 'RuntimeException', '/x.php', 99)
        );
    }

    public function test_is_rate_limited_returns_false_for_different_line(): void
    {
        $this->makeProposal([
            'project' => 'p',
            'exception_class' => 'RuntimeException',
            'file' => '/x.php',
            'line' => 99,
        ]);

        $this->assertFalse(
            AutofixProposal::isRateLimited('p', 'RuntimeException', '/x.php', 100),
            'Different line = different error site → must allow new proposal.'
        );
    }
}
