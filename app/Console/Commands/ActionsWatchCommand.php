<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

/**
 * Zet een falende GitHub Actions-run om in een in-app health-alert.
 *
 * Aanleiding: Vusista's staging-deploy faalde **dertien dagen**. Elke push gaf
 * een rode Action en een publieke URL met een foutpagina, en niemand zag het.
 * Het signaal bestond al — er was alleen geen kanaal naar een mens.
 *
 * Alleen de hoofdbranch telt: een rode run op een feature-branch is werk in
 * uitvoering, geen storing. De severity groeit met de duur, zodat een build die
 * dagen rood staat vanzelf luider wordt in plaats van te wennen.
 */
class ActionsWatchCommand extends Command
{
    protected $signature = 'actions:watch
        {--project= : Eén project uit config/havun-projects.php (default: alle)}
        {--critical-after=3 : Dagen rood voor severity critical (dan gaat de web-push af)}
        {--dry-run : Toon wat er gemeld zou worden, schrijf geen alerts weg}';

    protected $description = 'Meldt falende GitHub Actions-runs op de hoofdbranch als health-alert';

    public function handle(): int
    {
        $projects = (array) config('havun-projects', []);
        if ($only = $this->option('project')) {
            $projects = array_intersect_key($projects, [$only => true]);
            if ($projects === []) {
                $this->error("Onbekend project: {$only}");

                return self::FAILURE;
            }
        }

        // Niet kunnen meten meld je — je zwijgt het niet weg. Zonder deze guard
        // zou een ontbrekende `gh` elke run schoon laten lijken, precies de
        // stilte die dit commando bestaat om op te heffen.
        if (! $this->ghBeschikbaar()) {
            $this->error('`gh` niet beschikbaar of niet ingelogd — Actions zijn NIET gecontroleerd.');
            $this->line('Dit is geen "alles groen": er is niet gekeken. `gh auth status` om te zien wat er mist.');

            return self::FAILURE;
        }

        $rood = 0;
        $gecontroleerd = 0;

        foreach ($projects as $slug => $project) {
            $repo = $this->repoVoor($project['path'] ?? null);
            if ($repo === null) {
                continue; // geen git-remote: niets om te bewaken
            }

            $run = $this->laatsteRunOpHoofdbranch($repo);
            if ($run === null) {
                continue; // geen workflows, of API gaf niets bruikbaars
            }

            $gecontroleerd++;

            if (($run['conclusion'] ?? null) === 'failure') {
                $rood++;
                $this->meldFalend($slug, $repo, $run);

                continue;
            }

            $this->line("  {$slug}: {$repo} groen");

            if ($this->option('dry-run')) {
                continue;
            }

            // Groen (of nog bezig na een groene): een openstaande melding mag weg.
            $this->call('health:alert', [
                'key' => $this->alertKey($slug),
                '--status' => 'up',
            ]);
        }

        $this->info("Gecontroleerd: {$gecontroleerd} repo('s) | rood op de hoofdbranch: {$rood}");

        return self::SUCCESS;
    }

    private function meldFalend(string $slug, string $repo, array $run): void
    {
        $sinds = isset($run['updated_at']) ? Carbon::parse($run['updated_at']) : null;
        $dagen = $sinds ? (int) $sinds->diffInDays(now()) : 0;
        $drempel = (int) $this->option('critical-after');

        $severity = $dagen >= $drempel ? 'critical' : 'warning';
        $duur = $sinds ? $sinds->diffForHumans() : 'onbekend sinds wanneer';

        $this->warn("[{$severity}] {$slug}: {$repo} rood sinds {$duur}");

        if ($this->option('dry-run')) {
            return;
        }

        $this->call('health:alert', [
            'key' => $this->alertKey($slug),
            '--scope' => 'project',
            '--project' => $slug,
            '--status' => 'down',
            '--severity' => $severity,
            '--title' => "{$slug}: build faalt op de hoofdbranch",
            '--body' => sprintf(
                "Workflow '%s' faalt sinds %s (%d dag(en)). %s",
                $run['name'] ?? 'onbekend',
                $duur,
                $dagen,
                $run['html_url'] ?? ''
            ),
        ]);
    }

    private function alertKey(string $slug): string
    {
        return "actions-{$slug}";
    }

    /**
     * De repo volgt uit de remote van de checkout, niet uit een lijst in de
     * config — dezelfde keuze als de EcosystemDetector: detectie boven
     * registratie, want een tweede lijst loopt uit de pas.
     */
    private function repoVoor(?string $pad): ?string
    {
        if (! $pad || ! is_dir($pad)) {
            return null;
        }

        $result = Process::path($pad)->timeout(15)->run(['git', 'remote', 'get-url', 'origin']);
        if (! $result->successful()) {
            return null;
        }

        $url = trim($result->output());
        if (! preg_match('#github\.com[:/]([^/]+/[^/\s]+?)(?:\.git)?$#', $url, $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function laatsteRunOpHoofdbranch(string $repo): ?array
    {
        // `gh api` lost de default branch zelf op via {owner}/{repo}; we vragen
        // expliciet om die branch zodat feature-branches buiten beeld blijven.
        $branch = $this->defaultBranch($repo);
        if ($branch === null) {
            return null;
        }

        $result = Process::timeout(30)->run([
            'gh', 'api',
            "repos/{$repo}/actions/runs?branch={$branch}&per_page=1&status=completed",
        ]);

        if (! $result->successful()) {
            return null;
        }

        $decoded = json_decode($result->output(), true);

        return $decoded['workflow_runs'][0] ?? null;
    }

    private function defaultBranch(string $repo): ?string
    {
        $result = Process::timeout(30)->run(['gh', 'api', "repos/{$repo}", '--jq', '.default_branch']);

        return $result->successful() && trim($result->output()) !== ''
            ? trim($result->output())
            : null;
    }

    private function ghBeschikbaar(): bool
    {
        return Process::timeout(20)->run(['gh', 'auth', 'status'])->successful();
    }
}
