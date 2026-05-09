<?php

namespace Havun\Core;

use Illuminate\Support\ServiceProvider;
use Havun\Core\Services\InvoiceSyncService;
use Havun\Core\Services\MemorialReferenceService;
use Havun\Core\Services\MollieService;
use Havun\Core\Services\VaultService;
use Havun\Core\Services\SnippetLibrary;
use Havun\Core\Services\PushNotifier;
use Havun\Core\Services\BackupOrchestrator;

class HavunCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge backup configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/havun-backup.php', 'havun.backup'
        );
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

        // Register VaultService
        $this->app->singleton(VaultService::class, function ($app) {
            return new VaultService();
        });

        // Register SnippetLibrary
        $this->app->singleton(SnippetLibrary::class, function ($app) {
            return new SnippetLibrary();
        });

        // Register PushNotifier
        $this->app->singleton(PushNotifier::class, function ($app) {
            return new PushNotifier(
                notificationsBasePath: 'D:\GitHub\havun-mcp\notifications',
                projectName: config('app.name', 'HavunCore')
            );
        });

        // Register BackupOrchestrator
        $this->app->singleton(BackupOrchestrator::class, function ($app) {
            return new BackupOrchestrator();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Havun\Core\Commands\GenerateOpenAPISpec::class,

                // Vault commands
                \Havun\Core\Commands\VaultInit::class,
                \Havun\Core\Commands\VaultGenerateKey::class,
                \Havun\Core\Commands\VaultSet::class,
                \Havun\Core\Commands\VaultGet::class,
                \Havun\Core\Commands\VaultList::class,

                // Snippet commands
                \Havun\Core\Commands\SnippetInit::class,
                \Havun\Core\Commands\SnippetList::class,
                \Havun\Core\Commands\SnippetGet::class,

                // Notification commands
                \Havun\Core\Commands\NotificationSend::class,
                \Havun\Core\Commands\NotificationCheck::class,

                // Backup commands
                \Havun\Core\Commands\BackupRunCommand::class,
                \Havun\Core\Commands\BackupHealthCommand::class,
                \Havun\Core\Commands\BackupListCommand::class,
            ]);

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'havun-migrations');

            // Publish config
            $this->publishes([
                __DIR__.'/../config/havun-backup.php' => config_path('havun.php'),
            ], 'havun-config');
        }

        // Publish config if needed in the future
        // $this->publishes([
        //     __DIR__.'/../config/havun.php' => config_path('havun.php'),
        // ], 'havun-config');
    }
}
