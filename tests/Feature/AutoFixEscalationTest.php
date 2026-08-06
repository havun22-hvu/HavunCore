<?php

namespace Tests\Feature;

use App\Models\ClaudeTask;
use App\Services\AIProxyService;
use App\Services\AutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When AutoFix cannot produce a proposal itself, the error currently ends in a
 * log line and nowhere else. It should leave a task behind so Claude CLI can
 * pick it up on Henk's machine.
 *
 * The dedup rule is the load-bearing part: on 2026-08-05 the AI proxy failed 46
 * times in a row on the same broken model. Escalating naively would have queued
 * 46 identical tasks.
 */
class AutoFixEscalationTest extends TestCase
{
    use RefreshDatabase;

    private const ERROR = [
        'project' => 'judotoernooi',
        'exception_class' => 'RuntimeException',
        'message' => 'Poule-indeling faalt bij oneven aantal deelnemers',
        'file' => 'app/Services/PouleService.php',
        'line' => 88,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('autofix.escalate_projects', ['judotoernooi', 'herdenkingsportaal']);
    }

    private function serviceThatCannotAnalyse(): AutoFixService
    {
        $proxy = $this->createMock(AIProxyService::class);
        $proxy->method('chat')->willThrowException(new \Exception('Claude API error: 404'));

        return new AutoFixService($proxy);
    }

    public function test_a_failed_analysis_leaves_a_task_for_claude_cli(): void
    {
        $this->serviceThatCannotAnalyse()->analyze(self::ERROR);

        $task = ClaudeTask::where('project', 'judotoernooi')->first();

        $this->assertNotNull($task, 'A failed analysis must escalate, not just log.');
        $this->assertSame('pending', $task->status);
        // Whoever picks this up needs to find the error without guessing.
        $this->assertStringContainsString('RuntimeException', $task->task);
        $this->assertStringContainsString('PouleService.php', $task->task);
        $this->assertStringContainsString('88', $task->task);
        $this->assertSame('autofix', $task->created_by);
    }

    public function test_the_same_error_twice_does_not_queue_two_tasks(): void
    {
        $service = $this->serviceThatCannotAnalyse();

        $service->analyze(self::ERROR);
        $service->analyze(self::ERROR);

        $this->assertSame(1, ClaudeTask::where('project', 'judotoernooi')->count());
    }

    public function test_a_different_error_in_the_same_project_does_queue_a_second_task(): void
    {
        $service = $this->serviceThatCannotAnalyse();

        $service->analyze(self::ERROR);
        $service->analyze([...self::ERROR, 'exception_class' => 'TypeError', 'line' => 120]);

        $this->assertSame(2, ClaudeTask::where('project', 'judotoernooi')->count());
    }

    public function test_a_resolved_task_does_not_block_the_error_returning(): void
    {
        $service = $this->serviceThatCannotAnalyse();

        $service->analyze(self::ERROR);
        ClaudeTask::where('project', 'judotoernooi')->update(['status' => 'completed']);
        $service->analyze(self::ERROR);

        // The fix apparently did not hold, so it deserves a fresh task.
        $this->assertSame(2, ClaudeTask::where('project', 'judotoernooi')->count());
    }

    public function test_a_project_outside_the_list_is_not_escalated(): void
    {
        $this->serviceThatCannotAnalyse()->analyze([...self::ERROR, 'project' => 'havunadmin']);

        $this->assertSame(0, ClaudeTask::count());
    }

    public function test_escalation_is_off_when_no_projects_are_configured(): void
    {
        // An unset list must mean "nobody", not "everybody".
        config()->set('autofix.escalate_projects', []);

        $this->serviceThatCannotAnalyse()->analyze(self::ERROR);

        $this->assertSame(0, ClaudeTask::count());
    }
}
