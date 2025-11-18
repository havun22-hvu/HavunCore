<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\SnippetLibrary;

class SnippetGet extends Command
{
    protected $signature = 'havun:snippet:get
                            {path : Snippet path (e.g., payments/mollie-setup)}
                            {--copy : Copy to clipboard (requires xclip/pbcopy)}';

    protected $description = 'Get a code snippet from the library';

    public function handle(): int
    {
        $path = $this->argument('path');

        try {
            $library = app(SnippetLibrary::class);
            $snippet = $library->get($path);

            if (!$snippet) {
                $this->error("❌ Snippet '{$path}' not found");
                $this->line('   List all: php artisan havun:snippet:list');
                return self::FAILURE;
            }

            $this->info("📄 Snippet: {$path}");
            $this->newLine();

            if (!empty($snippet['metadata'])) {
                if (isset($snippet['metadata']['description'])) {
                    $this->line("Description: {$snippet['metadata']['description']}");
                }
                if (isset($snippet['metadata']['usage'])) {
                    $this->line("Usage: {$snippet['metadata']['usage']}");
                }
                if (isset($snippet['metadata']['tags'])) {
                    $this->line("Tags: " . implode(', ', $snippet['metadata']['tags']));
                }
                $this->newLine();
            }

            $this->line(str_repeat('=', 80));
            $this->line($snippet['code']);
            $this->line(str_repeat('=', 80));

            if ($this->option('copy')) {
                $this->copyToClipboard($snippet['code']);
                $this->newLine();
                $this->info('✅ Copied to clipboard!');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to get snippet: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function copyToClipboard(string $content): void
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $process = popen('pbcopy', 'w');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $process = popen('xclip -selection clipboard', 'w');
        } else {
            return;
        }

        fwrite($process, $content);
        pclose($process);
    }
}
