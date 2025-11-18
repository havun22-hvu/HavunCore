<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\MCPService;

class TasksCheck extends Command
{
    protected $signature = 'havun:tasks:check
                            {--auto : Automatically execute tasks without confirmation}
                            {--filter= : Filter tasks by orchestration ID}';

    protected $description = 'Check for pending tasks from HavunCore orchestration';

    public function handle(): int
    {
        $this->info('📥 Checking for tasks from HavunCore...');
        $this->newLine();

        try {
            $mcp = app(MCPService::class);
            $projectName = config('app.name', 'Unknown');

            // Get messages tagged as tasks for this project
            $messages = $mcp->getMessages($projectName, ['task']);

            if (empty($messages)) {
                $this->warn('⚠️  No pending tasks found');
                $this->line('   HavunCore will notify you when tasks are ready');
                return self::SUCCESS;
            }

            // Filter by orchestration if specified
            if ($filter = $this->option('filter')) {
                $messages = array_filter($messages, function ($msg) use ($filter) {
                    return isset($msg['tags']) && in_array($filter, $msg['tags']);
                });
            }

            $this->info('📋 Pending tasks (' . count($messages) . '):');
            $this->newLine();

            foreach ($messages as $index => $message) {
                $this->displayTask($index + 1, $message);
                $this->newLine();
            }

            if ($this->option('auto')) {
                $this->info('🤖 Auto-execution mode: displaying tasks for Claude to execute');
                return self::SUCCESS;
            }

            $choice = $this->ask('Which task to display? (number, or "all" to see all, "exit" to quit)');

            if ($choice === 'exit') {
                return self::SUCCESS;
            }

            if ($choice === 'all') {
                foreach ($messages as $message) {
                    $this->line(str_repeat('=', 80));
                    $this->line($message['content']);
                    $this->line(str_repeat('=', 80));
                    $this->newLine();
                }
            } elseif (is_numeric($choice) && isset($messages[$choice - 1])) {
                $this->line(str_repeat('=', 80));
                $this->line($messages[$choice - 1]['content']);
                $this->line(str_repeat('=', 80));
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to check tasks: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayTask(int $number, array $message): void
    {
        // Parse task ID and priority from tags
        $taskId = null;
        $orchestrationId = null;

        foreach ($message['tags'] ?? [] as $tag) {
            if (str_starts_with($tag, 'task_')) {
                $taskId = $tag;
            }
            if (str_starts_with($tag, 'orch_')) {
                $orchestrationId = $tag;
            }
        }

        // Extract priority and description from content
        $lines = explode("\n", $message['content']);
        $description = null;
        $priority = 'MEDIUM';

        foreach ($lines as $line) {
            if (str_contains($line, '**Priority:**')) {
                preg_match('/\*\*Priority:\*\* (.+)/', $line, $matches);
                $priority = $matches[1] ?? 'MEDIUM';
            }
            if (str_contains($line, '## Description')) {
                $nextLineIndex = array_search($line, $lines) + 2;
                $description = $lines[$nextLineIndex] ?? null;
            }
        }

        $priorityColor = match (strtoupper(trim($priority))) {
            'HIGH' => '<fg=red>HIGH</>',
            'LOW' => '<fg=gray>LOW</>',
            default => '<fg=yellow>MEDIUM</>',
        };

        $this->line("[{$number}] {$priorityColor} - {$taskId}");
        if ($description) {
            $this->line("    {$description}");
        }
        if ($orchestrationId) {
            $this->line("    Orchestration: {$orchestrationId}");
        }
    }
}
