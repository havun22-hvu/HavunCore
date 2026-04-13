<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;

/**
 * DNS resolution — tests that all critical external domains resolve correctly.
 */
class DnsResolutionExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'DNS Resolution';
    }

    public function hypothesis(): string
    {
        return 'All critical external domains resolve correctly and within acceptable time';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // Critical domains that our services depend on
        $domains = [
            'api.mollie.com' => 'Payment processing',
            'api.anthropic.com' => 'Claude AI API',
            'github.com' => 'Code repository',
            'unpkg.com' => 'CDN (AlpineJS)',
            'cdn.jsdelivr.net' => 'CDN (QRCode)',
            'cdnjs.cloudflare.com' => 'CDN (fabric.js, qrcodejs)',
        ];

        // Add project domains from chaos config
        foreach (config('chaos.endpoints', []) as $project => $url) {
            $parsed = parse_url($url);
            if (isset($parsed['host'])) {
                $domains[$parsed['host']] = "Project: {$project}";
            }
        }

        foreach ($domains as $domain => $purpose) {
            $result = $this->measure(function () use ($domain) {
                $records = @dns_get_record($domain, DNS_A);

                return ! empty($records) ? $records[0]['ip'] ?? 'resolved' : null;
            });

            if ($result['error'] || ! $result['result']) {
                $checks[$domain] = [
                    'status' => 'fail',
                    'message' => "FAILED ({$purpose}) — " . ($result['error'] ?? 'no DNS records'),
                ];
                $overallStatus = 'fail';
            } elseif ($result['time_ms'] > 2000) {
                $checks[$domain] = [
                    'status' => 'warn',
                    'message' => "{$purpose} — slow resolution ({$result['time_ms']}ms)",
                ];
                if ($overallStatus === 'pass') {
                    $overallStatus = 'warn';
                }
            } else {
                $checks[$domain] = [
                    'status' => 'pass',
                    'message' => "{$purpose} → {$result['result']} ({$result['time_ms']}ms)",
                ];
            }
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
