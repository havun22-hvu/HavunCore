<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\DocIndexer;
use App\Models\DocIntelligence\DocEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DocWatchCommand extends Command
{
    protected $signature = 'docs:watch
                            {--interval=30 : Seconds between sync cycles}
                            {--once : Run one sync cycle and exit}';

    protected $description = 'Auto-sync: watch for file changes and re-index automatically';

    protected bool $shouldStop = false;

    public function handle(DocIndexer $indexer): int
    {
        $interval = (int) $this->option('interval');
        $once = $this->option('once');

        if ($once) {
            $result = $this->syncCycle($indexer);
            $this->printResult($result);
            return Command::SUCCESS;
        }

        $this->info("Doc Intelligence auto-sync started (every {$interval}s). Press Ctrl+C to stop.");

        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        }

        while (!$this->shouldStop) {
            $result = $this->syncCycle($indexer);
            $this->printResult($result);

            // Sleep in 1-second intervals for responsiveness
            for ($i = 0; $i < $interval && !$this->shouldStop; $i++) {
                sleep(1);
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        }

        $this->info('Auto-sync stopped.');
        return Command::SUCCESS;
    }

    /**
     * Run one sync cycle: check all projects for changed files
     */
    protected function syncCycle(DocIndexer $indexer): array
    {
        $totalUpdated = 0;
        $totalRemoved = 0;
        $projects = [];

        $projectPaths = $this->getProjectPaths();

        foreach ($projectPaths as $project => $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            // Delta index: only changed files (force=false)
            $result = $indexer->indexProject($project, false);
            $updated = $result['indexed'] ?? 0;

            // Cleanup orphaned files
            $removed = $indexer->cleanupOrphaned($project);

            if ($updated > 0 || $removed > 0) {
                $projects[$project] = [
                    'updated' => $updated,
                    'removed' => $removed,
                ];
            }

            $totalUpdated += $updated;
            $totalRemoved += $removed;
        }

        return [
            'total_updated' => $totalUpdated,
            'total_removed' => $totalRemoved,
            'projects' => $projects,
            'timestamp' => now()->format('H:i:s'),
        ];
    }

    protected function printResult(array $result): void
    {
        $ts = $result['timestamp'];

        if ($result['total_updated'] === 0 && $result['total_removed'] === 0) {
            $this->line("[{$ts}] No changes detected.");
            return;
        }

        $this->info("[{$ts}] Synced: {$result['total_updated']} updated, {$result['total_removed']} removed");
        foreach ($result['projects'] as $project => $stats) {
            $parts = [];
            if ($stats['updated'] > 0) $parts[] = "{$stats['updated']} updated";
            if ($stats['removed'] > 0) $parts[] = "{$stats['removed']} removed";
            $this->line("  {$project}: " . implode(', ', $parts));
        }
    }

    protected function getProjectPaths(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'havuncore' => 'D:/GitHub/HavunCore',
                'havunadmin' => 'D:/GitHub/HavunAdmin',
                'herdenkingsportaal' => 'D:/GitHub/Herdenkingsportaal',
                'judotoernooi' => 'D:/GitHub/JudoToernooi',
                'infosyst' => 'D:/GitHub/Infosyst',
                'studieplanner' => 'D:/GitHub/Studieplanner',
                'studieplanner-api' => 'D:/GitHub/Studieplanner-api',
                'safehavun' => 'D:/GitHub/SafeHavun',
                'havun' => 'D:/GitHub/Havun',
                'vpdupdate' => 'D:/GitHub/VPDUpdate',
                'idsee' => 'D:/GitHub/IDSee',
                'havunvet' => 'D:/GitHub/HavunVet',
                'havuncore-webapp' => 'D:/GitHub/havuncore-webapp',
            ];
        }

        return [
            'havuncore' => '/var/www/development/HavunCore',
            'havunadmin' => '/var/www/havunadmin/production',
            'herdenkingsportaal' => '/var/www/herdenkingsportaal/production',
            'judotoernooi' => '/var/www/judotoernooi/laravel',
            'infosyst' => '/var/www/infosyst/production',
            'studieplanner' => '/var/www/studieplanner/production',
            'safehavun' => '/var/www/safehavun/production',
        ];
    }
}
