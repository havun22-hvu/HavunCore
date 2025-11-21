<?php

namespace Havun\Core\Commands;

use Havun\Core\Models\BackupLog;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'havun:backup:list
                            {--project= : Filter by project}
                            {--limit=20 : Number of backups to show}';

    protected $description = 'List recent backups';

    public function handle(): int
    {
        $project = $this->option('project');
        $limit = (int) $this->option('limit');

        $this->info('');
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║   Recent Backups                      ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->info('');

        $query = BackupLog::query();

        if ($project) {
            $query->forProject($project);
            $this->info("Showing backups for: {$project}");
        } else {
            $this->info("Showing backups for all projects");
        }

        $backups = $query->orderBy('backup_date', 'desc')
            ->limit($limit)
            ->get();

        if ($backups->isEmpty()) {
            $this->warn('No backups found.');
            return self::SUCCESS;
        }

        $this->info('');

        $headers = ['Date', 'Project', 'Status', 'Size', 'Duration', 'Offsite'];
        $rows = [];

        foreach ($backups as $backup) {
            $statusIcon = match ($backup->status) {
                'success' => '<fg=green>✅</>',
                'failed' => '<fg=red>❌</>',
                'partial' => '<fg=yellow>⚠️</>',
                default => '❓',
            };

            $rows[] = [
                $backup->backup_date->format('Y-m-d H:i'),
                $backup->project,
                $statusIcon . ' ' . ucfirst($backup->status),
                $backup->formatted_size,
                $backup->duration_seconds ? $backup->duration_seconds . 's' : '-',
                $backup->disk_offsite ? '<fg=green>✅</>' : '<fg=red>❌</>',
            ];
        }

        $this->table($headers, $rows);

        $this->info('');
        $this->info("Total: {$backups->count()} backups");

        return self::SUCCESS;
    }
}
