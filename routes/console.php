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

// Droogtest reminder: daily check, sends email exactly 7 days before each scheduled dry run (VP-13)
Schedule::command('droogtest:reminder')->dailyAt('09:00');

// Quality & Safety (K&V) scans — cross-project CVE / dep / SSL monitoring
// Off-minuten (:07, :17) houden deze runs buiten het :00-boeket.
Schedule::command('qv:scan --only=composer --json')->dailyAt('03:07');
Schedule::command('qv:scan --only=npm --json')->dailyAt('03:17');
Schedule::command('qv:scan --only=ssl --json')->weeklyOn(1, '04:07');
