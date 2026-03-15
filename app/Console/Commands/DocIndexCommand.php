<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Console\Command;

class DocIndexCommand extends Command
{
    protected $signature = 'docs:index
                            {project? : Project to index (or "all" for all projects)}
                            {--force : Force reindex even if unchanged}
                            {--no-code : Skip code files, only index MD docs}';

    protected $description = 'Index MD files and code for the Doc Intelligence system';

    public function handle(DocIndexer $indexer): int
    {
        $project = $this->argument('project');
        $force = $this->option('force');
        $includeCode = !$this->option('no-code');

        if ($project === 'all' || empty($project)) {
            $this->info('Indexing all projects...' . ($includeCode ? ' (MD + code)' : ' (MD only)'));
            $results = $indexer->indexAll($force, $includeCode);

            foreach ($results as $proj => $result) {
                if (isset($result['error'])) {
                    $this->error("{$proj}: {$result['error']}");
                } else {
                    $md = $result['indexed_md'] ?? $result['indexed'];
                    $code = $result['indexed_code'] ?? 0;
                    $this->line("{$proj}: {$result['indexed']} indexed ({$md} md, {$code} code), {$result['skipped']} skipped");
                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $error) {
                            $this->warn("  - {$error}");
                        }
                    }
                }
            }
        } else {
            $this->info("Indexing project: {$project}" . ($includeCode ? ' (MD + code)' : ' (MD only)'));
            $result = $indexer->indexProject($project, $force, $includeCode);

            if (isset($result['error'])) {
                $this->error($result['error']);
                return Command::FAILURE;
            }

            $md = $result['indexed_md'] ?? $result['indexed'];
            $code = $result['indexed_code'] ?? 0;
            $this->info("Indexed: {$result['indexed']} ({$md} md, {$code} code), Skipped: {$result['skipped']}");
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
