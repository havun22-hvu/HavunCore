<?php

namespace App\Http\Middleware;

use App\Models\RequestMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        try {
            if (! $this->shouldLog($request)) {
                return $response;
            }

            $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

            RequestMetric::create([
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => $responseTimeMs,
                'ip_address' => $request->ip(),
                'tenant' => $request->input('tenant') ?? $request->header('X-Tenant'),
                'user_agent' => Str::limit($request->userAgent(), 497),
                'memory_usage_kb' => (int) round(memory_get_peak_usage(true) / 1024),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never let metrics logging break the request
        }

        return $response;
    }

    /**
     * Check if this request should be logged.
     */
    protected function shouldLog(Request $request): bool
    {
        if (! config('observability.enabled', true)) {
            return false;
        }

        // Sampling rate check
        $rate = config('observability.sampling_rate', 1.0);
        if ($rate < 1.0 && mt_rand(1, 10000) / 10000 > $rate) {
            return false;
        }

        // Excluded paths check
        $path = $request->path();
        foreach (config('observability.excluded_paths', []) as $excluded) {
            if (Str::is($excluded, $path)) {
                return false;
            }
        }

        return true;
    }
}
