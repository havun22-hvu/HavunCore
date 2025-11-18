<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\SnippetLibrary;

class SnippetInit extends Command
{
    protected $signature = 'havun:snippet:init';
    protected $description = 'Initialize snippet library with default templates';

    public function handle(): int
    {
        $this->info('📚 Initializing snippet library...');

        try {
            $library = app(SnippetLibrary::class);
            $library->initialize();

            $this->info('✅ Snippet library initialized!');
            $this->newLine();
            $this->line('Default snippets added:');
            $this->line('  • payments/mollie-payment-setup.php');
            $this->line('  • api/rest-response-formatter.php');
            $this->line('  • utilities/memorial-reference-service.php');
            $this->newLine();
            $this->line('List all: php artisan havun:snippet:list');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
