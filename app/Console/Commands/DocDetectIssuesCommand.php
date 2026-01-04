<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Illuminate\Console\Command;

class DocDetectIssuesCommand extends Command
{
    protected $signature = 'docs:detect
                            {project? : Project to check (or empty for all)}
                            {--index : Also run indexer first}';

    protected $description = 'Detect issues (duplicates, inconsistencies, outdated) in MD files';

    public function handle(DocIndexer $indexer, IssueDetector $detector): int
    {
        $project = $this->argument('project');

        // Optionally index first
        if ($this->option('index')) {
            $this->info('Indexing first...');
            if ($project) {
                $indexer->indexProject($project);
            } else {
                $indexer->indexAll();
            }
            $this->line('');
        }

        $this->info('Detecting issues...');
        $results = $detector->detectAll($project);

        $this->line('');
        $this->info('Results:');
        $this->line("  Duplicates found: {$results['duplicates']}");
        $this->line("  Outdated docs: {$results['outdated']}");
        $this->line("  Broken links: {$results['broken_links']}");
        $this->line("  Inconsistencies: {$results['inconsistencies']}");

        $total = array_sum($results);
        if ($total > 0) {
            $this->line('');
            $this->warn("Total new issues: {$total}");
            $this->line('Run `php artisan docs:issues` to see all open issues.');
        } else {
            $this->line('');
            $this->info('No new issues detected!');
        }

        return Command::SUCCESS;
    }
}
