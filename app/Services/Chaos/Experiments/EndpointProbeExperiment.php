<?php

namespace App\Services\Chaos\Experiments;

use App\Services\Chaos\ChaosExperiment;
use Illuminate\Support\Facades\Http;

/**
 * Probes all project health endpoints and measures response times.
 */
class EndpointProbeExperiment extends ChaosExperiment
{
    public function name(): string
    {
        return 'Endpoint Probe';
    }

    public function hypothesis(): string
    {
        return 'All project endpoints respond with 200 within 5 seconds';
    }

    public function run(): array
    {
        $endpoints = config('chaos.endpoints', []);
        $checks = [];
        $overallStatus = 'pass';

        foreach ($endpoints as $project => $url) {
            $probe = $this->measure(function () use ($url) {
                $response = Http::timeout(5)->withoutVerifying()->get($url);

                return $response->status();
            });

            $status = 'pass';
            $message = '';

            if ($probe['error']) {
                $status = 'fail';
                $message = "ERROR: {$probe['error']}";
                $overallStatus = 'fail';
            } elseif ($probe['result'] !== 200) {
                $status = 'warn';
                $message = "HTTP {$probe['result']} ({$probe['time_ms']}ms)";
                if ($overallStatus !== 'fail') {
                    $overallStatus = 'warn';
                }
            } elseif ($probe['time_ms'] > 3000) {
                $status = 'warn';
                $message = "SLOW: {$probe['time_ms']}ms";
                if ($overallStatus !== 'fail') {
                    $overallStatus = 'warn';
                }
            } else {
                $message = "OK ({$probe['time_ms']}ms)";
            }

            $checks[$project] = [
                'status' => $status,
                'message' => $message,
                'url' => $url,
            ];
        }

        return [
            'status' => $overallStatus,
            'checks' => $checks,
        ];
    }
}
