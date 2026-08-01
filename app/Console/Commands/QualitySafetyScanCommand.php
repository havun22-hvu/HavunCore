<?php

namespace App\Console\Commands;

use App\Enums\Severity;
use App\Services\QualitySafety\EcosystemDetector;
use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class QualitySafetyScanCommand extends Command
{
    protected $signature = 'qv:scan
        {--only= : Run only one check (composer|npm|cargo|deps-coverage|ssl|observatory|server|forms|ratelimit|secrets|session-cookies|test-erosion|debug-mode|residu|registries)}
        {--project= : Scan only one project (slug)}
        {--json : Emit machine-readable JSON on stdout}';

    protected $description = 'Cross-project Quality & Safety scan (composer audit, npm audit, SSL expiry, Mozilla Observatory, server health, form-validation coverage, rate-limit coverage, hardcoded-secrets detection, session-cookie flags, test-erosion, debug-mode, repo-hygiene residu, registry-drift)';

    public function handle(QualitySafetyScanner $scanner): int
    {
        $only = $this->option('only');
        $projectFilter = $this->option('project');

        $availableChecks = ['composer', 'npm', 'cargo', 'deps-coverage', 'ssl', 'observatory', 'server', 'forms', 'ratelimit', 'secrets', 'session-cookies', 'test-erosion', 'debug-mode', 'residu', 'registries'];
        $checks = $only ? [$only] : $availableChecks;

        foreach ($checks as $check) {
            if (! in_array($check, $availableChecks, true)) {
                $this->error("Unknown check: {$check}. Available: " . implode(', ', $availableChecks));

                return 2;
            }
        }

        $projects = $this->resolveProjects($projectFilter);

        if (empty($projects)) {
            $this->error('No matching projects (check config/quality-safety.php and --project flag)');

            return 2;
        }

        $run = $scanner->scan($projects, $checks);
        $encoded = json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->persist($encoded);

        if (! $this->option('json')) {
            $this->renderHuman($run);
        } else {
            $this->line($encoded);
        }

        return $this->exitCodeFor($run);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function resolveProjects(?string $filter): array
    {
        $all = config('quality-safety.projects', []);

        $enabled = array_filter($all, fn ($p) => ! empty($p['enabled']));

        if ($filter === null) {
            return $enabled;
        }

        return array_intersect_key($enabled, [$filter => true]);
    }

    private function persist(string $encoded): void
    {
        $now = Carbon::now();
        $root = rtrim(config('quality-safety.storage.root', 'qv-scans'), '/');
        $disk = config('quality-safety.storage.disk', 'local');

        Storage::disk($disk)->put(
            "{$root}/{$now->toDateString()}/run-{$now->format('Hisv')}-" . getmypid() . '.json',
            $encoded
        );
    }

    private function renderHuman(array $run): void
    {
        $this->info('Quality & Safety scan — ' . $run['started_at']);
        $this->line('Projects: ' . count($run['projects']) . ' | Checks: ' . implode(', ', $run['checks']));

        // Tonen hoe elk project gebouwd is, en welk ecosysteem géén audit heeft.
        // Zonder deze regel is "0 findings" op een Go-project niet te
        // onderscheiden van "0 findings" op een gemeten Laravel-app.
        foreach ($run['ecosystems'] ?? [] as $slug => $ecos) {
            if ($ecos === []) {
                continue;
            }
            $gelabeld = array_map(
                fn (string $e): string => in_array($e, EcosystemDetector::AUDITABLE, true) ? $e : "{$e} (NIET gemeten)",
                $ecos
            );
            $this->line("  {$slug}: " . implode(', ', $gelabeld));
        }

        $this->newLine();

        foreach ($run['findings'] as $finding) {
            $icon = Severity::safe((string) ($finding['severity'] ?? ''))->icon();
            $this->line("{$icon} [{$finding['severity']}] {$finding['project']}/{$finding['check']}: {$finding['message']}");
        }

        // Overgeslagen checks horen bij de uitslag: zonder deze regel leest
        // "0 findings" op een project waar de helft niet draaide hetzelfde als
        // op een project dat volledig is doorgemeten.
        if (! empty($run['skipped'])) {
            $this->newLine();
            $this->line('Niet gedraaid (n.v.t.):');
            foreach ($run['skipped'] as $skip) {
                $this->line("  - {$skip['project']}/{$skip['check']}: {$skip['reason']}");
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Totals — critical: %d | high: %d | medium: %d | low: %d | info: %d | errors: %d | overgeslagen: %d',
            $run['totals']['critical'] ?? 0,
            $run['totals']['high'] ?? 0,
            $run['totals']['medium'] ?? 0,
            $run['totals']['low'] ?? 0,
            $run['totals']['informational'] ?? 0,
            $run['totals']['errors'] ?? 0,
            count($run['skipped'] ?? []),
        ));
    }

    private function exitCodeFor(array $run): int
    {
        if (($run['totals']['errors'] ?? 0) > 0) {
            return 2;
        }
        if (($run['totals']['critical'] ?? 0) > 0 || ($run['totals']['high'] ?? 0) > 0) {
            return 1;
        }

        return 0;
    }
}
