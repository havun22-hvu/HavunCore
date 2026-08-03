<?php

namespace App\Console\Commands;

use App\Models\VaultSecret;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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

    private ?string $pat = null;

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
        // zou een ontbrekende sleutel elke run schoon laten lijken, precies de
        // stilte die dit commando bestaat om op te heffen.
        if (! $this->patBeschikbaar()) {
            $this->error('Geen GitHub-PAT in de Vault — Actions zijn NIET gecontroleerd.');
            $this->line("Dit is geen \"alles groen\": er is niet gekeken. Het secret heet 'github_pat_ro'.");

            return self::FAILURE;
        }

        $rood = 0;
        $gecontroleerd = 0;
        $reposGevonden = 0;
        $onbereikbaar = [];

        foreach ($projects as $slug => $project) {
            $repo = $this->repoVoor($this->checkoutPad($project));
            if ($repo === null) {
                continue; // geen git-remote: niets om te bewaken
            }

            $reposGevonden++;

            $branch = $this->defaultBranch($repo);

            if ($branch === null) {
                // Geen antwoord van de API: geen toegang, repo hernoemd, of
                // netwerk. Wat het ook is, er is niet gekeken.
                $onbereikbaar[] = "{$slug} ({$repo})";

                continue;
            }

            $run = $this->laatsteRunOpHoofdbranch($repo, $branch);
            if ($run === null) {
                continue; // repo zonder workflows: niets te melden
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

        // Een repo die de API niet wil geven, is niet gecontroleerd. Stil
        // overslaan zou hetzelfde beeld geven als "groen" — gemeten 04-08-2026
        // gaf de read-only PAT 404 op vier van de acht repo's en verdwenen die
        // alle vier zonder een woord.
        if ($onbereikbaar !== []) {
            $this->error('Niet op te vragen bij GitHub (geen toegang of hernoemd): ' . implode(', ', $onbereikbaar));

            // De cron stuurt stdout naar /dev/null, dus een regel op het scherm
            // is geen melding. Blinde bewaking moet dezelfde weg naar een mens
            // nemen als een rode build — anders is dit weer een signaal dat
            // bestaat en niemand bereikt.
            if (! $this->option('dry-run')) {
                $this->call('health:alert', [
                    'key' => 'actions-bewaking',
                    '--scope' => 'global',
                    '--status' => 'down',
                    '--severity' => 'warning',
                    '--title' => 'Actions-bewaking kan niet bij alle repo\'s',
                    '--body' => 'Niet op te vragen bij GitHub: ' . implode(', ', $onbereikbaar)
                        . '. De PAT `github_pat_ro` in de Vault heeft daar geen toegang toe.',
                ]);
            }

            return self::FAILURE;
        }

        // Geen énkele checkout gevonden is geen schone ronde maar een blinde.
        // Op de server was dat maandenlang de werkelijkheid — `D:/GitHub/...`
        // bestaat daar niet — en het kwam langs als exitcode 0. Een repo zónder
        // workflows telt hier niet als probleem: die is er gewoon.
        if ($reposGevonden === 0 && $projects !== []) {
            $this->error('Er is geen enkele repo gecontroleerd — geen checkout gevonden om een git-remote uit te lezen.');

            return self::FAILURE;
        }

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
     * De checkout waar we de git-remote uit lezen: Henks werkkopie als die
     * bestaat, anders die op de server.
     *
     * Zonder deze keuze vond het commando op de server nul repo's — `path` is
     * `D:/GitHub/...` en bestaat daar niet — en meldde het exitcode 0. Zelfde
     * val als bij de code-checks in QualitySafetyScanner.
     *
     * @param  array<string,mixed> $project
     */
    private function checkoutPad(array $project): ?string
    {
        foreach (['path', 'server_path', 'remote_path'] as $sleutel) {
            $pad = $project[$sleutel] ?? null;

            if (is_string($pad) && $pad !== '' && is_dir($pad)) {
                return $pad;
            }
        }

        return null;
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

        // Ook `git@github-<project>:owner/repo.git`. De prod-checkouts gebruiken
        // per repo een eigen SSH-host-alias zodat elke deploy-key maar één
        // project opent (één lek = één project). Een regex die alleen letterlijk
        // `github.com` accepteerde liet zes van de zeven checkouts op de server
        // wegvallen — de beveiligingsmaatregel maakte de bewaking blind.
        if (! preg_match('#github[\w.-]*[:/]([^/]+/[^/\s]+?)(?:\.git)?$#', $url, $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function laatsteRunOpHoofdbranch(string $repo, string $branch): ?array
    {
        // Expliciet om de default branch vragen, zodat feature-branches buiten
        // beeld blijven: een rode run daarop is werk in uitvoering.
        $antwoord = $this->github("repos/{$repo}/actions/runs", [
            'branch' => $branch,
            'per_page' => 1,
            'status' => 'completed',
        ]);

        return $antwoord['workflow_runs'][0] ?? null;
    }

    private function defaultBranch(string $repo): ?string
    {
        $branch = $this->github("repos/{$repo}")['default_branch'] ?? null;

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    /**
     * Rechtstreeks naar de GitHub-API, met de read-only PAT uit de Vault.
     *
     * Dit ging tot 04-08-2026 via `gh api`. Dat werkt op Henks machine en
     * nergens anders: op de server staat `gh` niet geïnstalleerd, dus de crons
     * van 07:00 en 19:00 hebben sinds hun bestaan niets gecontroleerd — vier
     * rode builds, waarvan HavunAdmin al drie maanden, vond niemand. Een extra
     * binary installeren om een HTTP-call te doen die je zelf kunt doen, is de
     * omweg; de PAT lag al in de Vault.
     *
     * @param  array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function github(string $pad, array $query = []): array
    {
        $antwoord = Http::withToken($this->pat ?? '')
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(30)
            ->get("https://api.github.com/{$pad}", $query);

        if (! $antwoord->successful()) {
            return [];
        }

        $decoded = $antwoord->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * De read-only PAT (`github_pat_ro`) staat in de Vault — dezelfde die de
     * webapp gebruikt voor mobiele-projectmonitoring. Ontbreekt hij, dan valt
     * er niets te meten, en dát meld je in plaats van een lege ronde als
     * "alles groen" te laten lezen.
     */
    private function patBeschikbaar(): bool
    {
        $secret = VaultSecret::where('key', 'github_pat_ro')->first();

        if ($secret === null) {
            return false;
        }

        try {
            $this->pat = $secret->getDecryptedValue();
        } catch (\Throwable) {
            return false;
        }

        return trim($this->pat) !== '';
    }
}
