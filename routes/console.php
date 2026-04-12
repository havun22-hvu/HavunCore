<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Observability: aggregate metrics hourly, cleanup daily
Schedule::command('observability:aggregate --period=hourly')->hourly();
Schedule::command('observability:aggregate --period=daily')->dailyAt('00:15');
Schedule::command('observability:cleanup')->dailyAt('03:00');

// Performance baseline: daily comparison
Schedule::command('observability:baseline')->dailyAt('06:00');

// Chaos probes: health + endpoint check every hour
Schedule::command('chaos:run health-deep')->hourly();
Schedule::command('chaos:run endpoint-probe')->hourly();
