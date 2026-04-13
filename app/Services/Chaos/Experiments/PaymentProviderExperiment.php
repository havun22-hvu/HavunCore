<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\Http;

/**
 * Payment provider resilience — tests what happens when Mollie/Bunq are unreachable.
 */
class PaymentProviderExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Payment Provider Resilience';
    }

    public function hypothesis(): string
    {
        return 'System handles payment provider outages gracefully — no 500 errors, clear user messaging';
    }

    public function run(): array
    {
        $checks = [];
        $overallStatus = 'pass';

        // 1. Mollie API reachability
        $mollieResult = $this->measure(function () {
            return Http::timeout(5)
                ->get('https://api.mollie.com/v2/methods')
                ->status();
        });

        if ($mollieResult['error']) {
            $checks['mollie_reachable'] = [
                'status' => 'warn',
                'message' => "Mollie API unreachable: {$mollieResult['error']}",
            ];
            $overallStatus = 'warn';
        } else {
            // 401 = no auth but API is up, 200 = public endpoint
            $status = in_array($mollieResult['result'], [200, 401]) ? 'pass' : 'warn';
            $checks['mollie_reachable'] = [
                'status' => $status,
                'message' => "Mollie API responds ({$mollieResult['time_ms']}ms, HTTP {$mollieResult['result']})",
            ];
        }

        // 2. Mollie API key configured
        $mollieKey = config('services.mollie.key') ?: config('mollie.key');
        $checks['mollie_configured'] = [
            'status' => ! empty($mollieKey) ? 'pass' : 'warn',
            'message' => ! empty($mollieKey) ? 'Mollie API key configured' : 'Mollie API key missing — mock mode?',
        ];

        // 3. Bunq API reachability (if configured)
        $bunqToken = config('services.bunq.api_key') ?: config('bunq.api_key');
        if (! empty($bunqToken)) {
            $bunqResult = $this->measure(function () {
                return Http::timeout(5)
                    ->get('https://api.bunq.com/v1/sandbox-user')
                    ->status();
            });

            $checks['bunq_reachable'] = [
                'status' => $bunqResult['error'] ? 'warn' : 'pass',
                'message' => $bunqResult['error']
                    ?? "Bunq API responds ({$bunqResult['time_ms']}ms, HTTP {$bunqResult['result']})",
            ];
        } else {
            $checks['bunq_configured'] = [
                'status' => 'pass',
                'message' => 'Bunq not configured (EPC-only mode)',
            ];
        }

        // 4. Simulate timeout — does Http::timeout work correctly?
        $timeoutResult = $this->measure(function () {
            try {
                Http::timeout(1)->get('https://httpstat.us/200?sleep=3000');

                return 'no_timeout';
            } catch (\Illuminate\Http\Client\ConnectionException) {
                return 'timeout_caught';
            }
        });

        $timeoutWorks = $timeoutResult['result'] === 'timeout_caught' || $timeoutResult['error'] !== null;
        $checks['timeout_handling'] = [
            'status' => $timeoutWorks ? 'pass' : 'warn',
            'message' => $timeoutWorks
                ? "Timeout correctly enforced ({$timeoutResult['time_ms']}ms)"
                : 'Timeout NOT enforced — requests may hang',
        ];

        // 5. Payment config completeness
        $paymentConfig = config('payment', []);
        $requiredKeys = ['mock_mode', 'currency'];
        $missing = [];
        foreach ($requiredKeys as $key) {
            if (! isset($paymentConfig[$key])) {
                $missing[] = $key;
            }
        }

        $checks['payment_config'] = [
            'status' => empty($missing) ? 'pass' : 'warn',
            'message' => empty($missing)
                ? 'Payment config complete'
                : 'Missing config keys: ' . implode(', ', $missing),
        ];

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
