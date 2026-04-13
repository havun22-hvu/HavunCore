<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Observability Controller
 *
 * API endpoints for monitoring dashboard data.
 * Protected by bearer token (OBSERVABILITY_ADMIN_TOKEN).
 */
class ObservabilityController extends Controller
{
    protected ObservabilityService $observability;

    public function __construct(ObservabilityService $observability)
    {
        $this->observability = $observability;
    }

    /**
     * Dashboard summary
     *
     * GET /api/observability/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'data' => $this->observability->getDashboard($request->input('project')),
        ]);
    }

    /**
     * Recent request metrics
     *
     * GET /api/observability/requests?path=&status_code=&tenant=&method=&errors_only=1&per_page=50
     */
    public function requests(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        $data = $this->observability->getRequests($request->only([
            'project', 'path', 'status_code', 'tenant', 'method', 'errors_only', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Recent errors
     *
     * GET /api/observability/errors?severity=&exception_class=&per_page=50
     */
    public function errors(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        $data = $this->observability->getErrors($request->only([
            'project', 'severity', 'exception_class', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Slow queries
     *
     * GET /api/observability/slow-queries?min_time=&per_page=50
     */
    public function slowQueries(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        $data = $this->observability->getSlowQueries($request->only([
            'project', 'min_time', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * System health
     *
     * GET /api/observability/system
     */
    public function system(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'data' => $this->observability->getSystemHealth(),
        ]);
    }

    /**
     * Aggregated metrics
     *
     * GET /api/observability/metrics?period=hourly&path=&limit=48
     */
    public function metrics(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        $data = $this->observability->getMetrics(
            period: $request->input('period', 'hourly'),
            path: $request->input('path'),
            limit: (int) $request->input('limit', 48),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Performance baseline
     *
     * GET /api/observability/baseline?date=2026-04-12
     */
    public function baseline(Request $request): JsonResponse
    {
        if (! $this->checkToken($request)) {
            return $this->unauthorized();
        }

        $date = $request->input('date', now()->subDay()->toDateString());
        $data = cache()->get("performance_baseline:{$date}");

        return response()->json([
            'success' => true,
            'date' => $date,
            'data' => $data,
        ]);
    }

    /**
     * Check bearer token authorization.
     */
    protected function checkToken(Request $request): bool
    {
        $token = config('observability.admin_token');

        // If no token configured, deny access
        if (empty($token)) {
            return false;
        }

        return $request->bearerToken() === $token;
    }

    /**
     * Chaos experiment results — latest per experiment + history.
     */
    public function chaos(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            return $this->unauthorized();
        }

        $latest = \App\Models\ChaosResult::query()
            ->selectRaw('experiment, status, duration_ms, checks, MAX(created_at) as last_run')
            ->groupBy('experiment')
            ->orderBy('experiment')
            ->get()
            ->map(fn ($r) => [
                'experiment' => $r->experiment,
                'status' => $r->status,
                'duration_ms' => $r->duration_ms,
                'checks' => $r->checks,
                'last_run' => $r->last_run,
            ]);

        $history = \App\Models\ChaosResult::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'experiment' => $r->experiment,
                'status' => $r->status,
                'duration_ms' => $r->duration_ms,
                'created_at' => $r->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'latest' => $latest,
                'history' => $history,
                'total_experiments' => 13,
            ],
        ]);
    }

    /**
     * Run a chaos experiment via API.
     */
    public function chaosRun(Request $request): JsonResponse
    {
        if (! $this->isAuthorized($request)) {
            return $this->unauthorized();
        }

        $experiment = $request->input('experiment');
        if (! $experiment) {
            return response()->json(['success' => false, 'error' => 'Missing experiment parameter'], 422);
        }

        try {
            $result = \Illuminate\Support\Facades\Artisan::call('chaos:run', ['experiment' => $experiment]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'success' => true,
                'data' => [
                    'experiment' => $experiment,
                    'exit_code' => $result,
                    'output' => $output,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Unauthorized',
        ], 401);
    }
}
