<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutofixProposal;
use App\Services\AutoFixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central AutoFix API
 *
 * Projects send errors here for analysis. Fix proposals are returned.
 * Projects apply fixes locally and report results back.
 */
class AutoFixController extends Controller
{
    protected AutoFixService $autofix;

    public function __construct(AutoFixService $autofix)
    {
        $this->autofix = $autofix;
    }

    /**
     * Analyze an error and return a fix proposal.
     *
     * POST /api/autofix/analyze
     */
    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => 'required|string|max:50',
            'exception_class' => 'required|string|max:255',
            'message' => 'required|string',
            'file' => 'nullable|string|max:500',
            'line' => 'nullable|integer',
            'trace' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $proposal = $this->autofix->analyze($validated);

        if (! $proposal) {
            return response()->json([
                'success' => false,
                'reason' => 'rate_limited_or_analysis_failed',
            ], 429);
        }

        return response()->json([
            'success' => true,
            'proposal' => [
                'id' => $proposal->id,
                'fix_proposal' => $proposal->fix_proposal,
                'risk_level' => $proposal->risk_level,
                'status' => $proposal->status,
            ],
        ]);
    }

    /**
     * Report the result of applying a fix.
     *
     * POST /api/autofix/report
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proposal_id' => 'required|integer',
            'status' => 'required|string|in:applied,failed,rejected',
            'result_message' => 'nullable|string',
        ]);

        $this->autofix->reportResult(
            $validated['proposal_id'],
            $validated['status'],
            $validated['result_message'] ?? null
        );

        return response()->json(['success' => true]);
    }

    /**
     * Record a local fallback fix (when HavunCore was unreachable).
     *
     * POST /api/autofix/fallback
     */
    public function fallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => 'required|string|max:50',
            'exception_class' => 'required|string|max:255',
            'message' => 'nullable|string',
            'file' => 'nullable|string',
            'line' => 'nullable|integer',
            'fix_proposal' => 'nullable|string',
            'status' => 'required|string',
            'risk_level' => 'nullable|string',
            'result_message' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $proposal = $this->autofix->recordFallback($validated);

        return response()->json([
            'success' => true,
            'proposal_id' => $proposal->id,
        ]);
    }

    /**
     * List proposals (for dashboard).
     *
     * GET /api/autofix/proposals
     */
    public function proposals(Request $request): JsonResponse
    {
        $query = AutofixProposal::query()->orderByDesc('created_at');

        if ($request->filled('project')) {
            $query->forProject($request->input('project'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 25)),
        ]);
    }
}
