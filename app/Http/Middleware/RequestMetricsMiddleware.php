<?php

namespace App\Http\Middleware;

use App\Models\RequestMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestMetricsMiddleware
{
    protected bool $enabled;

    protected float $samplingRate;

    protected array $excludedPaths;

    public function __construct()
    {
        $this->enabled = config('observability.enabled', true);
        $this->samplingRate = (float) config('observability.sampling_rate', 1.0);
        $this->excludedPaths = config('observability.excluded_paths', []);
    }

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
        if (! $this->enabled) {
            return false;
        }

        if ($this->samplingRate < 1.0 && mt_rand(1, 10000) / 10000 > $this->samplingRate) {
            return false;
        }

        $path = $request->path();
        foreach ($this->excludedPaths as $excluded) {
            if (Str::is($excluded, $path)) {
                return false;
            }
        }

        return true;
    }
}
