<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\TaskOrchestrator;

class Orchestrate extends Command
{
    protected $signature = 'havun:orchestrate
                            {description : High-level description of what you want to build}
                            {--projects=* : Specific projects to target}
                            {--dry-run : Show what would be done without delegating tasks}';

    protected $description = 'Orchestrate a task across multiple Claude instances';

    public function handle(): int
    {
        $description = $this->argument('description');

        $this->info('🎯 HavunCore Task Orchestrator');
        $this->newLine();
        $this->line("Request: {$description}");
        $this->newLine();

        try {
            $this->info('📊 Analyzing request...');

            $orchestrator = app(TaskOrchestrator::class);

            if ($this->option('dry-run')) {
                $this->warn('🔍 DRY RUN MODE - No tasks will be delegated');
                $this->newLine();
            }

            $orchestration = $orchestrator->orchestrate($description, [
                'projects' => $this->option('projects'),
            ]);

            $this->displayOrchestration($orchestration);

            if ($this->option('dry-run')) {
                $this->newLine();
                $this->info('✅ Dry run complete. Use without --dry-run to execute.');
                return self::SUCCESS;
            }

            $this->newLine();
            $this->info('📤 Tasks delegated via MCP!');
            $this->newLine();
            $this->line('Monitor progress with:');
            $this->line("  php artisan havun:status {$orchestration['id']}");
            $this->newLine();
            $this->line('Projects should check for tasks with:');
            $this->line('  php artisan havun:tasks:check');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Orchestration failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayOrchestration(array $orchestration): void
    {
        $analysis = $orchestration['analysis'];

        $this->newLine();
        $this->info('📋 Analysis Results:');
        $this->newLine();

        $this->line('Components identified:');
        foreach ($analysis['components'] as $component) {
            $this->line("  • {$component}");
        }

        $this->newLine();
        $this->line('Projects affected:');
        foreach ($analysis['projects'] as $project) {
            $this->line("  • {$project}");
        }

        if (!empty($analysis['required_secrets'])) {
            $this->newLine();
            $this->line('Secrets required:');
            foreach ($analysis['required_secrets'] as $secret) {
                $this->line("  ✓ {$secret}");
            }
        }

        if (!empty($analysis['relevant_snippets'])) {
            $this->newLine();
            $this->line('Code snippets available:');
            foreach ($analysis['relevant_snippets'] as $snippet) {
                $this->line("  ✓ {$snippet}");
            }
        }

        $this->newLine();
        $this->line('Database changes: ' . ($analysis['database_changes'] ? 'Yes' : 'No'));
        $this->line('API changes: ' . ($analysis['api_changes'] ? 'Yes' : 'No'));
        $this->line('Complexity: ' . strtoupper($analysis['complexity']));

        $this->newLine();
        $this->info("🎯 Created {$orchestration['total_tasks']} tasks:");
        $this->newLine();

        $headers = ['ID', 'Project', 'Priority', 'Description', 'Time', 'Deps'];
        $rows = [];

        foreach ($orchestration['tasks'] as $task) {
            $rows[] = [
                $task['id'],
                $task['project'],
                strtoupper($task['priority']),
                $this->truncate($task['description'], 40),
                $task['estimated_duration_minutes'] . 'm',
                empty($task['dependencies']) ? '-' : count($task['dependencies']),
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->line("⏱️  Estimated duration: {$orchestration['estimated_duration_minutes']} minutes (parallel execution)");

        // Calculate sequential time
        $sequential = array_sum(array_column($orchestration['tasks'], 'estimated_duration_minutes'));
        $savings = round((($sequential - $orchestration['estimated_duration_minutes']) / $sequential) * 100);

        $this->line("   Sequential would take: {$sequential} minutes");
        $this->line("   Time saved: {$savings}%");
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
