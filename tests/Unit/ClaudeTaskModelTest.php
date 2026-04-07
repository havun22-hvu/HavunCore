<?php

namespace Tests\Unit;

use App\Models\ClaudeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeTaskModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_running(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'A', 'status' => 'running', 'started_at' => now()]);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'B', 'status' => 'pending']);

        $this->assertCount(1, ClaudeTask::running()->get());
    }

    public function test_scope_completed(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'A', 'status' => 'completed']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'B', 'status' => 'pending']);

        $this->assertCount(1, ClaudeTask::completed()->get());
    }

    public function test_scope_failed(): void
    {
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'A', 'status' => 'failed']);
        ClaudeTask::create(['project' => 'havunadmin', 'task' => 'B', 'status' => 'running', 'started_at' => now()]);

        $this->assertCount(1, ClaudeTask::failed()->get());
    }
}
