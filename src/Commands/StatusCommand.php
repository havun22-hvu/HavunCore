<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\TaskOrchestrator;

class StatusCommand extends Command
{
    protected $signature = 'havun:status
                            {orchestration_id? : Specific orchestration to check}
                            {--all : Include completed orchestrations}
                            {--json : Output as JSON}
                            {--watch : Continuously watch status (refresh every 10s)}';

    protected $description = 'Monitor orchestration and task status';

    public function handle(): int
    {
        if ($this->option('watch')) {
            return $this->watchStatus();
        }

        try {
            $orchestrator = app(TaskOrchestrator::class);
            $orchestrationId = $this->argument('orchestration_id');

            if ($orchestrationId) {
                return $this->showSingleOrchestration($orchestrator, $orchestrationId);
            } else {
                return $this->showAllOrchestrations($orchestrator);
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to get status: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function showSingleOrchestration(TaskOrchestrator $orchestrator, string $orchestrationId): int
    {
        $orchestration = $orchestrator->getStatus($orchestrationId);

        if (!$orchestration) {
            $this->error("❌ Orchestration {$orchestrationId} not found");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($orchestration, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayOrchestrationDetails($orchestration);

        return self::SUCCESS;
    }

    private function showAllOrchestrations(TaskOrchestrator $orchestrator): int
    {
        $orchestrations = $orchestrator->listOrchestrations($this->option('all'));

        if (empty($orchestrations)) {
            $this->warn('⚠️  No orchestrations found');
            $this->line('   Create one with: php artisan havun:orchestrate "<description>"');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($orchestrations, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('🎯 Active Orchestrations:');
        $this->newLine();

        $headers = ['ID', 'Status', 'Progress', 'Tasks', 'Created', 'Duration'];
        $rows = [];

        foreach ($orchestrations as $orch) {
            $progress = $orch['total_tasks'] > 0
                ? round(($orch['completed_tasks'] / $orch['total_tasks']) * 100)
                : 0;

            $status = match ($orch['status']) {
                'completed' => '<fg=green>COMPLETED</>',
                'in_progress' => '<fg=yellow>IN PROGRESS</>',
                'failed' => '<fg=red>FAILED</>',
                default => '<fg=gray>PENDING</>',
            };

            $duration = $this->calculateDuration($orch);

            $rows[] = [
                substr($orch['id'], 0, 20),
                $status,
                $progress . '%',
                "{$orch['completed_tasks']}/{$orch['total_tasks']}",
                date('Y-m-d H:i', strtotime($orch['created_at'])),
                $duration,
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->line('Use: php artisan havun:status <id> for detailed view');

        return self::SUCCESS;
    }

    private function displayOrchestrationDetails(array $orchestration): void
    {
        $this->info('🎯 Orchestration Status');
        $this->newLine();

        $this->line("ID: {$orchestration['id']}");
        $this->line("Description: {$orchestration['description']}");

        $statusIcon = match ($orchestration['status']) {
            'completed' => '✅',
            'in_progress' => '⏳',
            'failed' => '❌',
            default => '⏸️',
        };

        $this->line("Status: {$statusIcon} " . strtoupper($orchestration['status']));

        $progress = $orchestration['total_tasks'] > 0
            ? round(($orchestration['completed_tasks'] / $orchestration['total_tasks']) * 100)
            : 0;

        $this->line("Progress: {$progress}% ({$orchestration['completed_tasks']}/{$orchestration['total_tasks']} tasks)");

        $this->newLine();
        $this->line("Created: " . date('Y-m-d H:i:s', strtotime($orchestration['created_at'])));

        if ($orchestration['started_at']) {
            $this->line("Started: " . date('Y-m-d H:i:s', strtotime($orchestration['started_at'])));
        }

        if ($orchestration['completed_at']) {
            $this->line("Completed: " . date('Y-m-d H:i:s', strtotime($orchestration['completed_at'])));
        }

        $this->newLine();
        $this->line("Estimated duration: {$orchestration['estimated_duration_minutes']} minutes");

        if ($orchestration['status'] !== 'pending') {
            $elapsed = $this->calculateElapsedMinutes($orchestration);
            $this->line("Elapsed time: {$elapsed} minutes");

            if ($orchestration['status'] !== 'completed') {
                $remaining = max(0, $orchestration['estimated_duration_minutes'] - $elapsed);
                $this->line("Estimated remaining: {$remaining} minutes");

                if ($orchestration['started_at']) {
                    $estimatedCompletion = strtotime($orchestration['started_at']) + ($orchestration['estimated_duration_minutes'] * 60);
                    $this->line("Estimated completion: " . date('H:i', $estimatedCompletion));
                }
            }
        }

        $this->newLine();
        $this->info('📋 Tasks:');
        $this->newLine();

        $headers = ['ID', 'Project', 'Status', 'Priority', 'Duration', 'Description'];
        $rows = [];

        foreach ($orchestration['tasks'] as $task) {
            $statusIcon = match ($task['status']) {
                'completed' => '✅',
                'in_progress' => '⏳',
                'failed' => '❌',
                default => '⏸️',
            };

            $duration = $task['actual_duration_minutes']
                ? "{$task['actual_duration_minutes']}m"
                : "~{$task['estimated_duration_minutes']}m";

            $priority = match ($task['priority']) {
                'high' => '<fg=red>HIGH</>',
                'low' => '<fg=gray>LOW</>',
                default => '<fg=yellow>MED</>',
            };

            $rows[] = [
                $task['id'],
                $task['project'],
                $statusIcon,
                $priority,
                $duration,
                $this->truncate($task['description'], 35),
            ];
        }

        $this->table($headers, $rows);

        // Show dependencies if any
        $tasksWithDeps = array_filter($orchestration['tasks'], fn($t) => !empty($t['dependencies']));
        if (!empty($tasksWithDeps)) {
            $this->newLine();
            $this->info('🔗 Dependencies:');
            foreach ($tasksWithDeps as $task) {
                $this->line("{$task['id']} depends on: " . implode(', ', $task['dependencies']));
            }
        }

        // Show next actions
        $this->newLine();
        $this->info('📌 Next Steps:');

        $pendingTasks = array_filter($orchestration['tasks'], function ($task) use ($orchestration) {
            if ($task['status'] !== 'pending') {
                return false;
            }

            // Check if dependencies are met
            foreach ($task['dependencies'] as $depId) {
                $depTask = $this->findTask($orchestration['tasks'], $depId);
                if (!$depTask || $depTask['status'] !== 'completed') {
                    return false;
                }
            }

            return true;
        });

        if (empty($pendingTasks)) {
            if ($orchestration['status'] === 'completed') {
                $this->line('✅ All tasks completed!');
            } else {
                $this->line('⏳ Waiting for current tasks to complete...');
            }
        } else {
            foreach ($pendingTasks as $task) {
                $this->line("• {$task['project']}: {$task['description']}");
            }
            $this->newLine();
            $this->line('Run in each project: php artisan havun:tasks:check');
        }
    }

    private function watchStatus(): int
    {
        $orchestrationId = $this->argument('orchestration_id');

        if (!$orchestrationId) {
            $this->error('❌ Orchestration ID required for watch mode');
            return self::FAILURE;
        }

        while (true) {
            // Clear screen
            if (PHP_OS_FAMILY !== 'Windows') {
                system('clear');
            } else {
                system('cls');
            }

            $this->info('🔄 Watching orchestration (refresh every 10s) - Press Ctrl+C to stop');
            $this->newLine();

            $orchestrator = app(TaskOrchestrator::class);
            $orchestration = $orchestrator->getStatus($orchestrationId);

            if (!$orchestration) {
                $this->error("❌ Orchestration {$orchestrationId} not found");
                return self::FAILURE;
            }

            $this->displayOrchestrationDetails($orchestration);

            if ($orchestration['status'] === 'completed' || $orchestration['status'] === 'failed') {
                $this->newLine();
                $this->info('✅ Orchestration finished. Exiting watch mode.');
                return self::SUCCESS;
            }

            sleep(10);
        }
    }

    private function calculateDuration(array $orchestration): string
    {
        if ($orchestration['status'] === 'pending') {
            return '-';
        }

        $elapsed = $this->calculateElapsedMinutes($orchestration);

        if ($orchestration['status'] === 'completed') {
            return "{$elapsed}m";
        }

        return "{$elapsed}m / ~{$orchestration['estimated_duration_minutes']}m";
    }

    private function calculateElapsedMinutes(array $orchestration): int
    {
        if (!$orchestration['started_at']) {
            return 0;
        }

        $end = $orchestration['completed_at'] ?? now()->toIso8601String();
        $start = strtotime($orchestration['started_at']);
        $endTime = strtotime($end);

        return round(($endTime - $start) / 60);
    }

    private function findTask(array $tasks, string $taskId): ?array
    {
        foreach ($tasks as $task) {
            if ($task['id'] === $taskId) {
                return $task;
            }
        }
        return null;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
