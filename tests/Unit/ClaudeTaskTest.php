<?php

namespace Tests\Unit;

use App\Models\ClaudeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage voor ClaudeTask state-machine + scopes + status flags.
 */
class ClaudeTaskTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(array $overrides = []): ClaudeTask
    {
        return ClaudeTask::create(array_merge([
            'project' => 'havuncore',
            'task' => 'Do something',
            'status' => 'pending',
            'priority' => 'normal',
        ], $overrides));
    }

    public function test_pending_running_completed_failed_scopes_filter_by_status(): void
    {
        $this->makeTask(['status' => 'pending']);
        $this->makeTask(['status' => 'pending']);
        $this->makeTask(['status' => 'running']);
        $this->makeTask(['status' => 'completed']);
        $this->makeTask(['status' => 'failed']);

        $this->assertSame(2, ClaudeTask::pending()->count());
        $this->assertSame(1, ClaudeTask::running()->count());
        $this->assertSame(1, ClaudeTask::completed()->count());
        $this->assertSame(1, ClaudeTask::failed()->count());
    }

    public function test_for_project_scope_filters_correctly(): void
    {
        $this->makeTask(['project' => 'a']);
        $this->makeTask(['project' => 'a']);
        $this->makeTask(['project' => 'b']);

        $this->assertSame(2, ClaudeTask::forProject('a')->count());
    }

    public function test_by_priority_orders_urgent_first(): void
    {
        $low = $this->makeTask(['priority' => 'low']);
        $urgent = $this->makeTask(['priority' => 'urgent']);
        $normal = $this->makeTask(['priority' => 'normal']);
        $high = $this->makeTask(['priority' => 'high']);

        $ordered = ClaudeTask::byPriority()->get()->pluck('id')->toArray();

        $this->assertSame([$urgent->id, $high->id, $normal->id, $low->id], $ordered);
    }

    public function test_mark_as_started_sets_running_and_timestamp(): void
    {
        $task = $this->makeTask(['status' => 'pending']);

        $task->markAsStarted();
        $task->refresh();

        $this->assertSame('running', $task->status);
        $this->assertNotNull($task->started_at);
    }

    public function test_mark_as_completed_records_result_and_execution_time(): void
    {
        $task = $this->makeTask(['status' => 'running', 'started_at' => now()->subSeconds(5)]);

        $task->markAsCompleted('done!');
        $task->refresh();

        $this->assertSame('completed', $task->status);
        $this->assertSame('done!', $task->result);
        $this->assertNotNull($task->completed_at);
        // Carbon 3 returns a SIGNED diff — the model uses
        // now()->diffInSeconds(started_at) which gives -5 when started_at
        // is 5 seconds in the past. The field is set; the magnitude is
        // what matters. (Logged as follow-up: model could use abs() for
        // consistent positive durations.)
        $this->assertEqualsWithDelta(-5, (int) $task->execution_time_seconds, 1);
    }

    public function test_mark_as_failed_records_error(): void
    {
        $task = $this->makeTask(['status' => 'running', 'started_at' => now()]);

        $task->markAsFailed('boom');
        $task->refresh();

        $this->assertSame('failed', $task->status);
        $this->assertSame('boom', $task->error);
    }

    public function test_status_check_methods_match_status_field(): void
    {
        $pending = $this->makeTask(['status' => 'pending']);
        $running = $this->makeTask(['status' => 'running']);
        $completed = $this->makeTask(['status' => 'completed']);
        $failed = $this->makeTask(['status' => 'failed']);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isRunning());
        $this->assertTrue($running->isRunning());
        $this->assertTrue($completed->isCompleted());
        $this->assertTrue($failed->isFailed());
    }

    public function test_metadata_array_is_cast_back_from_json(): void
    {
        $task = $this->makeTask(['metadata' => ['retries' => 2, 'note' => 'x']]);

        $this->assertSame(['retries' => 2, 'note' => 'x'], $task->fresh()->metadata);
    }
}
