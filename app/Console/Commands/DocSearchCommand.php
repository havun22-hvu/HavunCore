<?php

namespace App\Console\Commands;

use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Console\Command;

class DocSearchCommand extends Command
{
    protected $signature = 'docs:search
                            {query : The search query}
                            {--project= : Filter by project}
                            {--limit=5 : Number of results}';

    protected $description = 'Search documentation using semantic search';

    public function handle(DocIndexer $indexer): int
    {
        $query = $this->argument('query');
        $project = $this->option('project');
        $limit = (int) $this->option('limit');

        $this->info("Searching for: \"{$query}\"");
        $this->line('');

        $results = $indexer->search($query, $project, $limit);

        if (empty($results)) {
            $this->warn('No results found.');
            return Command::SUCCESS;
        }

        $this->info("Top {$limit} results:");
        $this->line('');

        foreach ($results as $i => $result) {
            $rank = $i + 1;
            $similarity = round($result['similarity'] * 100, 1);
            $modified = $result['file_modified_at'] ?? 'unknown';

            $this->line("{$rank}. [{$result['project']}] {$result['file_path']}");
            $this->line("   Relevance: {$similarity}% | Modified: {$modified}");
            $this->line("   Preview: " . substr($result['snippet'], 0, 100) . '...');
            $this->line('');
        }

        return Command::SUCCESS;
    }
}
