<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\VaultService;

class VaultInit extends Command
{
    protected $signature = 'havun:vault:init';
    protected $description = 'Initialize the HavunCore secrets vault';

    public function handle(): int
    {
        $this->info('🔐 Initializing HavunCore Vault...');

        try {
            $vault = app(VaultService::class);
            $vault->initialize();

            $this->info('✅ Vault initialized successfully!');
            $this->newLine();
            $this->info('📁 Location: storage/vault/secrets.encrypted.json');
            $this->info('🔑 Encryption: AES-256-CBC');
            $this->newLine();
            $this->info('Next steps:');
            $this->line('  1. Add secrets: php artisan havun:vault:set <key> <value>');
            $this->line('  2. List secrets: php artisan havun:vault:list');
            $this->line('  3. Get secret: php artisan havun:vault:get <key>');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize vault: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
