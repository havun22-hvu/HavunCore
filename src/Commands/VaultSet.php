<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\VaultService;

class VaultSet extends Command
{
    protected $signature = 'havun:vault:set
                            {key : Secret key (e.g., mollie_api_key)}
                            {value? : Secret value (optional, will prompt if not provided)}
                            {--project= : Project this secret belongs to}
                            {--description= : Description of this secret}
                            {--expires= : Expiration date (Y-m-d format)}';

    protected $description = 'Store a secret in the HavunCore vault';

    public function handle(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        // Prompt for value if not provided (for sensitive data)
        if (!$value) {
            $value = $this->secret('Enter secret value');
        }

        $metadata = array_filter([
            'project' => $this->option('project'),
            'description' => $this->option('description'),
            'expires_at' => $this->option('expires'),
        ]);

        try {
            $vault = app(VaultService::class);
            $exists = $vault->has($key);

            $vault->set($key, $value, $metadata);

            if ($exists) {
                $this->info("✅ Secret '{$key}' updated successfully!");
            } else {
                $this->info("✅ Secret '{$key}' stored successfully!");
            }

            if ($this->option('project')) {
                $this->line("   📦 Project: {$this->option('project')}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to store secret: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
