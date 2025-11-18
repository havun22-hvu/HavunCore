<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\SnippetLibrary;

class SnippetList extends Command
{
    protected $signature = 'havun:snippet:list
                            {--category= : Filter by category}
                            {--tag= : Filter by tag}';

    protected $description = 'List all code snippets in the library';

    public function handle(): int
    {
        try {
            $library = app(SnippetLibrary::class);

            if ($tag = $this->option('tag')) {
                $snippets = $library->searchByTag($tag);
                $this->info("📚 Snippets tagged with '{$tag}':");
            } else {
                $snippets = $library->list($this->option('category'));
                $this->info('📚 Code Snippet Library:');
            }

            if (empty($snippets)) {
                $this->warn('⚠️  No snippets found');
                $this->line('   Initialize with: php artisan havun:snippet:init');
                return self::SUCCESS;
            }

            $this->newLine();

            $headers = ['Path', 'Language', 'Tags', 'Description'];
            $rows = [];

            foreach ($snippets as $path => $metadata) {
                $rows[] = [
                    $path,
                    $metadata['language'] ?? 'php',
                    implode(', ', $metadata['tags'] ?? []),
                    $this->truncate($metadata['description'] ?? '-', 40),
                ];
            }

            $this->table($headers, $rows);

            $this->newLine();
            $this->line('Total: ' . count($snippets) . ' snippets');
            $this->newLine();
            $this->line('View snippet: php artisan havun:snippet:get <path>');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to list snippets: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
