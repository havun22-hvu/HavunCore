<?php

namespace App\Console\Commands;

use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class QualitySafetyScanCommand extends Command
{
    protected $signature = 'qv:scan
        {--only= : Run only one check (composer|npm|ssl)}
        {--project= : Scan only one project (slug)}
        {--json : Emit machine-readable JSON on stdout}';

    protected $description = 'Cross-project Quality & Safety scan (composer audit, npm audit, SSL expiry)';

    public function handle(QualitySafetyScanner $scanner): int
    {
        $only = $this->option('only');
        $projectFilter = $this->option('project');

        $availableChecks = ['composer', 'npm', 'ssl'];
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
        $this->line("Projects: " . count($run['projects']) . ' | Checks: ' . implode(', ', $run['checks']));
        $this->newLine();

        foreach ($run['findings'] as $finding) {
            $icon = match ($finding['severity']) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low' => '🔵',
                default => '⚪',
            };
            $this->line("{$icon} [{$finding['severity']}] {$finding['project']}/{$finding['check']}: {$finding['message']}");
        }

        $this->newLine();
        $this->line(sprintf(
            'Totals — critical: %d | high: %d | medium: %d | low: %d | info: %d | errors: %d',
            $run['totals']['critical'] ?? 0,
            $run['totals']['high'] ?? 0,
            $run['totals']['medium'] ?? 0,
            $run['totals']['low'] ?? 0,
            $run['totals']['informational'] ?? 0,
            $run['totals']['errors'] ?? 0,
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
