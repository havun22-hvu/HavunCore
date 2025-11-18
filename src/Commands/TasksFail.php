<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\TaskOrchestrator;
use Havun\Core\Services\MCPService;

class TasksFail extends Command
{
    protected $signature = 'havun:tasks:fail
                            {task_id : The task ID to mark as failed}
                            {reason : Reason for failure}';

    protected $description = 'Mark a task as failed and notify HavunCore';

    public function handle(): int
    {
        $taskId = $this->argument('task_id');
        $reason = $this->argument('reason');

        $this->error("❌ Marking task {$taskId} as failed...");

        try {
            $orchestrationId = $this->findOrchestrationId($taskId);

            if (!$orchestrationId) {
                $this->error("❌ Could not find orchestration for task {$taskId}");
                return self::FAILURE;
            }

            $orchestrator = app(TaskOrchestrator::class);

            $result = [
                'failed_by' => config('app.name', 'Unknown'),
                'reason' => $reason,
                'failed_at' => now()->toIso8601String(),
            ];

            $success = $orchestrator->updateTaskStatus(
                orchestrationId: $orchestrationId,
                taskId: $taskId,
                status: 'failed',
                result: $result
            );

            if (!$success) {
                $this->error("❌ Failed to update task status");
                return self::FAILURE;
            }

            // Notify HavunCore via MCP
            $mcp = app(MCPService::class);
            $mcp->storeMessage(
                project: 'HavunCore',
                content: "❌ Task {$taskId} failed in " . config('app.name') . "\n\nReason: {$reason}",
                tags: ['task_failed', $orchestrationId, $taskId]
            );

            $this->error('❌ Task marked as failed');
            $this->line('   HavunCore has been notified');
            $this->newLine();
            $this->line('Reason: ' . $reason);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to mark task as failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function findOrchestrationId(string $taskId): ?string
    {
        $mcp = app(MCPService::class);
        $projectName = config('app.name', 'HavunCore');

        $messages = $mcp->getMessages($projectName, [$taskId]);

        foreach ($messages as $message) {
            foreach ($message['tags'] ?? [] as $tag) {
                if (str_starts_with($tag, 'orch_')) {
                    return $tag;
                }
            }
        }

        $orchestrationsPath = storage_path('orchestrations');
        if (is_dir($orchestrationsPath)) {
            $files = glob($orchestrationsPath . '/*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                foreach ($data['tasks'] ?? [] as $task) {
                    if ($task['id'] === $taskId) {
                        return $data['id'];
                    }
                }
            }
        }

        return null;
    }
}
