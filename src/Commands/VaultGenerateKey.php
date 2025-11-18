<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\VaultService;

class VaultGenerateKey extends Command
{
    protected $signature = 'havun:vault:generate-key';
    protected $description = 'Generate a new encryption key for the vault';

    public function handle(): int
    {
        $key = VaultService::generateEncryptionKey();

        $this->info('🔑 Generated vault encryption key:');
        $this->newLine();
        $this->line('HAVUN_VAULT_KEY=' . $key);
        $this->newLine();
        $this->warn('⚠️  IMPORTANT:');
        $this->line('   1. Add this to your .env file');
        $this->line('   2. Keep this key SECRET and SECURE');
        $this->line('   3. If you lose this key, you cannot decrypt your vault');
        $this->line('   4. Back up this key in a secure password manager');

        return self::SUCCESS;
    }
}
