<?php

namespace Tests\Feature;

use App\Models\ClaudeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeTaskTest extends TestCase
{
    use RefreshDatabase;

    // -- Model Logic --

    public function test_task_starts_as_pending(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task description',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $this->assertTrue($task->isPending());
        $this->assertFalse($task->isRunning());
        $this->assertFalse($task->isCompleted());
        $this->assertFalse($task->isFailed());
    }

    public function test_mark_as_started_transitions_to_running(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'pending',
        ]);

        $task->markAsStarted();

        $this->assertTrue($task->fresh()->isRunning());
        $this->assertNotNull($task->fresh()->started_at);
    }

    public function test_mark_as_completed_stores_result(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'running',
            'started_at' => now()->subMinute(),
        ]);

        $task->markAsCompleted('Task done successfully');

        $fresh = $task->fresh();
        $this->assertTrue($fresh->isCompleted());
        $this->assertEquals('Task done successfully', $fresh->result);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->execution_time_seconds);
    }

    public function test_mark_as_failed_stores_error(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'running',
            'started_at' => now()->subMinute(),
        ]);

        $task->markAsFailed('Something went wrong');

        $fresh = $task->fresh();
        $this->assertTrue($fresh->isFailed());
        $this->assertEquals('Something went wrong', $fresh->error);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_metadata_is_stored_as_json(): void
    {
        $metadata = ['context' => 'test', 'files' => ['a.php', 'b.php']];

        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $task->fresh()->metadata);
    }

    // -- Scopes --

    public function test_pending_scope_filters_correctly(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Pending', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Running', 'status' => 'running']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Completed', 'status' => 'completed']);

        $pending = ClaudeTask::pending()->get();
        $this->assertCount(1, $pending);
        $this->assertEquals('Pending', $pending->first()->task);
    }

    public function test_for_project_scope_filters_correctly(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Admin task', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'herdenkingsportaal', 'task' => 'Portal task', 'status' => 'pending']);

        $tasks = ClaudeTask::forProject('havunadmin')->get();
        $this->assertCount(1, $tasks);
        $this->assertEquals('Admin task', $tasks->first()->task);
    }

    // -- API Endpoints --

    public function test_create_task_via_api(): void
    {
        $response = $this->postJson('/api/claude/tasks', [
            'project' => 'havunadmin',
            'task' => 'Create a new feature for the admin panel',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('task.project', 'havunadmin')
            ->assertJsonPath('task.priority', 'high')
            ->assertJsonPath('task.status', 'pending');
    }

    public function test_create_task_validation_rejects_invalid_project(): void
    {
        $response = $this->postJson('/api/claude/tasks', [
            'project' => 'nonexistent',
            'task' => 'Some task',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_task_validation_rejects_short_task(): void
    {
        $response = $this->postJson('/api/claude/tasks', [
            'project' => 'havunadmin',
            'task' => 'Hi',
        ]);

        $response->assertStatus(422);
    }

    public function test_start_task_via_api(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/start");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('task.status', 'running');
    }

    public function test_start_already_running_task_returns_error(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/start");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_complete_task_via_api(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/complete", [
            'result' => 'Task completed with output',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('task.status', 'completed');
    }

    public function test_fail_task_via_api(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/fail", [
            'error' => 'File not found',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('task.status', 'failed');
    }

    public function test_complete_non_running_task_returns_error(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Test task',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/complete", [
            'result' => 'Done',
        ]);

        $response->assertStatus(400);
    }

    public function test_pending_tasks_can_be_queried_by_project(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Task A', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Task B', 'status' => 'running']);
        ClaudeTask::create(['project' => 'herdenkingsportaal', 'task' => 'Task C', 'status' => 'pending']);

        // Query pending tasks directly (API endpoint uses byPriority which requires MySQL FIELD())
        $tasks = ClaudeTask::pending()->forProject('havunadmin')->get();

        $this->assertCount(1, $tasks);
        $this->assertEquals('Task A', $tasks->first()->task);
    }

    public function test_show_task_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/claude/tasks/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_delete_task_via_api(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin',
            'task' => 'Delete me',
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/claude/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('claude_tasks', ['id' => $task->id]);
    }
}
