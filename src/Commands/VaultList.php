<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\VaultService;

class VaultList extends Command
{
    protected $signature = 'havun:vault:list
                            {--project= : Filter by project}
                            {--json : Output as JSON}';

    protected $description = 'List all secrets in the HavunCore vault';

    public function handle(): int
    {
        try {
            $vault = app(VaultService::class);
            $secrets = $vault->list();

            if (empty($secrets)) {
                $this->warn('⚠️  Vault is empty');
                $this->line('   Add secrets with: php artisan havun:vault:set <key> <value>');
                return self::SUCCESS;
            }

            // Filter by project if specified
            if ($project = $this->option('project')) {
                $secrets = array_filter($secrets, function ($metadata) use ($project) {
                    return isset($metadata['project']) && $metadata['project'] === $project;
                });
            }

            if ($this->option('json')) {
                $this->line(json_encode(array_keys($secrets), JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            $this->info('🔐 Secrets in HavunCore Vault:');
            $this->newLine();

            $headers = ['Key', 'Project', 'Description', 'Created'];
            $rows = [];

            foreach ($secrets as $key => $metadata) {
                if ($key === '_vault_version') {
                    continue;
                }

                $rows[] = [
                    $key,
                    $metadata['project'] ?? 'global',
                    $metadata['description'] ?? '-',
                    isset($metadata['created_at']) ? date('Y-m-d H:i', strtotime($metadata['created_at'])) : '-',
                ];
            }

            $this->table($headers, $rows);
            $this->newLine();
            $this->line('Total: ' . count($rows) . ' secrets');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to list secrets: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
