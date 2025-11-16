<?php

namespace Havun\Core;

use Illuminate\Support\ServiceProvider;
use Havun\Core\Services\InvoiceSyncService;
use Havun\Core\Services\MemorialReferenceService;
use Havun\Core\Services\MollieService;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config if needed in the future
        // $this->publishes([
        //     __DIR__.'/../config/havun.php' => config_path('havun.php'),
        // ], 'havun-config');
    }
}
