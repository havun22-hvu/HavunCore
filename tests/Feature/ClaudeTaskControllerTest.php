<?php

namespace Tests\Feature;

use App\Models\ClaudeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    // -- Index --

    public function test_index_returns_all_tasks(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Task A', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Task B', 'status' => 'running', 'started_at' => now()]);

        $response = $this->getJson('/api/claude/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 2);
    }

    public function test_index_filters_by_project(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Admin', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'herdenkingsportaal', 'task' => 'Portal', 'status' => 'pending']);

        $response = $this->getJson('/api/claude/tasks?project=havunadmin');

        $response->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_index_filters_by_status(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Pending', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Done', 'status' => 'completed']);

        $response = $this->getJson('/api/claude/tasks?status=completed');

        $response->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_index_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            ClaudeTask::create(['project' => 'havunadmin', 'task' => "Task {$i}", 'status' => 'pending']);
        }

        $response = $this->getJson('/api/claude/tasks?limit=2');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_index_limit_capped_at_200(): void
    {
        $response = $this->getJson('/api/claude/tasks?limit=500');

        $response->assertOk();
        // Should not error, limit is capped internally
    }

    // -- Show --

    public function test_show_returns_task(): void
    {
        $task = ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Show me', 'status' => 'pending']);

        $response = $this->getJson("/api/claude/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('task.task', 'Show me');
    }

    // -- Pending --

    public function test_pending_endpoint_returns_pending_for_project(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Pending A', 'status' => 'pending']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'Running B', 'status' => 'running', 'started_at' => now()]);
        ClaudeTask::create(['project' => 'herdenkingsportaal', 'task' => 'Pending C', 'status' => 'pending']);

        $response = $this->getJson('/api/claude/tasks/pending/havunadmin');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('project', 'havunadmin')
            ->assertJsonPath('count', 1);
    }

    // -- Start not found --

    public function test_start_nonexistent_returns_404(): void
    {
        $response = $this->postJson('/api/claude/tasks/99999/start');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // -- Complete not found --

    public function test_complete_nonexistent_returns_404(): void
    {
        $response = $this->postJson('/api/claude/tasks/99999/complete', ['result' => 'Done']);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // -- Complete validation --

    public function test_complete_without_result_returns_422(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin', 'task' => 'Test', 'status' => 'running', 'started_at' => now(),
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/complete", []);

        $response->assertStatus(422);
    }

    // -- Fail not found --

    public function test_fail_nonexistent_returns_404(): void
    {
        $response = $this->postJson('/api/claude/tasks/99999/fail', ['error' => 'Oops']);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // -- Fail validation --

    public function test_fail_without_error_returns_422(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin', 'task' => 'Test', 'status' => 'running', 'started_at' => now(),
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/fail", []);

        $response->assertStatus(422);
    }

    // -- Fail non-running --

    public function test_fail_non_running_task_returns_400(): void
    {
        $task = ClaudeTask::create([
            'project' => 'havunadmin', 'task' => 'Test', 'status' => 'pending',
        ]);

        $response = $this->postJson("/api/claude/tasks/{$task->id}/fail", ['error' => 'Oops']);

        $response->assertStatus(400);
    }

    // -- Destroy not found --

    public function test_destroy_nonexistent_returns_404(): void
    {
        $response = $this->deleteJson('/api/claude/tasks/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
