<?php

namespace App\Services\QualitySafety;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Voegt de losse `qv:scan`-runs samen tot één beeld.
 *
 * Elke `qv:scan --only=X` schrijft zijn eigen run-bestand. De rapportage las er
 * één — het nieuwste — en daarmee verdween alles wat niet toevallig vlak vóór
 * `qv:log` (03:27) of `docs:handover` (04:00) had gedraaid. Gemeten op
 * 03-08-2026: de observatory-run van 04:37 vond een high (safehavun grade C) die
 * in geen enkel document terechtkwam, en de acht wekelijkse checks — die alle
 * acht ná 04:00 draaien — hadden nog nooit iets gerapporteerd gekregen.
 * Beide rapporten meldden die ochtend `critical 0 | high 0 | medium 0 | low 0`.
 *
 * Regel: per check telt de nieuwste run binnen het venster. Optellen over alle
 * runs zou een finding die gisteren gefixt is vandaag opnieuw laten opduiken.
 *
 * Plan: docs/kb/plans/qv-rapportage-venster-plan.md
 */
class MergedRunAssembler
{
    /**
     * Acht dagen: genoeg voor de wekelijkse checks (ssl, observatory, forms,
     * ratelimit, secrets, session-cookies, test-erosion, residu), met een dag
     * speling als de scheduler een keer overslaat.
     */
    public const VENSTER_DAGEN = 8;

    private const SEVERITIES = ['critical', 'high', 'medium', 'low', 'informational'];

    /** Markeert een fout die zegt "deze check heeft niet gedraaid". */
    public const ERROR_CHECK_ONTBREEKT = 'check-ontbreekt';

    /**
     * @return array{started_at:?string,checks:list<string>,projects:list<string>,findings:list<array<string,mixed>>,errors:list<array<string,mixed>>,totals:array<string,int>,check_runs:array<string,string>}|null
     */
    public function assemble(?string $disk = null, ?string $root = null): ?array
    {
        $disk = $disk ?? (string) config('quality-safety.storage.disk', 'local');
        $root = rtrim($root ?? (string) config('quality-safety.storage.root', 'qv-scans'), '/');

        $runs = $this->runsInVenster($disk, $root);

        if ($runs === []) {
            // Nul runs is de ernstigste uitkomst, niet de stilste. Tot 06-08-2026
            // gaf dit `null`, en elke aanroeper maakte daar een leeg rapport van
            // — een scheduler die helemaal stilstaat las als "niets aan de hand".
            return $this->legeUitslag();
        }

        // Oud naar nieuw, zodat een latere run van dezelfde check de vorige
        // overschrijft in plaats van andersom.
        usort($runs, fn (array $a, array $b): int => $a['_pad'] <=> $b['_pad']);

        $perCheck = [];
        $checkRuns = [];
        $projects = [];

        foreach ($runs as $run) {
            foreach ($this->checksVan($run) as $check) {
                $perCheck[$check] = $this->deelVanRun($run, $check);
                $checkRuns[$check] = (string) ($run['started_at'] ?? '');
            }

            $projects = array_merge($projects, array_filter((array) ($run['projects'] ?? []), 'is_string'));
        }

        $projects = array_values(array_unique($projects));
        sort($projects);

        ksort($perCheck);
        ksort($checkRuns);

        $findings = array_merge(...array_values(array_map(fn (array $d): array => $d['findings'], $perCheck)));
        $errors = array_merge(...array_values(array_map(fn (array $d): array => $d['errors'], $perCheck)));

        // Een check die niet meer draait verdwijnt uit dit rapport, en één regel
        // minder valt niemand op. Daarom staat afwezigheid er nu als fout in.
        $errors = array_merge($errors, $this->ontbrekendeChecks($checkRuns));

        return [
            // De nieuwste run bepaalt de kop; per check staat de eigen tijd in
            // check_runs, zodat een uitslag van zes dagen oud als zodanig leesbaar is.
            'started_at' => $this->nieuwsteStart($checkRuns),
            'checks' => array_keys($perCheck),
            'projects' => $projects,
            'findings' => array_values($findings),
            'errors' => array_values($errors),
            // Herberekend, niet opgeteld: de totals van een run die door een
            // nieuwere is vervangen mogen niet meetellen.
            'totals' => $this->totals($findings, $errors),
            'check_runs' => $checkRuns,
        ];
    }

    /**
     * Checks die binnen het venster niet (of te lang niet) gedraaid hebben.
     *
     * @param  array<string,string>  $checkRuns
     * @return list<array<string,mixed>>
     */
    private function ontbrekendeChecks(array $checkRuns): array
    {
        $gemist = [];

        foreach ((new VerwachteChecks())->ontbrekend($checkRuns) as $rij) {
            $gemist[] = [
                // Eigen soort: een check die niet draaide is iets anders dan een
                // check die draaide en een fout gaf. De eerste zegt dat er niets
                // gemeten is, de tweede dat het meten misging.
                'type' => self::ERROR_CHECK_ONTBREEKT,
                'check' => $rij['check'],
                'project' => null,
                'message' => "check `{$rij['check']}` {$rij['reden']} — er wordt op dit punt niets gemeten",
            ];
        }

        return $gemist;
    }

    /**
     * @return array<string,mixed>
     */
    private function legeUitslag(): array
    {
        $errors = $this->ontbrekendeChecks([]);

        return [
            'started_at' => null,
            'checks' => [],
            'projects' => [],
            'findings' => [],
            'errors' => $errors,
            'totals' => $this->totals([], $errors),
            'check_runs' => [],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function runsInVenster(string $disk, string $root): array
    {
        $storage = Storage::disk($disk);
        $grens = Carbon::now()->subDays(self::VENSTER_DAGEN)->toDateString();

        $runs = [];

        foreach ($storage->directories($root) as $map) {
            $datum = basename($map);

            if ($datum < $grens) {
                continue;
            }

            foreach ($storage->files($map) as $bestand) {
                if (! str_ends_with($bestand, '.json')) {
                    continue;
                }

                $data = json_decode((string) $storage->get($bestand), true);

                // Een onleesbare run mag de andere negen niet blokkeren, maar
                // ook niet stil verdwijnen: hij wordt zelf een error-rij.
                if (! is_array($data)) {
                    $data = [
                        'checks' => ['_onleesbaar:' . basename($bestand)],
                        'errors' => [[
                            'project' => '_scan',
                            'check' => 'run-bestand',
                            'message' => "Run-bestand is geen geldige JSON: {$bestand}",
                        ]],
                    ];
                }

                $data['_pad'] = $bestand;
                $runs[] = $data;
            }
        }

        return $runs;
    }

    /**
     * Een run zonder `checks`-lijst wordt onder zijn eigen pad bijgehouden, zodat
     * hij niet stil verdwijnt of een echte check overschrijft.
     *
     * @param  array<string,mixed> $run
     * @return list<string>
     */
    private function checksVan(array $run): array
    {
        $checks = array_values(array_filter((array) ($run['checks'] ?? []), 'is_string'));

        return $checks !== [] ? $checks : ['_onbekend:' . basename((string) $run['_pad'])];
    }

    /**
     * @param  array<string,mixed> $run
     * @return array{findings:list<array<string,mixed>>,errors:list<array<string,mixed>>}
     */
    private function deelVanRun(array $run, string $check): array
    {
        // Draaide een run meerdere checks tegelijk (`qv:scan` zonder --only),
        // dan hoort alleen het deel van déze check erbij.
        $filter = fn (array $rijen): array => array_values(array_filter(
            $rijen,
            fn ($r): bool => is_array($r) && (! isset($r['check']) || $r['check'] === $check),
        ));

        $enkeleCheck = count($this->checksVan($run)) === 1;

        return [
            'findings' => $enkeleCheck
                ? array_values(array_filter((array) ($run['findings'] ?? []), 'is_array'))
                : $filter((array) ($run['findings'] ?? [])),
            'errors' => $enkeleCheck
                ? array_values(array_filter((array) ($run['errors'] ?? []), 'is_array'))
                : $filter((array) ($run['errors'] ?? [])),
        ];
    }

    /**
     * @param  array<string,string> $checkRuns
     */
    private function nieuwsteStart(array $checkRuns): ?string
    {
        $tijden = array_values(array_filter($checkRuns));

        if ($tijden === []) {
            return null;
        }

        usort($tijden, fn (string $a, string $b): int => strcmp($a, $b));

        return end($tijden);
    }

    /**
     * @param  list<array<string,mixed>> $findings
     * @param  list<array<string,mixed>> $errors
     * @return array<string,int>
     */
    private function totals(array $findings, array $errors): array
    {
        $totals = array_fill_keys(self::SEVERITIES, 0);
        $totals['errors'] = count($errors);

        foreach ($findings as $finding) {
            $severity = (string) ($finding['severity'] ?? '');

            if (isset($totals[$severity])) {
                $totals[$severity]++;
            }
        }

        return $totals;
    }
}
