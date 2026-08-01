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
// Cargo advisories (Rust) — zelfde ritme als composer/npm. Zonder deze regel
// zou een Rust-project elke nacht ongemeten blijven terwijl het rapport nul toont.
Schedule::command('qv:scan --only=cargo --json')->dailyAt('03:22');
// Dekkingscontrole: meldt een ecosysteem waarvoor géén audit bestaat. Draait ná
// de drie audits, zodat "niet gemeten" naast de echte uitslagen komt te staan.
Schedule::command('qv:scan --only=deps-coverage --json')->dailyAt('03:24');
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
// Test-erosion (deleted/skipped tests) — weekly, off-minute (Saturday).
Schedule::command('qv:scan --only=test-erosion --json')->weeklyOn(6, '05:37');
// APP_DEBUG default check — daily (cheap config-read).
Schedule::command('qv:scan --only=debug-mode --json')->dailyAt('03:57');
// Registry-drift: staat elk draaiend project ook in de scan- en backuplijst?
// Draait vóór qv:log (03:27) zodat een ontbrekend project in hetzelfde rapport
// staat als de scans die het gemist heeft. Config-vergelijking, dus goedkoop.
Schedule::command('qv:scan --only=registries --json')->dailyAt('03:02');
// Render latest scan as Markdown report (overwrites docs/kb/reference/qv-scan-latest.md)
Schedule::command('qv:log')->dailyAt('03:27');
// Verify critical-paths docs still point at existing tests (link-check only; no --run).
Schedule::command('critical-paths:verify --all --json')->dailyAt('03:52');
// Auto-refresh public handover.md from recent git log + latest qv:scan snapshot.
// Runs after qv:log (03:27) so the V&K state reflects today's scheduled scans.
Schedule::command('docs:handover')->dailyAt('04:00');
// Wekelijkse KB-audit (zondag 04:30 — na za test-erosion 05:37 op zaterdag,
// voor ma-SSL 04:07). Rapport in docs/kb/reference/kb-audit-latest.md.
Schedule::command('docs:audit')->weeklyOn(0, '04:30');

// GitHub Actions op de hoofdbranch — twee keer per dag. Vusista's staging-deploy
// faalde dertien dagen zonder dat iemand het zag: het signaal bestond, het kanaal
// niet. Een rode build wordt hier een in-app health-alert, en na drie dagen rood
// escaleert die naar `critical` (web-push).
Schedule::command('actions:watch')->twiceDaily(7, 19);

// Repo-hygiene residu — wekelijkse detectie. Lokale fallback wanneer de
// scanner op de productie-host zelf draait (geen SSH self-loop).
Schedule::command('qv:scan --only=residu --json')->weeklyOn(0, '05:47');

// Auto-commit + push van de regenerated docs (handover, kb-audit-latest,
// qv-scan-latest, security-findings-log). Draait elke dag om 06:00, na alle
// ochtend-regeneraties (qv:log 03:27, docs:handover 04:00, docs:audit 04:30).
// Idempotent: skip als er niets gewijzigd is.
Schedule::command('auto:commit-regenerated')->dailyAt('06:00');
