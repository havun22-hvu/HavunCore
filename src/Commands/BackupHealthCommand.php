<?php

namespace Havun\Core\Commands;

use Havun\Core\Services\BackupOrchestrator;
use Illuminate\Console\Command;

class BackupHealthCommand extends Command
{
    protected $signature = 'havun:backup:health';

    protected $description = 'Check backup health status for all projects';

    public function handle(BackupOrchestrator $orchestrator): int
    {
        $this->info('');
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║   Backup Health Check                 ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->info('');

        $results = $orchestrator->healthCheck();

        if (empty($results)) {
            $this->warn('No enabled projects found.');
            return self::SUCCESS;
        }

        $hasIssues = false;

        foreach ($results as $project => $health) {
            $this->displayProjectHealth($project, $health);

            if (in_array($health['status'], ['critical', 'warning'])) {
                $hasIssues = true;
            }
        }

        $this->info('──────────────────────────────────────');
        $this->info('');

        if ($hasIssues) {
            $this->warn('⚠️  Some projects have backup issues!');
            return self::FAILURE;
        }

        $this->info('✅ All backups are healthy!');
        return self::SUCCESS;
    }

    protected function displayProjectHealth(string $project, array $health): void
    {
        $statusIcon = match ($health['status']) {
            'healthy' => '<fg=green>✅</>',
            'warning' => '<fg=yellow>⚠️</>',
            'critical' => '<fg=red>❌</>',
            default => '❓',
        };

        $priorityLabel = match ($health['priority']) {
            'critical' => '<fg=red;options=bold>CRITICAL</>',
            'high' => '<fg=yellow;options=bold>HIGH</>',
            'medium' => '<fg=blue>MEDIUM</>',
            'low' => '<fg=gray>LOW</>',
            default => 'UNKNOWN',
        };

        $this->line("{$statusIcon} <fg=cyan;options=bold>{$project}</> ({$priorityLabel})");

        if ($health['last_backup_date']) {
            $ageHours = round($health['last_backup_age_hours'], 1);
            $ageColor = $ageHours < 25 ? 'green' : ($ageHours < 48 ? 'yellow' : 'red');

            $this->line("   Last backup: {$health['last_backup_date']->format('Y-m-d H:i')} (<fg={$ageColor}>{$ageHours}h ago</>)");
            $this->line("   Size: {$health['last_backup_size']}");
        } else {
            $this->line("   <fg=red>No backup found!</>");
        }

        if ($health['is_too_old']) {
            $this->warn("   ⚠️  Backup is too old! (>25 hours)");
        }

        if ($health['status'] === 'critical') {
            $this->error("   🚨 CRITICAL: Immediate action required!");
        }

        $this->info('');
    }
}
