<?php

namespace Havun\Core\Commands;

use Havun\Core\Services\BackupOrchestrator;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'havun:backup:run
                            {--project= : Specific project to backup}
                            {--dry-run : Test without actual upload}
                            {--force : Force backup even if one already exists}';

    protected $description = 'Run backup for all or specific Havun project';

    public function handle(BackupOrchestrator $orchestrator): int
    {
        $project = $this->option('project');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No files will be uploaded');
        }

        $this->info('');
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║   HavunCore Backup Orchestrator       ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->info('');

        if ($project) {
            $this->info("📦 Starting backup for: {$project}");
        } else {
            $this->info("📦 Starting backup for all enabled projects");
        }

        $this->info('');

        try {
            $results = $orchestrator->runBackup($project);

            $this->displayResults($results);

            // Check if any backups failed
            $hasFailures = collect($results)->contains(fn($result) => $result['status'] === 'failed');

            if ($hasFailures) {
                $this->error('');
                $this->error('❌ Some backups failed! Check the logs for details.');
                return self::FAILURE;
            }

            $this->info('');
            $this->info('✅ All backups completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('');
            $this->error('❌ Backup failed with exception:');
            $this->error($e->getMessage());
            $this->error('');
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    protected function displayResults(array $results): void
    {
        foreach ($results as $project => $result) {
            $this->info("──────────────────────────────────────");
            $this->info("Project: {$project}");

            if ($result['status'] === 'success') {
                $this->line("Status:   <fg=green>✅ Success</>");
                $this->line("Name:     {$result['backup_name']}");
                $this->line("Size:     {$result['size']}");
                $this->line("Duration: {$result['duration']}s");
                $this->line("Local:    " . ($result['local'] ? '<fg=green>✅</>' : '<fg=red>❌</>'));
                $this->line("Offsite:  " . ($result['offsite'] ? '<fg=green>✅</>' : '<fg=red>❌</>'));
                $this->line("Checksum: " . substr($result['checksum'], 0, 16) . '...');
            } else {
                $this->line("Status: <fg=red>❌ Failed</>");
                $this->line("Error:  {$result['error']}");
            }

            $this->info('');
        }
    }
}
