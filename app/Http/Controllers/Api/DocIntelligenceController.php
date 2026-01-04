<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocIntelligenceController extends Controller
{
    /**
     * Search documents across all projects
     *
     * GET /api/docs/search?q=query&project=optional&limit=5
     */
    public function search(Request $request, DocIndexer $indexer): JsonResponse
    {
        $query = $request->input('q', $request->input('query', ''));
        $project = $request->input('project');
        $limit = (int) $request->input('limit', 5);

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'error' => 'Query parameter (q) is required',
            ], 400);
        }

        $results = $indexer->search($query, $project, $limit);

        return response()->json([
            'success' => true,
            'query' => $query,
            'project' => $project,
            'results' => $results,
        ]);
    }

    /**
     * Get open issues for a project
     *
     * GET /api/docs/issues?project=optional&type=optional
     */
    public function issues(Request $request): JsonResponse
    {
        $project = $request->input('project');
        $type = $request->input('type');

        $query = DocIssue::where('status', 'open');

        if ($project) {
            $query->where('project', strtolower($project));
        }

        if ($type) {
            $query->where('type', $type);
        }

        $issues = $query->orderByRaw("CASE
            WHEN severity = 'high' THEN 1
            WHEN severity = 'medium' THEN 2
            ELSE 3
        END")
            ->limit(50)
            ->get()
            ->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'type' => $issue->type,
                    'severity' => $issue->severity,
                    'description' => $issue->description,
                    'project' => $issue->project,
                    'files' => $issue->affected_files,
                    'suggestion' => $issue->suggested_action,
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $issues->count(),
            'issues' => $issues,
        ]);
    }

    /**
     * Get statistics about indexed documents
     *
     * GET /api/docs/stats
     */
    public function stats(): JsonResponse
    {
        $stats = DocEmbedding::selectRaw('project, COUNT(*) as count, SUM(token_count) as total_tokens')
            ->groupBy('project')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->project => [
                    'files' => $row->count,
                    'tokens' => $row->total_tokens,
                ]];
            });

        $issueStats = DocIssue::where('status', 'open')
            ->selectRaw('project, COUNT(*) as count')
            ->groupBy('project')
            ->get()
            ->mapWithKeys(fn($row) => [$row->project => $row->count]);

        return response()->json([
            'success' => true,
            'total_files' => DocEmbedding::count(),
            'total_issues' => DocIssue::where('status', 'open')->count(),
            'by_project' => $stats,
            'issues_by_project' => $issueStats,
        ]);
    }

    /**
     * Read a specific document
     *
     * GET /api/docs/read?project=x&path=y
     */
    public function read(Request $request): JsonResponse
    {
        $project = $request->input('project');
        $path = $request->input('path');

        if (!$project || !$path) {
            return response()->json([
                'success' => false,
                'error' => 'Both project and path are required',
            ], 400);
        }

        $doc = DocEmbedding::where('project', strtolower($project))
            ->where('file_path', $path)
            ->first();

        if (!$doc) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'project' => $doc->project,
            'path' => $doc->file_path,
            'content' => $doc->content,
            'last_indexed' => $doc->updated_at->toIso8601String(),
        ]);
    }
}
