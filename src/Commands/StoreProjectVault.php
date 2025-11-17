<?php

namespace Havun\Core\Commands;

use Havun\Core\Services\MCPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Store project configuration in HavunCore's "disaster recovery vault"
 *
 * This command stores all critical configuration needed to restore a project.
 * HavunCore acts as the central vault for all project configurations.
 *
 * Usage:
 *   php artisan havun:vault:store
 */
class StoreProjectVault extends Command
{
    protected $signature = 'havun:vault:store {--force : Force update even if recently stored}';

    protected $description = 'Store project configuration in HavunCore disaster recovery vault';

    private MCPService $mcp;

    public function __construct(MCPService $mcp)
    {
        parent::__construct();
        $this->mcp = $mcp;
    }

    public function handle(): int
    {
        $this->info('🔐 Storing project configuration in vault...');

        $projectName = config('app.name', 'Unknown');

        // Gather all critical configuration
        $config = $this->gatherConfiguration();

        // Store in MCP vault
        $success = $this->mcp->storeProjectVault($projectName, $config);

        if ($success) {
            $this->info("✅ Configuration for {$projectName} stored in vault!");
            $this->line('');
            $this->line('This vault can be used for:');
            $this->line('- Disaster recovery');
            $this->line('- Project restoration');
            $this->line('- Configuration reference');
            $this->line('');
            $this->line('Stored configuration:');
            $this->table(['Key', 'Value'], $this->formatConfigForDisplay($config));

            return self::SUCCESS;
        } else {
            $this->error('❌ Failed to store vault configuration');
            return self::FAILURE;
        }
    }

    /**
     * Gather all critical project configuration
     */
    private function gatherConfiguration(): array
    {
        $projectName = config('app.name');

        $config = [
            'project_name' => $projectName,
            'stored_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),

            // Database configuration
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
                // DO NOT store credentials!
            ],

            // API endpoints
            'api_endpoints' => $this->gatherApiEndpoints(),

            // Installed packages (composer)
            'composer_packages' => $this->getComposerPackages(),

            // Environment-specific settings
            'app_settings' => [
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],

            // Queue configuration
            'queue' => [
                'default' => config('queue.default'),
                'connections' => array_keys(config('queue.connections', [])),
            ],

            // Cache configuration
            'cache' => [
                'default' => config('cache.default'),
            ],

            // Mail configuration
            'mail' => [
                'driver' => config('mail.default'),
                'from' => config('mail.from.address'),
            ],
        ];

        // Add project-specific configuration based on project name
        if ($projectName === 'Herdenkingsportaal') {
            $config['herdenkingsportaal'] = $this->getHerdenkingsportaalConfig();
        } elseif ($projectName === 'HavunAdmin') {
            $config['havunadmin'] = $this->getHavunAdminConfig();
        }

        return $config;
    }

    /**
     * Gather API endpoints used by this project
     */
    private function gatherApiEndpoints(): array
    {
        $endpoints = [];

        // HavunAdmin API (used by Herdenkingsportaal)
        if (config('services.havunadmin.api_url')) {
            $endpoints['havunadmin'] = [
                'url' => config('services.havunadmin.api_url'),
                'endpoints' => [
                    'invoice_sync' => 'POST /invoices/sync',
                    'invoice_status' => 'GET /invoices/by-reference/{reference}',
                ],
            ];
        }

        // Mollie API
        if (config('services.mollie.key')) {
            $endpoints['mollie'] = [
                'url' => 'https://api.mollie.com/v2',
                'has_key' => true, // Don't store actual key
            ];
        }

        // Bunq API (if configured)
        if (config('services.bunq')) {
            $endpoints['bunq'] = [
                'configured' => true,
            ];
        }

        return $endpoints;
    }

    /**
     * Get installed composer packages
     */
    private function getComposerPackages(): array
    {
        $composerLock = base_path('composer.lock');

        if (!File::exists($composerLock)) {
            return ['error' => 'composer.lock not found'];
        }

        $lock = json_decode(File::get($composerLock), true);

        $packages = [];
        foreach ($lock['packages'] ?? [] as $package) {
            // Only store important packages
            if (str_starts_with($package['name'], 'havun/') ||
                str_starts_with($package['name'], 'laravel/') ||
                in_array($package['name'], ['guzzlehttp/guzzle', 'mollie/laravel-mollie'])) {
                $packages[$package['name']] = $package['version'];
            }
        }

        return $packages;
    }

    /**
     * Get Herdenkingsportaal-specific configuration
     */
    private function getHerdenkingsportaalConfig(): array
    {
        return [
            'type' => 'customer_facing_app',
            'features' => [
                'memorials' => true,
                'payments' => true,
                'mollie_integration' => config('services.mollie.key') !== null,
                'invoice_sync' => config('services.havunadmin.api_url') !== null,
            ],
            'memorial_reference' => [
                'format' => '12 lowercase hex chars',
                'source' => 'UUID first 12 chars without dashes',
            ],
        ];
    }

    /**
     * Get HavunAdmin-specific configuration
     */
    private function getHavunAdminConfig(): array
    {
        return [
            'type' => 'admin_panel',
            'features' => [
                'invoice_management' => true,
                'client_management' => true,
                'mollie_sync' => config('services.mollie.key') !== null,
                'receives_invoices_from' => ['Herdenkingsportaal'],
            ],
            'api_endpoints' => [
                'POST /api/invoices/sync' => 'Receive invoices from Herdenkingsportaal',
                'GET /api/invoices/by-reference/{ref}' => 'Get invoice status',
            ],
        ];
    }

    /**
     * Format configuration for table display
     */
    private function formatConfigForDisplay(array $config, string $prefix = ''): array
    {
        $rows = [];

        foreach ($config as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                // Only show first level of nested arrays
                $rows[] = [$fullKey, json_encode($value, JSON_PRETTY_PRINT)];
            } else {
                $rows[] = [$fullKey, $value];
            }
        }

        return $rows;
    }
}
