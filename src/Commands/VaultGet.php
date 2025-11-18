<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\VaultService;

class VaultGet extends Command
{
    protected $signature = 'havun:vault:get
                            {key : Secret key to retrieve}
                            {--show : Show the secret value (default: hidden)}';

    protected $description = 'Retrieve a secret from the HavunCore vault';

    public function handle(): int
    {
        $key = $this->argument('key');

        try {
            $vault = app(VaultService::class);
            $value = $vault->get($key);

            if ($value === null) {
                $this->error("❌ Secret '{$key}' not found in vault");
                return self::FAILURE;
            }

            $this->info("🔑 Secret: {$key}");

            if ($this->option('show')) {
                $this->line("   Value: {$value}");
            } else {
                $this->line("   Value: " . str_repeat('*', min(strlen($value), 20)));
                $this->line("   (Use --show to reveal)");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to retrieve secret: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
