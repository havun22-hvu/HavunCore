<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Console\Command;

class DocIndexCommand extends Command
{
    protected $signature = 'docs:index
                            {project? : Project to index (or "all" for all projects)}
                            {--force : Force reindex even if unchanged}';

    protected $description = 'Index MD files for the Doc Intelligence system';

    public function handle(DocIndexer $indexer): int
    {
        $project = $this->argument('project');
        $force = $this->option('force');

        if ($project === 'all' || empty($project)) {
            $this->info('Indexing all projects...');
            $results = $indexer->indexAll($force);

            foreach ($results as $proj => $result) {
                if (isset($result['error'])) {
                    $this->error("{$proj}: {$result['error']}");
                } else {
                    $this->line("{$proj}: {$result['indexed']} indexed, {$result['skipped']} skipped");
                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $error) {
                            $this->warn("  - {$error}");
                        }
                    }
                }
            }
        } else {
            $this->info("Indexing project: {$project}");
            $result = $indexer->indexProject($project, $force);

            if (isset($result['error'])) {
                $this->error($result['error']);
                return Command::FAILURE;
            }

            $this->info("Indexed: {$result['indexed']}, Skipped: {$result['skipped']}");
            if (!empty($result['errors'])) {
                $this->warn('Errors:');
                foreach ($result['errors'] as $error) {
                    $this->warn("  - {$error}");
                }
            }
        }

        return Command::SUCCESS;
    }
}
