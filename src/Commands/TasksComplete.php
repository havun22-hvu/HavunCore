<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\TaskOrchestrator;
use Havun\Core\Services\MCPService;

class TasksComplete extends Command
{
    protected $signature = 'havun:tasks:complete
                            {task_id : The task ID to mark as complete}
                            {--message= : Optional completion message}
                            {--files=* : Files that were created/modified}';

    protected $description = 'Mark a task as completed and notify HavunCore';

    public function handle(): int
    {
        $taskId = $this->argument('task_id');
        $message = $this->option('message');
        $files = $this->option('files');

        $this->info("✅ Marking task {$taskId} as complete...");

        try {
            // Extract orchestration ID from task ID (format: task_001 from orch_xxx)
            // We need to find the orchestration this task belongs to
            $orchestrationId = $this->findOrchestrationId($taskId);

            if (!$orchestrationId) {
                $this->error("❌ Could not find orchestration for task {$taskId}");
                $this->line('   Make sure you are in the HavunCore project or provide orchestration ID');
                return self::FAILURE;
            }

            $orchestrator = app(TaskOrchestrator::class);

            $result = [
                'completed_by' => config('app.name', 'Unknown'),
                'message' => $message,
                'files' => $files,
                'completed_at' => now()->toIso8601String(),
            ];

            $success = $orchestrator->updateTaskStatus(
                orchestrationId: $orchestrationId,
                taskId: $taskId,
                status: 'completed',
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
                content: "✅ Task {$taskId} completed by " . config('app.name') .
                         ($message ? "\n\nMessage: {$message}" : ''),
                tags: ['task_completed', $orchestrationId, $taskId]
            );

            $this->info('✅ Task marked as complete!');
            $this->line('   HavunCore has been notified');

            if (!empty($files)) {
                $this->newLine();
                $this->line('Files modified:');
                foreach ($files as $file) {
                    $this->line("  • {$file}");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to complete task: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function findOrchestrationId(string $taskId): ?string
    {
        // Try to find orchestration from MCP messages
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

        // If running in HavunCore, try to find from orchestration files
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
