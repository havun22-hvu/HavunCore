<?php

namespace App\Services\QualitySafety;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Carbon;
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
