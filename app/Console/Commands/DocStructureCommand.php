<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\StructureIndexer;
use Illuminate\Console\Command;

class DocStructureCommand extends Command
{
    protected $signature = 'docs:structure
                            {project? : Project to analyze (or "all")}
                            {--force : Force regenerate even if unchanged}';

    protected $description = 'Generate and index a structural overview of a project (models, controllers, routes, etc.)';

    public function handle(StructureIndexer $indexer): int
    {
        $project = $this->argument('project');
        $force = $this->option('force');

        if ($project === 'all' || empty($project)) {
            $this->info('Generating structure index for all projects...');
            $results = $indexer->indexAll($force);

            foreach ($results as $proj => $result) {
                if (isset($result['error'])) {
                    $this->warn("{$proj}: {$result['error']}");
                } else {
                    $this->line("{$proj}: {$result['models']}m / {$result['controllers']}c / {$result['services']}s / {$result['migrations']}mig / {$result['routes']}r");
                }
            }
        } else {
            $this->info("Generating structure index for: {$project}");
            $result = $indexer->indexProject($project, $force);

            if (isset($result['error'])) {
                $this->error($result['error']);
                return Command::FAILURE;
            }

            $this->info("Models: {$result['models']} | Controllers: {$result['controllers']} | Services: {$result['services']} | Migrations: {$result['migrations']} | Routes: {$result['routes']}");
            $this->newLine();
            $this->line($result['summary_preview']);
        }

        return Command::SUCCESS;
    }
}
