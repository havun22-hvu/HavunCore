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
     * Authenticate request via Bearer token or env-configured KB token.
     * Returns true if authenticated, false otherwise.
     */
    private function authenticate(Request $request): bool
    {
        $token = $request->bearerToken() ?? $request->header('X-KB-Token');

        if (!$token) {
            return false;
        }

        $validToken = config('services.doc_intelligence.api_token');

        if (!$validToken) {
            return false;
        }

        return hash_equals($validToken, $token);
    }

    /**
     * Search documents across all projects
     *
     * GET /api/docs/search?q=query&project=optional&limit=5
     * Authorization: Bearer <token>
     */
    public function search(Request $request, DocIndexer $indexer): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        $query = $request->input('q', $request->input('query', ''));
        $project = $request->input('project');
        $fileType = $request->input('type');
        $limit = (int) $request->input('limit', 5);

        if (empty($query)) {
            return response()->json([
                'success' => false,
                'error' => 'Query parameter (q) is required',
            ], 400);
        }

        $results = $indexer->search($query, $project, $limit, $fileType);

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
        if (!$this->authenticate($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
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
    public function stats(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
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
     * Health check with detailed system info
     *
     * GET /api/docs/health
     */
    public function health(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $dbPath = database_path('doc_intelligence.sqlite');
        $dbExists = file_exists($dbPath);
        $dbSizeMb = $dbExists ? round(filesize($dbPath) / 1024 / 1024, 1) : 0;

        $totalFiles = DocEmbedding::count();
        $neuralCount = DocEmbedding::where('embedding_model', '!=', 'tfidf-fallback')->count();
        $tfidfCount = DocEmbedding::where('embedding_model', 'tfidf-fallback')->count();
        $lastIndexed = DocEmbedding::max('updated_at');

        $byProject = DocEmbedding::selectRaw('project, COUNT(*) as total')
            ->groupBy('project')
            ->pluck('total', 'project');

        $byType = DocEmbedding::selectRaw('file_type, COUNT(*) as total')
            ->groupBy('file_type')
            ->pluck('total', 'file_type');

        $openIssues = DocIssue::where('status', 'open')->count();

        // Check Ollama connectivity
        $ollamaOk = false;
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(3)
                ->get(env('OLLAMA_URL', 'http://127.0.0.1:11434') . '/api/tags');
            $ollamaOk = $response->successful();
        } catch (\Exception $e) {
            // Ollama unreachable
        }

        return response()->json([
            'success' => true,
            'status' => $dbExists && $totalFiles > 0 ? 'healthy' : 'degraded',
            'indexed_files' => $totalFiles,
            'neural_embeddings' => $neuralCount,
            'tfidf_embeddings' => $tfidfCount,
            'last_indexed_at' => $lastIndexed,
            'open_issues' => $openIssues,
            'db_size_mb' => $dbSizeMb,
            'ollama_available' => $ollamaOk,
            'by_project' => $byProject,
            'by_type' => $byType,
        ]);
    }

    /**
     * Read a specific document
     *
     * GET /api/docs/read?project=x&path=y
     */
    public function read(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
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
