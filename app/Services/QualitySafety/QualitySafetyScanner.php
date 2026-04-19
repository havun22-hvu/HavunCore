<?php

namespace App\Services\QualitySafety;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class QualitySafetyScanner
{
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

        foreach ($projects as $slug => $project) {
            foreach ($checks as $check) {
                $result = $this->runCheck($check, $project);

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
            }
        }

        return [
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => Carbon::now()->toIso8601String(),
            'projects' => array_keys($projects),
            'checks' => $checks,
            'findings' => $findings,
            'errors' => $errors,
            'totals' => $this->totals($findings, $errors),
        ];
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function runCheck(string $check, array $project): array
    {
        return match ($check) {
            'composer' => $this->composerAudit($project),
            'npm' => $this->npmAudit($project),
            'ssl' => $this->sslExpiry($project),
            'observatory' => $this->observatory($project),
            'server' => $this->serverHealth($project),
            'forms' => $this->formsCoverage($project),
            'ratelimit' => $this->rateLimitCoverage($project),
            'secrets' => $this->secretsScan($project),
            default => ['findings' => [], 'error' => "Unknown check: {$check}"],
        };
    }

    /**
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function composerAudit(array $project): array
    {
        $path = $project['path'] ?? null;
        if (! $path || ! is_dir($path)) {
            return ['findings' => [], 'error' => "Project path not found: {$path}"];
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
        if (! $path || ! is_dir($path)) {
            return ['findings' => [], 'error' => "Project path not found: {$path}"];
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
            return ['findings' => []];
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
                ->post($endpoint, ['host' => $host]);
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
        $bin = config('quality-safety.bin.ssh', 'ssh');
        $sshOpts = (array) config('quality-safety.server.ssh_options', []);

        $remoteCmd = 'df -P -B1 && echo ---SYSTEMD--- && systemctl --failed --no-legend --plain --type=service 2>/dev/null || true';

        $cmd = array_merge([$bin], $sshOpts, ["{$user}@{$host}", $remoteCmd]);

        $result = Process::timeout(30)->run($cmd);

        if (! $result->successful()) {
            $stderr = trim($result->errorOutput()) ?: trim($result->output());

            return [
                'findings' => [],
                'error' => "SSH to {$host} failed (exit {$result->exitCode()}): " . ($stderr ?: 'no output'),
            ];
        }

        [$dfOutput, $systemdOutput] = $this->splitServerOutput($result->output());

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
     * Heuristic: ratio between (FormRequest classes + inline `->validate(`) and
     * write-routes (POST/PUT/PATCH/DELETE). Below the warn-threshold becomes a
     * `high` finding, below the critical-threshold a `critical`. Skipped for
     * non-Laravel projects (no `artisan` file at the project root).
     *
     * @param  array<string,mixed>  $project
     * @return array{findings:array<int,array<string,mixed>>, error?:string}
     */
    private function formsCoverage(array $project): array
    {
        $root = $this->laravelRootOrNull($project);
        if ($root === null) {
            return ['findings' => []];
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
        $appCounts = is_dir($appDir)
            ? $this->countMatches($appDir, [
                'fr' => '/extends\s+FormRequest\b/',
                'iv' => '/->validate(?:WithBag)?\s*\(|Validator::make\s*\(|\$this->validate\s*\(/',
            ])
            : ['fr' => 0, 'iv' => 0];
        $formRequests = $appCounts['fr'];
        $inlineValidates = $appCounts['iv'];
        $covered = $formRequests + $inlineValidates;

        // Cap at write-route count: a single route covered by both a FormRequest
        // and an inline ::validate must not push coverage above 100 %.
        $coverage = (int) round((min($covered, $writeRoutes) / $writeRoutes) * 100);

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
                'write_routes' => $writeRoutes,
                'form_requests' => $formRequests,
                'inline_validates' => $inlineValidates,
                'message' => "{$coverage}% form-validation coverage ({$formRequests} FormRequest + {$inlineValidates} inline ::validate vs {$writeRoutes} write-routes)",
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
            return ['findings' => []];
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
            'mollie-live' => '/\bmollie_live_[A-Za-z0-9]{20,}\b/',
            'mollie-test' => '/\bmollie_test_[A-Za-z0-9]{20,}\b/',
            'resend' => '/\bre_[A-Za-z0-9_]{16,}\b/',
            'anthropic' => '/\bsk-ant-[A-Za-z0-9\-_]{50,}\b/',
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
        return match (strtolower($raw)) {
            'crit', 'critical' => 'critical',
            'high' => 'high',
            'med', 'medium', 'moderate' => 'medium',
            'low' => 'low',
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
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
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
