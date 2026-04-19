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
Schedule::command('qv:scan --only=observatory --json')->weeklyOn(1, '04:37');
// Server health (disk + failed systemd units) — daily, off-minute :47.
Schedule::command('qv:scan --only=server --json')->dailyAt('03:47');
// Form-validation coverage heuristic — weekly, off-minute :57 (Tuesday so it
// runs after the Monday SSL+Observatory window).
Schedule::command('qv:scan --only=forms --json')->weeklyOn(2, '04:57');
// Rate-limit coverage heuristic — weekly, off-minute (Wednesday).
Schedule::command('qv:scan --only=ratelimit --json')->weeklyOn(3, '05:07');
// Hardcoded-credentials scan — weekly, off-minute (Thursday).
Schedule::command('qv:scan --only=secrets --json')->weeklyOn(4, '05:17');
// Session-cookie security flags — weekly, off-minute (Friday).
Schedule::command('qv:scan --only=session-cookies --json')->weeklyOn(5, '05:27');
// Render latest scan as Markdown report (overwrites docs/kb/reference/qv-scan-latest.md)
Schedule::command('qv:log')->dailyAt('03:27');
