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
            'data' => $this->observability->getDashboard(),
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
            'path', 'status_code', 'tenant', 'method', 'errors_only', 'per_page',
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
            'severity', 'exception_class', 'per_page',
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
            'min_time', 'per_page',
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
