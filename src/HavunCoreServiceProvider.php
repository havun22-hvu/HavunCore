<?php

namespace Havun\Core;

use Illuminate\Support\ServiceProvider;
use Havun\Core\Services\InvoiceSyncService;
use Havun\Core\Services\MemorialReferenceService;
use Havun\Core\Services\MollieService;
use Havun\Core\Services\MCPService;
use Havun\Core\Listeners\ReportToMCP;
use Illuminate\Support\Facades\Event;

class HavunCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MemorialReferenceService as singleton
        $this->app->singleton(MemorialReferenceService::class, function ($app) {
            return new MemorialReferenceService();
        });

        // Register MollieService
        $this->app->singleton(MollieService::class, function ($app) {
            return new MollieService(
                config('services.mollie.key')
            );
        });

        // Register InvoiceSyncService
        $this->app->singleton(InvoiceSyncService::class, function ($app) {
            return new InvoiceSyncService(
                apiUrl: config('services.havunadmin.api_url'),
                apiToken: config('services.havunadmin.api_token'),
                memorialService: $app->make(MemorialReferenceService::class)
            );
        });

        // Register MCPService
        $this->app->singleton(MCPService::class, function ($app) {
            return new MCPService(
                mcpUrl: config('services.mcp.url', 'http://localhost:3000'),
                projectName: config('app.name', 'HavunCore')
            );
        });

        // Register APIContractRegistry
        $this->app->singleton(\Havun\Core\Services\APIContractRegistry::class, function ($app) {
            return new \Havun\Core\Services\APIContractRegistry(
                mcp: $app->make(MCPService::class),
                projectName: config('app.name', 'HavunCore')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register MCP event subscriber
        Event::subscribe(ReportToMCP::class);

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Havun\Core\Commands\StoreProjectVault::class,
            ]);
        }

        // Publish config if needed in the future
        // $this->publishes([
        //     __DIR__.'/../config/havun.php' => config_path('havun.php'),
        // ], 'havun-config');
    }
}
