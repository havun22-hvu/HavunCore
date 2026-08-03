<?php

namespace App\Services\QualitySafety;

use App\Enums\Severity;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class QualitySafetyScanner
{
    /**
     * Checks die één keer draaien in plaats van per project.
     *
     * `registries` vergelijkt de projectregisters onderling; zijn onderwerp is
     * juist het project dat *niet* in de lijst staat. Binnen de per-project-loop
     * zou hij precies dat geval nooit zien — hij draait dan alleen voor
     * projecten die al geregistreerd zijn.
     */
    public const GLOBAL_CHECKS = ['registries', 'backup-coverage'];

    /**
     * De adressen van deze machine — één keer bepaald per scan, want elke
     * remote check vraagt ernaar.
     *
     * @var list<string>|null
     */
    private ?array $eigenAdressen = null;

    /**
     * @param  array<string,array<string,mixed>>  $projects
     * @param  array<int,string>                  $checks
     * @return array<string,mixed>
     */
    public function scan(array $projects, array $checks): array
    {
        $startedAt = Carbon::now();
        $findings = [];
        $errors = [];
        $ecosystems = [];
        $skipped = [];

        $detector = new EcosystemDetector;

        foreach ($projects as $slug => $project) {
            $project = $this->metBruikbaarPad($project);

            // Vastleggen hoe dít project gebouwd is, zodat het rapport kan
            // laten zien wát er gemeten is. Een nul zonder die context is niet
            // te onderscheiden van een nul omdat niemand keek.
            $pad = $project['path'] ?? null;
            if ($pad && is_dir($pad)) {
                $ecosystems[$slug] = array_keys($detector->detect($pad));
            }

            foreach ($checks as $check) {
                if (in_array($check, self::GLOBAL_CHECKS, true)) {
                    continue; // draait één keer, na de loop
                }

                $result = $this->runCheck($check, $slug, $project);

                foreach ($result['findings'] as $finding) {
                    $findings[] = $finding + [
                        'project' => $slug,
                        'check' => $check,
                    ];
                }

                if (! empty($result['error'])) {
                    $errors[] = [
                        'project' => $slug,
                        'check' => $check,
                        'message' => $result['error'],
                    ];
                }

                // Een overgeslagen check is géén schone check. Zonder dit
                // onderscheid is "0 findings" op een project waar `artisan`
                // niet gevonden werd (fout pad in de config) niet te
                // onderscheiden van een doorgemeten project.
                if (! empty($result['skipped'])) {
                    $skipped[] = [
                        'project' => $slug,
                        'check' => $check,
                        'reason' => $result['skipped'],
                    ];
                }
            }
        }

        foreach (array_intersect($checks, self::GLOBAL_CHECKS) as $check) {
            $result = $this->runGlobalCheck($check);

            foreach ($result['findings'] as $finding) {
                // Het project waar de bevinding over gaat, zodat het rapport
                // hem bij de juiste regel toont — ook als dat project nergens
                // in `$projects` voorkomt, wat bij drift het hele punt is.
                $findings[] = $finding + [
                    'project' => $finding['slug'] ?? '_registries',
                    'check' => $check,
                ];
            }

            if (! empty($result['error'])) {
                $errors[] = ['project' => '_registries', 'check' => $check, 'message' => $result['error']];
            }
        }

        return [
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => Carbon::now()->toIso8601String(),
            'projects' => array_keys($projects),
            'ecosystems' => $ecosystems,
            'checks' => $checks,
            'findings' => $findings,
            'errors' => $errors,
            'skipped' => $skipped,
            'totals' => $this->totals($findings, $errors),
        ];
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function runCheck(string $check, string $slug, array $project): array
    {
        return match ($check) {
            'composer' => $this->composerAudit($project),
            'npm' => $this->npmAudit($project),
            'cargo' => $this->cargoAudit($project),
            'deps-coverage' => $this->dependencyCoverage($project),
            'ssl' => $this->sslExpiry($project),
            'observatory' => $this->observatory($project),
            'server' => $this->serverHealth($project),
            'forms' => $this->formsCoverage($project),
            'ratelimit' => $this->rateLimitCoverage($project),
            'secrets' => $this->secretsScan($project),
            'session-cookies' => $this->sessionCookieFlags($project),
            'test-erosion' => $this->testErosion($project),
            'debug-mode' => $this->debugModeFlag($project),
            'residu' => $this->residueCheck($slug, $project),
            default => ['findings' => [], 'error' => "Unknown check: {$check}"],
        };
    }

    /**
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function runGlobalCheck(string $check): array
    {
        return match ($check) {
            'registries' => $this->registryDrift(),
            'backup-coverage' => $this->backupCoverage(),
            default => ['findings' => [], 'error' => "Unknown global check: {$check}"],
        };
    }

    /**
     * Kan deze check hier überhaupt meten? Drie uitkomsten, en het verschil
     * tussen de laatste twee is het hele punt.
     *
     * - pad bestaat → `null`, de check draait gewoon
     * - geen checkout hier én geen serverpad in de config → **overgeslagen**:
     *   een mobiele app, een desktop-app of een geparkeerd project hoort hier
     *   niet te staan, en dat is geen storing
     * - wél een serverpad geconfigureerd maar de map is er niet → **error**:
     *   iemand verwacht hier een checkout en die is weg
     *
     * Waarom niet alles een error: zolang de nachtelijke scan `errors: 5` meldt
     * voor projecten die er terecht niet zijn, leer je dat getal negeren — en
     * dan zegt het niets meer op de nacht dat er wél iets omvalt. Precies zo kon
     * de backupcheck drie dagen ongemerkt blind staan. Overgeslagen is óók niet
     * hetzelfde als schoon: het komt in de lijst "niet gedraaid" terecht.
     *
     * @param  array<string,mixed> $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string, skipped?:string}|null
     */
    private function padProbleem(array $project): ?array
    {
        $pad = $project['path'] ?? null;

        if (is_string($pad) && $pad !== '' && is_dir($pad)) {
            return null;
        }

        foreach (['server_path', 'remote_path'] as $sleutel) {
            if (! empty($project[$sleutel])) {
                return [
                    'findings' => [],
                    'error' => sprintf(
                        'Projectmap niet gevonden, ook niet op het geconfigureerde serverpad (%s)',
                        $project[$sleutel],
                    ),
                ];
            }
        }

        return [
            'findings' => [],
            'skipped' => 'geen checkout op deze machine, en er is geen serverpad geconfigureerd',
        ];
    }

    /**
     * Kiest het pad dat op déze machine bestaat.
     *
     * Er zijn er twee: `path` is Henks werkkopie (`D:/GitHub/...`), daarnaast
     * staat de checkout op de server. Alle code-checks gebruikten `path` — en
     * dus mat de nachtelijke scan op de server niets: gemeten op 03-08-2026
     * leverden composer, npm en cargo daar samen 40 keer
     * `Project path not found: D:/GitHub/...` op, wat neerkomt op nul
     * gecontroleerde projecten. Dat is waarom 34 composer-advisories op
     * Herdenkingsportaal dertien commits bleven liggen tot Henk ze zelf zag.
     *
     * Het serverpad heet in de twee registers **niet hetzelfde**:
     * `havun-projects.php` zegt `server_path`, de scanlijst in
     * `quality-safety.php` zegt `remote_path` — en de scanner leest die laatste.
     * Beide staan hieronder; alleen de eerste kennen loste niets op.
     *
     * De werkkopie wint als hij bestaat: die heeft dev-dependencies en loopt
     * voor op de server. Bestaat geen van beide, dan blijft `path` staan zodat
     * de check zelf een error geeft — een project zonder meetbare checkout mag
     * niet als schoon langskomen.
     *
     * @param  array<string,mixed> $project
     * @return array<string,mixed>
     */
    private function metBruikbaarPad(array $project): array
    {
        $pad = $project['path'] ?? null;

        if (is_string($pad) && is_dir($pad)) {
            return $project;
        }

        foreach (['server_path', 'remote_path'] as $sleutel) {
            $kandidaat = $project[$sleutel] ?? null;

            if (is_string($kandidaat) && is_dir($kandidaat)) {
                $project['path'] = $kandidaat;

                return $project;
            }
        }

        return $project;
    }

    /**
     * Toetst of er vannacht daadwerkelijk geback-upt is wat elk project nodig
     * heeft.
     *
     * De meting zelf gebeurt op de server, in `havun-backup-manifest.sh`: dat
     * script draait als root aan het eind van de backuprun en schrijft wát het
     * opleverde naar `/var/lib/havun/backup-manifest.json` — bestandsnamen,
     * groottes, tijden, en de `DB_DATABASE` van elke app. Hier wordt dat
     * bestand alleen gelezen.
     *
     * Waarom niet meer zelf meten: tot 02-08-2026 vroeg deze check de backupmap
     * op via SSH naar `root@`. Draait de scan op de server zelf — en dat doet
     * hij, elke minuut vanuit roots crontab — dan is dat een verbinding naar
     * zichzelf, zonder sleutel. De cron rapporteerde daardoor elke nacht
     * `errors=1, high=0`. Sinds 03-08 is er nog één definitie van wat er gemeten
     * wordt, in bash, en leest deze kant hem lokaal of via SSH.
     *
     * @return array{findings:array<int,array<string,mixed>>, error?:string, skipped?:string}
     */
    private function backupCoverage(): array
    {
        $verwacht = (array) config('havun-backup.verificatie.verwacht', []);

        if ($verwacht === []) {
            return ['findings' => [], 'skipped' => 'geen verwachting geconfigureerd (havun-backup.verificatie)'];
        }

        $pad = (string) config('havun-backup.verificatie.manifest', '/var/lib/havun/backup-manifest.json');

        // Op de server ligt het manifest gewoon op schijf; daarbuiten (Henks
        // machine) halen we hetzelfde bestand op via SSH. Een bestand dat je
        // lokaal kunt lezen via een SSH-sessie naar jezelf opvragen was de fout.
        if (is_readable($pad)) {
            return $this->toetsBackupdekking((string) file_get_contents($pad), $verwacht);
        }

        $result = $this->runRemote(
            (string) config('quality-safety.residu.host', '188.245.159.115'),
            (string) config('quality-safety.residu.user', 'root'),
            'cat ' . escapeshellarg($pad),
            20,
        );

        if (! $result['ok']) {
            // Geen stille `error` meer: die kwam vanaf 01-08-2026 elke nacht
            // langs als `errors=1, high=0` en niets las dat eerste veld. Een
            // mislukte meting is nu ook een bevinding.
            return $this->toetsBackupdekking('', $verwacht)
                + ['error' => 'Backupmanifest niet op te halen: ' . ($result['error'] ?? 'onbekend')];
        }

        return $this->toetsBackupdekking($result['output'], $verwacht);
    }

    /**
     * @param  string $manifestJson  lege string = er is niets gemeten
     * @param  array<string,array<int|string,string|int>> $verwacht
     * @return array{findings:array<int,array<string,mixed>>}
     */
    private function toetsBackupdekking(string $manifestJson, array $verwacht): array
    {
        $manifest = $this->leesManifest($manifestJson);

        return [
            'findings' => (new BackupCoverageDetector)->detect(
                (array) config('havun-projects', []),
                $verwacht,
                (array) config('havun-backup.verificatie.uitgezonderd', []),
                $manifest['bestanden'],
                (array) config('havun-backup.monitoring', []),
                $manifest['app_databases'],
                $manifest['leeftijd_uren'],
            ),
        ];
    }

    /**
     * Zet het manifest om naar wat de detector verwacht.
     *
     * Leeftijden worden gerekend tegen `gemaakt_op` — de klok van de server op
     * het moment van meten. Tegen de klok van deze machine rekenen zou een
     * tijdzoneverschil als "verouderde backup" laten lezen.
     *
     * @return array{bestanden:array<string,array{leeftijd_uren:float,bytes:int}>, app_databases:array<string,string>, leeftijd_uren:float|null}
     */
    private function leesManifest(string $json): array
    {
        $data = json_decode($json, true);

        // Geen bruikbaar manifest = niet gemeten. `leeftijd_uren: null` zegt dat
        // tegen de detector, die dan niets anders meer beweert.
        if (! is_array($data) || ! isset($data['gemaakt_op'])) {
            return ['bestanden' => [], 'app_databases' => [], 'leeftijd_uren' => null];
        }

        $gemetenOp = (int) $data['gemaakt_op'];
        $bestanden = [];

        foreach ((array) ($data['bestanden'] ?? []) as $bestand) {
            if (! is_array($bestand) || empty($bestand['naam'])) {
                continue;
            }

            $bestanden[(string) $bestand['naam']] = [
                'bytes' => (int) ($bestand['bytes'] ?? 0),
                'leeftijd_uren' => $this->urenSinds((int) ($bestand['mtime'] ?? $gemetenOp), $gemetenOp),
            ];
        }

        return [
            'bestanden' => $bestanden,
            'app_databases' => array_map('strval', (array) ($data['app_databases'] ?? [])),
            // Het manifest wordt na elke backuprun herschreven; is het oud, dan
            // staat de meetketen stil en zegt de inhoud niets over vannacht.
            'leeftijd_uren' => $this->urenSinds($gemetenOp, time()),
        ];
    }

    private function urenSinds(int $moment, int $peil): float
    {
        return round(max(0, $peil - $moment) / 3600, 2);
    }

    /**
     * Vergelijkt havun-projects.php met quality-safety.php. Zie
     * RegistryDriftDetector voor de regels en het incident erachter.
     *
     * @return array{findings:array<int,array<string,mixed>>}
     */
    private function registryDrift(): array
    {
        return [
            'findings' => (new RegistryDriftDetector)->detect(
                (array) config('havun-projects', []),
                (array) config('quality-safety.projects', []),
            ),
        ];
    }

    /**
     * Execute a single remote shell command via SSH and capture stdout.
     *
     * Shared between `serverHealth` and `residueCheck`; both run one
     * SSH session per project and parse structured stdout. Caller
     * decides what to do with errors (different finding shapes).
     *
     * @return array{ok:bool, output:string, exit_code:int, error:?string}
     */
    /**
     * Wijst `$host` naar de machine waar deze scan op draait?
     *
     * Loopback altijd; verder wordt het IP van de host vergeleken met de
     * adressen van de eigen netwerkinterfaces. `net_get_interfaces()` bestaat
     * niet op Windows — daar valt de vergelijking terug op de hostnaam, wat
     * klopt: op Henks machine is de server nooit "hier".
     */
    private function isDezeMachine(string $host): bool
    {
        $doel = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        return in_array($doel, $this->eigenAdressen(), true);
    }

    /**
     * @return list<string>
     */
    private function eigenAdressen(): array
    {
        if ($this->eigenAdressen !== null) {
            return $this->eigenAdressen;
        }

        $adressen = ['127.0.0.1', '::1'];

        $eigenNaam = gethostname();

        if (is_string($eigenNaam) && $eigenNaam !== '') {
            $adressen[] = gethostbyname($eigenNaam);
        }

        if (function_exists('net_get_interfaces') && ($interfaces = @net_get_interfaces()) !== false) {
            foreach ($interfaces as $interface) {
                foreach (array_merge($interface['unicast'] ?? [], []) as $unicast) {
                    foreach (['address'] as $veld) {
                        if (! empty($unicast[$veld])) {
                            $adressen[] = (string) $unicast[$veld];
                        }
                    }
                }
            }
        }

        return $this->eigenAdressen = array_values(array_unique(array_filter($adressen)));
    }

    private function runRemote(string $host, string $user, string $remoteCmd, int $timeout): array
    {
        // Is `$host` deze machine, dan draaien we het commando gewoon. Een
        // SSH-sessie naar jezelf opzetten vraagt om een sleutel die er niet is
        // en ook niet hoort te zijn: op 03-08-2026 bleek `serverHealth` daardoor
        // elke nacht `Permission denied (publickey)` te melden en dus nooit iets
        // te meten — op de server waar hij nu juist over gaat.
        if ($this->isDezeMachine($host)) {
            $result = Process::timeout($timeout)->run($remoteCmd);
        } else {
            $bin = config('quality-safety.bin.ssh', 'ssh');
            $sshOpts = (array) config('quality-safety.server.ssh_options', []);

            $cmd = array_merge([$bin], $sshOpts, ["{$user}@{$host}", $remoteCmd]);
            $result = Process::timeout($timeout)->run($cmd);
        }

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput()) ?: trim($result->output());

            return [
                'ok' => false,
                'output' => '',
                'exit_code' => $result->exitCode(),
                'error' => $stderr ?: 'no output',
            ];
        }

        return [
            'ok' => true,
            'output' => $result->output(),
            'exit_code' => 0,
            'error' => null,
        ];
    }

    /**
     * Repo-hygiene residu check — detects .env backup files that exceed the
     * lifecycle defined in docs/kb/reference/repo-hygiene-policy.md:
     *
     * - in-place .env.bak* older than `residu_archive_after_days` (default 14)
     *   → candidates for archive (Laag 2 cleanup)
     * - archived files older than `residu_purge_after_days` (default 90)
     *   → candidates for purge
     * - in-place .env.bak* whose name does not match the canonical format
     *   `.env.bak.YYYY-MM-DD-HHMMSS` → naming-convention drift
     *
     * Read-only: this check never deletes or moves files; it surfaces findings
     * so a human (or future admin-action UI) can decide.
     *
     * Skipped silently for project entries without `remote_path`.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function residueCheck(string $slug, array $project): array
    {
        $remotePath = $project['remote_path'] ?? null;
        if (! $remotePath) {
            return ['findings' => []];
        }

        $archiveRoot = rtrim((string) config('quality-safety.residu.archive_root', '/var/backups/havun-env'), '/');
        $archiveAfter = (int) config('quality-safety.thresholds.residu_archive_after_days', 14);
        $purgeAfter = (int) config('quality-safety.thresholds.residu_purge_after_days', 90);

        $archiveDir = $archiveRoot . '/' . $slug;

        // When the remote path is locally accessible (scanner is running on the
        // production host itself), skip SSH and inventory directly. Without this,
        // the scheduled cron on Hetzner ends up SSH-ing back into 127.0.0.1 as
        // its own www-data user, hitting "Permission denied (publickey)".
        $output = is_dir($remotePath)
            ? $this->scanResiduLocal($remotePath, $archiveDir)
            : null;

        if ($output === null) {
            $host = config('quality-safety.residu.host');
            $user = config('quality-safety.residu.user', 'root');

            if (! $host) {
                return ['findings' => [], 'error' => 'residu: qv-residu host not configured'];
            }

            $remoteCmd = $this->buildResiduScanScript($remotePath, $archiveDir);
            $remote = $this->runRemote($host, $user, $remoteCmd, 10);

            if (! $remote['ok']) {
                return [
                    'findings' => [],
                    'error' => "SSH residu scan failed for {$slug} (exit {$remote['exit_code']}): {$remote['error']}",
                ];
            }

            $output = $remote['output'];
        }

        $findings = [];
        foreach ($this->splitLines($output) as $line) {
            $parts = explode('|', $line);
            if (count($parts) !== 3) {
                continue;
            }
            [$type, $path, $ageRaw] = $parts;
            $age = (int) $ageRaw;
            $name = basename($path);

            if ($type === 'inplace' && $age > $archiveAfter) {
                $findings[] = [
                    'severity' => 'informational',
                    'title' => "{$name} is {$age}d old (>{$archiveAfter}d) — candidate for archive",
                    'file' => $path,
                    'age_days' => $age,
                    'message' => "{$slug}: {$name} ({$age}d) ready for {$archiveDir}/",
                ];
            }

            if ($type === 'inplace' && ! $this->matchesCanonicalBackupName($name)) {
                $findings[] = [
                    'severity' => 'low',
                    'title' => "{$name} doesn't match canonical name .env.bak.YYYY-MM-DD-HHMMSS",
                    'file' => $path,
                    'age_days' => $age,
                    'message' => "{$slug}: naming drift — {$name} (zie repo-hygiene-policy.md)",
                ];
            }

            if ($type === 'archive' && $age > $purgeAfter) {
                $findings[] = [
                    'severity' => 'informational',
                    'title' => "Archived {$name} is {$age}d old (>{$purgeAfter}d) — candidate for purge",
                    'file' => $path,
                    'age_days' => $age,
                    'message' => "{$slug}: archived {$name} ({$age}d) ready for purge",
                ];
            }
        }

        return ['findings' => $findings];
    }

    /**
     * Local equivalent of the SSH-side scan. Same `TYPE|PATH|AGE_DAYS` output
     * shape so the parser doesn't care which path produced it. Used when the
     * scanner runs on the same host as the checkouts (e.g. server-side cron).
     */
    private function scanResiduLocal(string $remotePath, string $archiveDir): string
    {
        $now = time();
        $lines = [];
        foreach ([
            ['inplace', glob(rtrim($remotePath, '/') . '/.env.bak*') ?: []],
            ['archive', glob(rtrim($archiveDir, '/') . '/*') ?: []],
        ] as [$type, $files]) {
            foreach ($files as $file) {
                if (! is_file($file)) {
                    continue;
                }
                $age = (int) floor(($now - filemtime($file)) / 86400);
                $lines[] = "{$type}|{$file}|{$age}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build the bash that inventories .env.bak* in the checkout and the
     * archive dir. Emits `TYPE|PATH|AGE_DAYS` per file. Glob iteration
     * with `[ -f ]` guard handles the no-match case (literal pattern survives
     * unmatched in non-nullglob bash).
     */
    private function buildResiduScanScript(string $remotePath, string $archiveDir): string
    {
        $template = <<<'BASH'
now=$(date +%s)
for f in {REMOTE}/.env.bak*; do
  [ -f "$f" ] || continue
  age=$(( ( now - $(stat -c%Y "$f") ) / 86400 ))
  echo "inplace|$f|$age"
done
for f in {ARCHIVE}/*; do
  [ -f "$f" ] || continue
  age=$(( ( now - $(stat -c%Y "$f") ) / 86400 ))
  echo "archive|$f|$age"
done
BASH;

        // Strip CR so the script survives CRLF-saved sources on Windows
        // (remote bash chokes on `do\r`).
        return str_replace("\r\n", "\n", strtr($template, [
            '{REMOTE}' => escapeshellarg($remotePath),
            '{ARCHIVE}' => escapeshellarg($archiveDir),
        ]));
    }

    /**
     * Canonical .env-backup name per repo-hygiene-policy.md:
     * `.env.bak.YYYY-MM-DD-HHMMSS` (e.g. `.env.bak.2026-05-09-143015`).
     */
    private function matchesCanonicalBackupName(string $basename): bool
    {
        return (bool) preg_match('/^\.env\.bak\.\d{4}-\d{2}-\d{2}-\d{6}$/', $basename);
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function composerAudit(array $project): array
    {
        $path = $project['path'] ?? null;

        if (($probleem = $this->padProbleem($project)) !== null) {
            return $probleem;
        }
        if (! file_exists(rtrim($path, '/\\') . '/composer.json')) {
            return ['findings' => []];
        }

        $bin = config('quality-safety.bin.composer', 'composer');
        $result = Process::path($path)->timeout(120)
            ->run([$bin, 'audit', '--format=json', '--no-interaction']);

        $decoded = $this->decodeAuditJson($result);
        if ($decoded === null) {
            return $result->exitCode() === 0
                ? ['findings' => []]
                : ['findings' => [], 'error' => 'composer audit produced no parseable JSON'];
        }

        $findings = [];
        foreach ($decoded['advisories'] ?? [] as $package => $items) {
            foreach ($items as $advisory) {
                $findings[] = [
                    'severity' => $this->normalizeSeverity($advisory['severity'] ?? 'medium'),
                    'title' => $advisory['title'] ?? ($advisory['cve'] ?? 'Unknown advisory'),
                    'package' => $package,
                    'advisory_id' => $advisory['advisoryId'] ?? ($advisory['cve'] ?? null),
                    'affected_versions' => $advisory['affectedVersions'] ?? null,
                    'message' => sprintf(
                        '%s %s — %s',
                        $package,
                        $advisory['affectedVersions'] ?? '',
                        $advisory['title'] ?? ($advisory['cve'] ?? '')
                    ),
                ];
            }
        }

        return ['findings' => $findings];
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function npmAudit(array $project): array
    {
        $path = $project['path'] ?? null;

        if (($probleem = $this->padProbleem($project)) !== null) {
            return $probleem;
        }
        if (! file_exists(rtrim($path, '/\\') . '/package.json')) {
            return ['findings' => []];
        }

        $bin = config('quality-safety.bin.npm', 'npm');
        $result = Process::path($path)->timeout(180)
            ->run([$bin, 'audit', '--json', '--omit=dev']);

        $decoded = $this->decodeAuditJson($result);
        if ($decoded === null) {
            return ['findings' => [], 'error' => 'npm audit produced no parseable JSON'];
        }

        $findings = [];
        foreach ($decoded['vulnerabilities'] ?? [] as $pkg => $vuln) {
            $viaItems = is_array($vuln['via'] ?? null) ? $vuln['via'] : [];
            $title = 'npm vulnerability';
            foreach ($viaItems as $via) {
                if (is_array($via) && isset($via['title'])) {
                    $title = $via['title'];
                    break;
                }
            }

            $findings[] = [
                'severity' => $this->normalizeSeverity($vuln['severity'] ?? 'low'),
                'title' => $title,
                'package' => $pkg,
                'range' => $vuln['range'] ?? null,
                'message' => sprintf('%s %s — %s', $pkg, $vuln['range'] ?? '', $title),
            ];
        }

        return ['findings' => $findings];
    }

    /**
     * `cargo audit` over elke Cargo.lock in het project.
     *
     * Waarom niet alleen de root: Vusista2 heeft géén Cargo.toml in de root en
     * vier Cargo.lock-bestanden in submappen. Een check die alleen de root
     * bekijkt, meldt daar nul — en die nul is dan de afwezigheid van een
     * meting, niet de uitkomst ervan.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function cargoAudit(array $project): array
    {
        $path = $project['path'] ?? null;

        if (($probleem = $this->padProbleem($project)) !== null) {
            return $probleem;
        }

        $lockfiles = (new EcosystemDetector)->detect($path)['rust'] ?? [];
        if ($lockfiles === []) {
            return ['findings' => []];
        }

        $bin = config('quality-safety.bin.cargo', 'cargo');
        $findings = [];
        $root = rtrim(str_replace('\\', '/', $path), '/');

        foreach ($lockfiles as $relatief) {
            $crateDir = dirname($root . '/' . $relatief);
            $result = Process::path($crateDir)->timeout(180)
                ->run([$bin, 'audit', '--json']);

            $decoded = $this->decodeAuditJson($result);
            if ($decoded === null) {
                // Geen parseerbare JSON is hier geen "schoon": meestal ontbreekt
                // cargo-audit. Dat moet zichtbaar zijn, niet wegvallen als nul.
                return [
                    'findings' => $findings,
                    'error' => "cargo audit gaf geen bruikbare JSON in {$relatief} — is cargo-audit geïnstalleerd?",
                ];
            }

            foreach ($decoded['vulnerabilities']['list'] ?? [] as $vuln) {
                $findings[] = $this->cargoFinding(
                    $vuln,
                    $this->normalizeSeverity(
                        $vuln['advisory']['cvss'] ?? ($vuln['advisory']['severity'] ?? 'high')
                    ),
                    $relatief
                );
            }

            // `warnings` staat náást `vulnerabilities` en bevat wat RustSec niet
            // als kwetsbaarheid telt maar wel meldt: crates zonder onderhoud (dus
            // zonder toekomstige security-fixes) en `unsound` code. Alleen de
            // vulnerabilities lezen gaf op de Tauri-crate 0 terwijl er 17 van
            // deze in stonden — dezelfde stilte die dit hele plan opruimt.
            foreach ($decoded['warnings'] ?? [] as $soort => $items) {
                foreach ((array) $items as $item) {
                    $findings[] = $this->cargoFinding($item, match ($soort) {
                        'unsound' => 'medium',
                        'unmaintained' => 'low',
                        default => 'info',
                    }, $relatief, $soort);
                }
            }
        }

        return ['findings' => $findings];
    }

    /**
     * Eén cargo-audit-item (vulnerability of warning) naar een finding.
     * Beide vormen dragen dezelfde `advisory`/`package`-structuur.
     *
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function cargoFinding(array $item, string $severity, string $lockfile, ?string $soort = null): array
    {
        $advisory = $item['advisory'] ?? [];
        $package = $item['package']['name'] ?? 'onbekend';
        $titel = $advisory['title'] ?? ($advisory['id'] ?? 'Rust advisory');
        $label = $soort !== null ? "{$soort}: " : '';

        return [
            'severity' => $severity,
            'title' => $label . $titel,
            'package' => $package,
            'advisory_id' => $advisory['id'] ?? null,
            'message' => sprintf(
                '%s%s (%s) — %s [%s]',
                $label,
                $package,
                $item['package']['version'] ?? '?',
                $titel,
                $lockfile
            ),
        ];
    }

    /**
     * Meldt ecosystemen die we wél zien maar niet auditen.
     *
     * Dit is de check die de valse nul opruimt. Zonder hem betekent
     * `critical 0 · high 0 · medium 0` op een Go- of Python-project "niemand
     * heeft gekeken", terwijl het leest als "niets gevonden". Een ecosysteem
     * dat we niet kunnen meten is een **bevinding**, geen stilte.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function dependencyCoverage(array $project): array
    {
        $path = $project['path'] ?? null;
        if (! $path || ! is_dir($path)) {
            return ['findings' => []];
        }

        $detector = new EcosystemDetector;
        $gedetecteerd = $detector->detect($path);

        if ($gedetecteerd === []) {
            return ['findings' => []];
        }

        $findings = [];
        foreach ($detector->unauditable($gedetecteerd) as $ecosysteem) {
            $manifesten = $gedetecteerd[$ecosysteem];
            $findings[] = [
                'severity' => 'high',
                'title' => "Dependencies niet gemeten: {$ecosysteem}",
                'ecosystem' => $ecosysteem,
                'message' => sprintf(
                    '%s gedetecteerd (%s) maar er draait geen audit voor — dit is NIET "geen bevindingen", dit is "niet gekeken". Ondersteund: %s.',
                    $ecosysteem,
                    implode(', ', array_slice($manifesten, 0, 3)),
                    implode('/', EcosystemDetector::AUDITABLE)
                ),
            ];
        }

        return ['findings' => $findings];
    }

    /**
     * @return array<string,mixed>|null  null when output is not valid JSON
     */
    private function decodeAuditJson(ProcessResult $result): ?array
    {
        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function sslExpiry(array $project): array
    {
        $url = $project['url'] ?? null;

        if (! $url) {
            return ['findings' => [], 'skipped' => 'geen url geregistreerd — geen publiek endpoint om te meten'];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return ['findings' => [], 'error' => "Cannot parse host from url: {$url}"];
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $fp = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $fp) {
            return [
                'findings' => [],
                'error' => "SSL connect failed for {$host}: {$errstr}",
            ];
        }

        $params = stream_context_get_params($fp);
        fclose($fp);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (! $cert) {
            return ['findings' => [], 'error' => "Could not read peer certificate for {$host}"];
        }

        $parsed = openssl_x509_parse($cert);
        if (! $parsed || empty($parsed['validTo_time_t'])) {
            return ['findings' => [], 'error' => "Could not parse certificate for {$host}"];
        }

        $expiresAt = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $daysLeft = (int) round(Carbon::now()->diffInDays($expiresAt, false));

        $warn = (int) config('quality-safety.thresholds.ssl_warning_days', 30);
        $crit = (int) config('quality-safety.thresholds.ssl_critical_days', 7);

        if ($daysLeft <= $crit) {
            $severity = 'critical';
        } elseif ($daysLeft <= $warn) {
            $severity = 'high';
        } else {
            return ['findings' => []];
        }

        return [
            'findings' => [[
                'severity' => $severity,
                'title' => "SSL certificate expires in {$daysLeft} days",
                'host' => $host,
                'expires_at' => $expiresAt->toIso8601String(),
                'message' => "{$host} — cert expires {$expiresAt->toDateString()} ({$daysLeft} days)",
            ]],
        ];
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function observatory(array $project): array
    {
        $url = $project['url'] ?? null;
        if (! $url) {
            return ['findings' => []];
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return ['findings' => [], 'error' => "Cannot parse host from url: {$url}"];
        }

        $endpoint = rtrim(config('quality-safety.observatory.endpoint', 'https://observatory-api.mdn.mozilla.net/api/v2/scan'), '/');

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders(['Content-Length' => '0'])
                ->post($endpoint . '?' . http_build_query(['host' => $host]));
        } catch (\Throwable $e) {
            return ['findings' => [], 'error' => "Observatory request failed for {$host}: {$e->getMessage()}"];
        }

        if (! $response->ok()) {
            return [
                'findings' => [],
                'error' => "Observatory returned HTTP {$response->status()} for {$host}",
            ];
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['grade'])) {
            return ['findings' => [], 'error' => "Observatory response missing grade for {$host}"];
        }

        $grade = (string) $data['grade'];
        $score = $data['score'] ?? null;
        $minGrade = (string) config('quality-safety.observatory.min_grade', 'B');

        if ($this->gradeRank($grade) >= $this->gradeRank($minGrade)) {
            return ['findings' => []];
        }

        $severity = in_array(strtoupper($grade), ['D', 'F'], true) ? 'critical' : 'high';

        return [
            'findings' => [[
                'severity' => $severity,
                'title' => "Observatory grade {$grade} (score {$score}, minimum {$minGrade})",
                'host' => $host,
                'grade' => $grade,
                'score' => $score,
                'message' => "{$host} — Observatory grade {$grade} (< {$minGrade})",
            ]],
        ];
    }

    /**
     * SSH-based server health: disk usage + failed systemd units.
     *
     * Runs only for project entries that declare a `host`. Other entries are
     * silently skipped so the same check can be added to `--only=server` runs
     * without polluting per-project loops.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function serverHealth(array $project): array
    {
        $host = $project['host'] ?? null;
        if (! $host) {
            return ['findings' => []];
        }

        $user = $project['user'] ?? 'root';
        $remoteCmd = 'df -P -B1 && echo ---SYSTEMD--- && systemctl --failed --no-legend --plain --type=service 2>/dev/null || true';

        $remote = $this->runRemote($host, $user, $remoteCmd, 30);

        if (! $remote['ok']) {
            return [
                'findings' => [],
                'error' => "SSH to {$host} failed (exit {$remote['exit_code']}): {$remote['error']}",
            ];
        }

        [$dfOutput, $systemdOutput] = $this->splitServerOutput($remote['output']);

        $warnPct = (int) config('quality-safety.thresholds.disk_warning_pct', 90);
        $critPct = (int) config('quality-safety.thresholds.disk_critical_pct', 95);
        $ignorePrefixes = (array) config('quality-safety.server.disk_ignore_mounts', []);

        $findings = array_merge(
            $this->parseDiskFindings($dfOutput, $host, $warnPct, $critPct, $ignorePrefixes),
            $this->parseSystemdFindings($systemdOutput, $host),
        );

        return ['findings' => $findings];
    }

    /**
     * @return array{0:string, 1:string}  [df-section, systemd-section]
     */
    private function splitServerOutput(string $raw): array
    {
        $parts = preg_split('/^---SYSTEMD---\s*$/m', $raw, 2);

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    /**
     * @return array<int,string>
     */
    private function splitLines(string $raw): array
    {
        return preg_split('/\R/', trim($raw)) ?: [];
    }

    /**
     * Parses POSIX `df -P` output (header line skipped).
     *
     * Last two columns are used: capacity (e.g. `91%`) and mountpoint. This
     * tolerates filesystem names with embedded spaces because we index from
     * the end of each row.
     *
     * @param  array<int,string>  $ignorePrefixes
     * @return array<int,array<string,mixed>>
     */
    private function parseDiskFindings(string $df, string $host, int $warn, int $crit, array $ignorePrefixes): array
    {
        $findings = [];
        $lines = $this->splitLines($df);

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($i === 0 || $line === '') {
                continue;
            }

            $cols = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($cols) < 6) {
                continue;
            }

            $capacity = $cols[count($cols) - 2] ?? '';
            $mount = $cols[count($cols) - 1] ?? '';

            if (! preg_match('/^(\d+)%$/', $capacity, $m)) {
                continue;
            }
            $pct = (int) $m[1];

            if ($this->mountIsIgnored($mount, $ignorePrefixes)) {
                continue;
            }

            if ($pct >= $crit) {
                $severity = 'critical';
            } elseif ($pct >= $warn) {
                $severity = 'high';
            } else {
                continue;
            }

            $findings[] = [
                'severity' => $severity,
                'title' => "Disk usage {$pct}% on {$mount}",
                'host' => $host,
                'mount' => $mount,
                'usage_pct' => $pct,
                'message' => "{$host} {$mount} — {$pct}% full (warn={$warn}%, crit={$crit}%)",
            ];
        }

        return $findings;
    }

    /**
     * @param  array<int,string>  $ignorePrefixes
     */
    private function mountIsIgnored(string $mount, array $ignorePrefixes): bool
    {
        foreach ($ignorePrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($mount, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseSystemdFindings(string $systemd, string $host): array
    {
        $findings = [];
        $lines = $this->splitLines($systemd);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $unit = $cols[0] ?? '';
            if ($unit === '' || ! str_contains($unit, '.')) {
                continue;
            }

            $findings[] = [
                'severity' => 'high',
                'title' => "systemd unit failed: {$unit}",
                'host' => $host,
                'unit' => $unit,
                'message' => "{$host} — failed unit {$unit}",
            ];
        }

        return $findings;
    }

    /**
     * Static-analysis estimate of form-validation coverage for Laravel projects.
     *
     * Two estimates are computed against the same write-route denominator
     * (POST/PUT/PATCH/DELETE), both capped at 100 %:
     *
     *  - `occurrence`: legacy heuristic — (# `extends FormRequest` classes +
     *    inline `->validate(`). Undercounts because a shared FormRequest reused
     *    on several routes (e.g. `store` + `update`) counts as a single class.
     *  - `usage`: counts FormRequest *type-hint injection points* in method
     *    signatures (`function store(FooRequest $r)`) instead of class
     *    definitions, so a shared FormRequest is credited once per route it
     *    guards. Leans on the `*Request` naming convention to tell a FormRequest
     *    type-hint apart from the base `Request $request` (which never matches).
     *
     * The gating estimate is selected by `quality-safety.forms_coverage_mode`
     * ('usages' default, 'occurrences' for rollback). Both numbers ride along in
     * the finding payload (dual-compute) so a project can be verified before the
     * gate trusts the new value. Below the warn-threshold becomes a `high`
     * finding, below the critical-threshold a `critical`. Skipped for
     * non-Laravel projects (no `artisan` file at the project root).
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function formsCoverage(array $project): array
    {
        $root = $this->laravelRootOrNull($project);
        if ($root === null) {
            return ['findings' => [], 'skipped' => 'geen Laravel-root (artisan + routes/) op dit pad'];
        }

        $routesDir = $root . '/routes';
        $appDir = $root . '/app';

        $writeRoutes = $this->countMatches($routesDir, ['w' => '/Route::(?:post|put|patch|delete)\s*\(/i'])['w'];

        if ($writeRoutes === 0) {
            return ['findings' => []];
        }

        // Inline-validate covers four Laravel idioms: $req->validate([...]),
        // $req->validateWithBag('bag', [...]), Validator::make($data, [...]),
        // and $this->validate($req, [...]).
        //
        // The `usage` pattern requires a `*Request`-named type-hint immediately
        // followed by a `$var` parameter; the leading `[^)]*` lets it match the
        // FormRequest in any parameter position, the trailing `\b` excludes the
        // base `Request` (which lacks the prefix the convention requires).
        $appCounts = is_dir($appDir)
            ? $this->countMatches($appDir, [
                'fr' => '/extends\s+FormRequest\b/',
                'usage' => '/function\s+\w+\s*\([^)]*\b[A-Z][A-Za-z0-9_]*Request\b\s+\$/',
                'iv' => '/->validate(?:WithBag)?\s*\(|Validator::make\s*\(|\$this->validate\s*\(/',
            ])
            : ['fr' => 0, 'usage' => 0, 'iv' => 0];
        $formRequests = $appCounts['fr'];
        $formRequestUsages = $appCounts['usage'];
        $inlineValidates = $appCounts['iv'];

        // Cap each estimate at the write-route count: a route covered by both a
        // FormRequest and an inline ::validate must not push coverage above 100 %.
        $coveredOcc = min($formRequests + $inlineValidates, $writeRoutes);
        $coveredUsage = min($formRequestUsages + $inlineValidates, $writeRoutes);
        $coverageOcc = (int) round(($coveredOcc / $writeRoutes) * 100);
        $coverageUsage = (int) round(($coveredUsage / $writeRoutes) * 100);

        $mode = (string) config('quality-safety.forms_coverage_mode', 'usages');
        [$coverage, $covered] = $mode === 'occurrences'
            ? [$coverageOcc, $coveredOcc]
            : [$coverageUsage, $coveredUsage];

        $warn = (int) config('quality-safety.thresholds.forms_warning_pct', 60);
        $crit = (int) config('quality-safety.thresholds.forms_critical_pct', 30);

        if ($coverage >= $warn) {
            return ['findings' => []];
        }

        $severity = $coverage < $crit ? 'critical' : 'high';

        return [
            'findings' => [[
                'severity' => $severity,
                'title' => "Form validation coverage {$coverage}% ({$covered}/{$writeRoutes} write-routes)",
                'coverage_pct' => $coverage,
                'coverage_mode' => $mode,
                'coverage_occurrence_pct' => $coverageOcc,
                'coverage_usage_pct' => $coverageUsage,
                'write_routes' => $writeRoutes,
                'form_requests' => $formRequests,
                'form_request_usages' => $formRequestUsages,
                'inline_validates' => $inlineValidates,
                'message' => "{$coverage}% form-validation coverage [{$mode}] "
                    . "({$covered}/{$writeRoutes} write-routes; occurrence={$coverageOcc}%, usage={$coverageUsage}%; "
                    . "{$formRequests} FormRequest classes, {$formRequestUsages} type-hint usages, {$inlineValidates} inline ::validate)",
            ]],
        ];
    }

    /**
     * Boolean rate-limiting check: a Laravel project with write-routes that has
     * neither `throttle:` middleware references nor `RateLimiter::for(` in its
     * providers triggers a `high` finding. We don't try to score per-route —
     * the absence of *any* rate-limiting on write-routes is the actionable
     * signal; tuning the limits is a follow-up.
     *
     * Limitations: only detects `throttle:` middleware strings and
     * `RateLimiter::for(` provider definitions. Custom rate-limit middleware
     * classes (e.g. `LoginThrottler::class`) are not recognised.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function rateLimitCoverage(array $project): array
    {
        $root = $this->laravelRootOrNull($project);
        if ($root === null) {
            return ['findings' => [], 'skipped' => 'geen Laravel-root (artisan + routes/) op dit pad'];
        }

        $routesDir = $root . '/routes';

        $routeCounts = $this->countMatches($routesDir, [
            'write' => '/Route::(?:post|put|patch|delete)\s*\(/i',
            'throttle' => '/[\'"]throttle:/',
        ]);

        if ($routeCounts['write'] === 0) {
            return ['findings' => []];
        }

        $providersDir = $root . '/app/Providers';
        $providerCounts = is_dir($providersDir)
            ? $this->countMatches($providersDir, ['rl' => '/RateLimiter::for\s*\(/'])
            : ['rl' => 0];

        if ($routeCounts['throttle'] > 0 || $providerCounts['rl'] > 0) {
            return ['findings' => []];
        }

        return [
            'findings' => [[
                'severity' => 'high',
                'title' => 'No rate-limiting detected on any write-route',
                'write_routes' => $routeCounts['write'],
                'throttle_refs' => 0,
                'rate_limiter_for_defs' => 0,
                'message' => "No `throttle:` middleware or `RateLimiter::for(` defs found across {$routeCounts['write']} write-routes",
            ]],
        ];
    }

    /**
     * Scans the project for hardcoded credentials matching well-known
     * provider-specific patterns (Stripe, AWS, Anthropic, Groq, GitHub, …).
     *
     * Avoids generic password/secret regexes — those false-positive too often
     * on test fixtures and database column names. The current set is tuned
     * for high-confidence prefixed tokens; the cost of a `critical` finding
     * is high, so accuracy beats recall.
     *
     * The check is for **code leaks** (secrets in tracked source files), not
     * for the legitimate per-environment storage in `.env` files. `.env*` is
     * therefore not scanned — keeping secrets out of `.env` is enforced by
     * `.gitignore`, not by this heuristic. Same for tests/, vendor/,
     * node_modules/, storage/, and lockfiles.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function secretsScan(array $project): array
    {
        $path = $project['path'] ?? null;
        if (! $path || ! is_dir($path)) {
            return ['findings' => []];
        }

        $root = rtrim($path, '/\\');

        $patterns = [
            'stripe-live' => '/\bsk_live_[A-Za-z0-9]{24,}\b/',
            'stripe-test' => '/\bsk_test_[A-Za-z0-9]{24,}\b/',
            'aws-access-key' => '/\bAKIA[0-9A-Z]{16}\b/',
            'groq' => '/\bgsk_[A-Za-z0-9]{40,}\b/',
            'google-api' => '/\bAIza[0-9A-Za-z\-_]{35}\b/',
            'slack' => '/\bxox[baprs]-[0-9]{10,}-[0-9]{10,}-[A-Za-z0-9]{24,}\b/',
            'github-pat' => '/\bghp_[A-Za-z0-9]{36}\b/',
            'github-fine-grained-pat' => '/\bgithub_pat_[A-Za-z0-9_]{82}\b/',
            'mollie-live' => '/\bmollie_live_[A-Za-z0-9]{20,}\b/',
            'mollie-test' => '/\bmollie_test_[A-Za-z0-9]{20,}\b/',
            'resend' => '/\bre_[A-Za-z0-9_]{16,}\b/',
            'anthropic' => '/\bsk-ant-[A-Za-z0-9\-_]{50,}\b/',
            'openai' => '/\bsk-proj-[A-Za-z0-9\-_]{40,}\b/',
            'sentry-dsn' => '/\bhttps?:\/\/[a-f0-9]{32}@[\w.-]*sentry\.io\/\d+\b/',
            'digitalocean' => '/\bdop_v1_[a-f0-9]{64}\b/',
            'huggingface' => '/\bhf_[A-Za-z0-9]{34,}\b/',
        ];

        $hits = $this->scanFilesForSecrets($root, $patterns);

        $findings = [];
        foreach ($hits as $hit) {
            $findings[] = [
                'severity' => 'critical',
                'title' => "Hardcoded {$hit['kind']} credential",
                'kind' => $hit['kind'],
                'file' => $hit['file'],
                'masked' => $this->maskCredential($hit['match']),
                'message' => "{$hit['file']}: hardcoded {$hit['kind']} ({$this->maskCredential($hit['match'])})",
            ];
        }

        return ['findings' => $findings];
    }

    /**
     * @param  array<string,string>  $patterns
     * @return array<int,array{kind:string,file:string,match:string}>
     */
    private function scanFilesForSecrets(string $root, array $patterns): array
    {
        $hits = [];
        $skipDirs = array_merge($this->defaultSkipDirs(), [
            DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
        ]);

        foreach ($this->walkSourceFiles(
            $root,
            extensions: ['php', 'js', 'ts', 'yml', 'yaml', 'json', 'sh'],
            skipDirs: $skipDirs,
            skipFiles: ['composer.lock', 'package-lock.json'],
        ) as $filePath => $content) {
            $relative = ltrim(str_replace($root, '', $filePath), '/\\');
            foreach ($patterns as $kind => $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach (array_unique($matches[0]) as $match) {
                        $hits[] = [
                            'kind' => $kind,
                            'file' => str_replace('\\', '/', $relative),
                            'match' => $match,
                        ];
                    }
                }
            }
        }

        return $hits;
    }

    /**
     * Show the prefix + last 4 chars only, so log lines never re-leak the secret.
     */
    private function maskCredential(string $secret): string
    {
        $len = strlen($secret);
        if ($len <= 12) {
            return str_repeat('*', $len);
        }

        return substr($secret, 0, 8) . str_repeat('*', max(4, $len - 12)) . substr($secret, -4);
    }

    /**
     * Verifies that a Laravel project's session-cookie flags are secure:
     * - `secure` defaults to true (cookie only over HTTPS)
     * - `http_only` defaults to true (JS can't read cookie — XSS shield)
     * - `same_site` is 'lax' or 'strict' (CSRF shield)
     *
     * Laravel's stock config/session.php uses `env(..., null)` for `secure`,
     * which lets the framework auto-detect HTTPS but isn't strict-secure.
     * For production-only apps explicit `true` is the safer default.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function sessionCookieFlags(array $project): array
    {
        $root = $this->laravelRootOrNull($project);
        if ($root === null) {
            return ['findings' => [], 'skipped' => 'geen Laravel-root (artisan + routes/) op dit pad'];
        }

        $configPath = $root . '/config/session.php';
        if (! file_exists($configPath)) {
            return ['findings' => []];
        }

        $content = @file_get_contents($configPath);
        if ($content === false) {
            return ['findings' => []];
        }

        $issues = [];

        if (! preg_match('/[\'"]secure[\'"]\s*=>\s*(?:env\([^)]*,\s*true\s*\)|true)/', $content)) {
            $issues[] = 'secure cookie flag default is not true (env fallback null/false)';
        }

        if (! preg_match('/[\'"]http_only[\'"]\s*=>\s*(?:env\([^)]*,\s*true\s*\)|true)/', $content)) {
            $issues[] = 'http_only cookie flag default is not true';
        }

        if (! preg_match('/[\'"]same_site[\'"]\s*=>\s*(?:env\([^)]*,\s*[\'"](?:strict|lax)|[\'"](?:strict|lax))/', $content)) {
            $issues[] = 'same_site is not strict/lax (CSRF risk)';
        }

        if (empty($issues)) {
            return ['findings' => []];
        }

        $count = count($issues);

        return [
            'findings' => [[
                'severity' => 'high',
                'title' => "{$count} session-cookie flag(s) not securely set",
                'issues' => $issues,
                'message' => 'config/session.php: ' . implode('; ', $issues),
            ]],
        ];
    }

    /**
     * Verifies that config/app.php has `debug` defaulting to false. With
     * `env('APP_DEBUG', true)` (default in some Laravel skeletons), a missing
     * APP_DEBUG env-var leaks Whoops stack traces with database credentials,
     * env-vars, and request payloads on every uncaught exception. The
     * production .env should set APP_DEBUG=false but defaults must be safe.
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function debugModeFlag(array $project): array
    {
        $root = $this->laravelRootOrNull($project);
        if ($root === null) {
            return ['findings' => [], 'skipped' => 'geen Laravel-root (artisan + routes/) op dit pad'];
        }

        $configPath = $root . '/config/app.php';
        if (! file_exists($configPath)) {
            return ['findings' => []];
        }

        $content = @file_get_contents($configPath);
        if ($content === false) {
            return ['findings' => []];
        }

        // Safe forms: env('APP_DEBUG', false) | env('APP_DEBUG', null) | (bool)env(...) ? false : false
        // Unsafe form: env('APP_DEBUG', true) — default-on debug.
        if (preg_match('/[\'"]debug[\'"]\s*=>\s*(?:\(bool\)\s*)?env\([\'"]APP_DEBUG[\'"]\s*,\s*true\s*\)/', $content)) {
            return [
                'findings' => [[
                    'severity' => 'critical',
                    'title' => 'APP_DEBUG defaults to true in config/app.php',
                    'config' => 'config/app.php',
                    'message' => "config/app.php: 'debug' defaults to true — missing APP_DEBUG env-var leaks Whoops stack traces in production.",
                ]],
            ];
        }

        return ['findings' => []];
    }

    /**
     * Detects test-suite erosion: tests deleted in the last N days and tests
     * sitting in markTestSkipped/markTestIncomplete state. Both are visibility
     * signals — VP-17 ("never fix a failing test by editing the assertion")
     * extends to "never silently drop a test either".
     *
     * Findings:
     * - any test-file deletion in the last 30 days = high (must be reviewed)
     * - markTestSkipped count above threshold = high (silent disabling)
     * - markTestIncomplete = info (visible work-in-progress, not erosion)
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function testErosion(array $project): array
    {
        $path = $project['path'] ?? null;
        if (! $path || ! is_dir($path)) {
            return ['findings' => []];
        }

        $root = rtrim($path, '/\\');
        $testsDir = $root . '/tests';
        if (! is_dir($testsDir)) {
            // Géén stille nul. Deze check meet deletions in `tests/`; ontbreekt
            // die map, dan is er niets gemeten — en bij Rust is dat structureel:
            // unit-tests leven daar in `#[cfg(test)]`-modules binnen `src/`, dus
            // een verwijderde testmodule valt hoe dan ook buiten beeld.
            // Zie reference/testgereedschap-per-stack.md.
            return ['findings' => [], 'skipped' => 'geen tests/-map — test-erosion is hier niet gemeten (Rust: tests staan in src/)'];
        }

        $findings = [];
        $threshold = (int) config('quality-safety.thresholds.test_skip_max', 10);

        $deletedSince = $this->recentlyDeletedTests($root, days: 30);
        $ignored = (array) config('quality-safety.test_erosion.ignored_deletions', []);
        if (! empty($ignored)) {
            $deletedSince = array_values(array_diff($deletedSince, $ignored));
        }
        if (! empty($deletedSince)) {
            $files = implode(', ', array_slice($deletedSince, 0, 5));
            $more = count($deletedSince) > 5 ? sprintf(' (+%d more)', count($deletedSince) - 5) : '';
            $findings[] = [
                'severity' => 'high',
                'title' => count($deletedSince) . ' test file(s) deleted in last 30 days',
                'deleted_files' => $deletedSince,
                'message' => 'Recently deleted: ' . $files . $more,
            ];
        }

        $skipCounts = $this->classifyTestSkips($testsDir);

        if ($skipCounts['unconditional'] > $threshold) {
            $findings[] = [
                'severity' => 'high',
                'title' => "{$skipCounts['unconditional']} unconditional markTestSkipped calls (threshold {$threshold})",
                'unconditional_skips' => $skipCounts['unconditional'],
                'defensive_skips' => $skipCounts['defensive'],
                'incomplete_count' => $skipCounts['incomplete'],
                'message' => sprintf(
                    'tests/: %d unconditional + %d defensive markTestSkipped + %d markTestIncomplete — audit the unconditional ones',
                    $skipCounts['unconditional'],
                    $skipCounts['defensive'],
                    $skipCounts['incomplete'],
                ),
            ];
        }

        return ['findings' => $findings];
    }

    /**
     * Walks tests/, separates markTestSkipped into unconditional (real silent
     * disabling) and defensive (runtime-guarded by `if (extension_loaded)`,
     * `if (!Schema::hasTable)`, `} else { skip }`, `} catch { skip }` etc.).
     * The defensive branch only fires when the environment lacks a resource,
     * so it's cosmetic noise rather than test-erosion.
     *
     * @return array{unconditional:int, defensive:int, incomplete:int}
     */
    private function classifyTestSkips(string $testsDir): array
    {
        $unconditional = 0;
        $defensive = 0;
        $incomplete = 0;

        foreach ($this->walkSourceFiles($testsDir, ['php'], $this->defaultSkipDirs(), []) as $content) {
            $lines = preg_split('/\R/', $content) ?: [];

            foreach ($lines as $i => $line) {
                if (str_contains($line, 'markTestIncomplete(')) {
                    $incomplete++;
                    continue;
                }
                if (! str_contains($line, 'markTestSkipped(')) {
                    continue;
                }

                $start = max(0, $i - 5);
                $context = implode("\n", array_slice($lines, $start, $i - $start + 1));

                $isDefensive = preg_match('/}\s*else(if\s*\([^)]*\))?\s*\{/', $context)
                    || preg_match('/\bif\s*\(/', $context)
                    || preg_match('/}\s*catch\s*\(/', $context);

                if ($isDefensive) {
                    $defensive++;
                } else {
                    $unconditional++;
                }
            }
        }

        return [
            'unconditional' => $unconditional,
            'defensive' => $defensive,
            'incomplete' => $incomplete,
        ];
    }

    /**
     * Lists test-files deleted in `tests/` over the last `$days` days using
     * the project's git history. Returns paths relative to the project root,
     * empty array when not a git repo or git is unavailable.
     *
     * @return array<int,string>
     */
    private function recentlyDeletedTests(string $root, int $days): array
    {
        if (! is_dir($root . '/.git')) {
            return [];
        }

        $bin = config('quality-safety.bin.git', 'git');
        $since = sprintf('--since=%d.days.ago', $days);

        $result = Process::path($root)->timeout(30)->run([
            $bin, 'log', $since, '--diff-filter=D', '--name-only', '--pretty=format:',
        ]);

        if (! $result->successful()) {
            return [];
        }

        $files = array_filter(
            preg_split('/\R/', $result->output()) ?: [],
            fn ($line) => $line !== '' && str_starts_with($line, 'tests/') && str_ends_with($line, '.php'),
        );

        // Filter out files that have been re-added since the delete (deleted-
        // then-restored isn't erosion). Compare against the working tree, not
        // the git index, so a freshly-staged restore counts immediately.
        $files = array_filter(
            $files,
            fn ($path) => ! file_exists($root . '/' . $path),
        );

        return array_values(array_unique($files));
    }

    /**
     * Returns the trimmed project root if the path looks like a Laravel app
     * (has both `artisan` and a `routes/` directory), otherwise null. Used to
     * gate the per-project Laravel checks (forms, ratelimit) with a single
     * preamble.
     *
     * @param  array<string,mixed>  $project
     */
    private function laravelRootOrNull(array $project): ?string
    {
        $path = $project['path'] ?? null;
        if (! $path || ! is_dir($path)) {
            return null;
        }

        $root = rtrim($path, '/\\');
        if (! file_exists($root . '/artisan') || ! is_dir($root . '/routes')) {
            return null;
        }

        return $root;
    }

    /**
     * Count regex matches across all `.php` files in a directory tree.
     *
     * Multiple patterns are evaluated in a single walk to halve I/O when the
     * caller needs several counts on the same tree. Skips vendor / node_modules
     * / storage / bootstrap-cache to keep the walk bounded on real Laravel apps.
     * Returns 0-counts for unreadable trees rather than throwing — coverage
     * heuristic should never break a scan.
     *
     * @param  array<string,string>  $patterns  keyed pattern map
     * @return array<string,int>     same keys, with match counts
     */
    private function countMatches(string $dir, array $patterns): array
    {
        $counts = array_fill_keys(array_keys($patterns), 0);

        foreach ($this->walkSourceFiles($dir, ['php'], $this->defaultSkipDirs(), []) as $content) {
            foreach ($patterns as $key => $pattern) {
                $counts[$key] += preg_match_all($pattern, $content);
            }
        }

        return $counts;
    }

    /**
     * @return array<int,string>
     */
    private function defaultSkipDirs(): array
    {
        return [
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR,
        ];
    }

    /**
     * Recursive PHP-source-file iterator with skip-list, extension whitelist
     * and tolerant error handling. Yields `path => content` for every file
     * that survives the filters.
     *
     * @param  array<int,string>  $extensions  whitelist (e.g. ['php']) or empty for any
     * @param  array<int,string>  $skipDirs    DIRECTORY_SEPARATOR-bracketed prefixes to drop
     * @param  array<int,string>  $skipFiles   exact basenames to drop
     * @return \Generator<string,string>
     */
    private function walkSourceFiles(string $root, array $extensions, array $skipDirs, array $skipFiles): \Generator
    {
        if (! is_dir($root)) {
            return;
        }

        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
        } catch (\UnexpectedValueException) {
            return;
        }

        foreach ($iter as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $filePath = $file->getPathname();
            foreach ($skipDirs as $needle) {
                if (str_contains($filePath, $needle)) {
                    continue 2;
                }
            }
            if ($skipFiles && in_array($file->getFilename(), $skipFiles, true)) {
                continue;
            }
            $ext = $file->getExtension();
            if ($extensions && ($ext === '' || ! in_array($ext, $extensions, true))) {
                continue;
            }
            $content = @file_get_contents($filePath);
            if ($content === false) {
                continue;
            }
            yield $filePath => $content;
        }
    }

    private function gradeRank(string $grade): int
    {
        return match (strtoupper($grade)) {
            'A+' => 8,
            'A' => 7,
            'A-' => 6,
            'B+' => 5,
            'B' => 4,
            'B-' => 3,
            'C+' => 2,
            'C' => 1,
            'C-' => 0,
            default => -1,
        };
    }

    private function normalizeSeverity(string $raw): string
    {
        // Keep legacy 'informational' bucket as-is for backward compat with
        // persisted scan JSON + downstream consumers. Known severities are
        // resolved via Severity enum; everything else falls through to the
        // legacy 'informational' label.
        return match (strtolower($raw)) {
            'crit', 'critical' => Severity::Critical->value,
            'high' => Severity::High->value,
            'med', 'medium', 'moderate' => Severity::Medium->value,
            'low' => Severity::Low->value,
            default => 'informational',
        };
    }

    /**
     * @param  array<int,array<string,mixed>>  $findings
     * @param  array<int,array<string,mixed>>  $errors
     * @return array<string,int>
     */
    private function totals(array $findings, array $errors): array
    {
        $totals = [
            Severity::Critical->value => 0,
            Severity::High->value => 0,
            Severity::Medium->value => 0,
            Severity::Low->value => 0,
            'informational' => 0,
            'errors' => count($errors),
        ];

        foreach ($findings as $f) {
            $sev = $f['severity'] ?? 'informational';
            if (! array_key_exists($sev, $totals)) {
                $sev = 'informational';
            }
            $totals[$sev]++;
        }

        return $totals;
    }
}
