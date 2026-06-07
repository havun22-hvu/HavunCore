<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health alerts API for the HavunCore webapp notification panel.
 *
 * Read-only status data (same sensitivity as /health/server), written by the
 * server-side health:alert command.
 */
class HealthAlertController extends Controller
{
    /**
     * List alerts (open by default), newest first.
     *
     * GET /api/health-alerts
     */
    public function index(Request $request): JsonResponse
    {
        $query = HealthAlert::query()->orderByDesc('last_seen_at');

        if ($request->input('status', 'open') !== 'all') {
            $query->where('status', $request->input('status', 'open'));
        }
        if ($request->filled('scope')) {
            $query->where('scope', $request->input('scope'));
        }
        if ($request->filled('project')) {
            $query->forProject($request->input('project'));
        }

        $alerts = $query->limit(100)->get();

        return response()->json([
            'success' => true,
            'open_count' => HealthAlert::open()->count(),
            'data' => $alerts,
        ]);
    }

    /**
     * Dismiss (resolve) an alert manually.
     *
     * POST /api/health-alerts/{id}/dismiss
     */
    public function dismiss(int $id): JsonResponse
    {
        $alert = HealthAlert::findOrFail($id);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return response()->json(['success' => true]);
    }
}
