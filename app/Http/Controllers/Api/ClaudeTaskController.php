<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClaudeTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClaudeTaskController extends Controller
{
    /**
     * Display a listing of tasks
     *
     * Query params:
     * - project: Filter by project (havunadmin, herdenkingsportaal) - havuncore BLOCKED
     * - status: Filter by status (pending, running, completed, failed)
     * - limit: Limit results (default 50)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClaudeTask::query();

        // Filter by project
        if ($request->has('project')) {
            $query->forProject($request->input('project'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Order by priority and created_at
        $query->byPriority()->latest();

        // Limit results
        $limit = min($request->input('limit', 50), 200);
        $tasks = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'count' => $tasks->count(),
            'tasks' => $tasks,
        ]);
    }

    /**
     * Store a newly created task
     *
     * Required:
     * - project: string (havunadmin, herdenkingsportaal) - havuncore BLOCKED
     * - task: string (the instruction)
     *
     * Optional:
     * - priority: string (low, normal, high, urgent)
     * - created_by: string (mobile, web, api, cli)
     * - metadata: object (extra context)
     */
    public function store(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'project' => 'required|string|in:havuncore,havunadmin,herdenkingsportaal,judotoernooi',
            'task' => 'required|string|min:5',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'created_by' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $task = ClaudeTask::create([
            'project' => $request->input('project'),
            'task' => $request->input('task'),
            'priority' => $request->input('priority', 'normal'),
            'created_by' => $request->input('created_by', 'api'),
            'metadata' => $request->input('metadata'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'task' => $task,
        ], 201);
    }

    /**
     * Display the specified task
     */
    public function show(string $id): JsonResponse
    {
        $task = ClaudeTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'task' => $task,
        ]);
    }

    /**
     * Get pending tasks for a specific project
     *
     * URL: /api/claude/tasks/pending/{project}
     */
    public function pending(string $project): JsonResponse
    {
        $tasks = ClaudeTask::pending()
            ->forProject($project)
            ->byPriority()
            ->get();

        return response()->json([
            'success' => true,
            'project' => $project,
            'count' => $tasks->count(),
            'tasks' => $tasks,
        ]);
    }

    /**
     * Mark task as started
     *
     * URL: POST /api/claude/tasks/{id}/start
     */
    public function start(string $id): JsonResponse
    {
        $task = ClaudeTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        if (!$task->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not pending',
                'current_status' => $task->status,
            ], 400);
        }

        $task->markAsStarted();

        return response()->json([
            'success' => true,
            'message' => 'Task marked as started',
            'task' => $task->fresh(),
        ]);
    }

    /**
     * Mark task as completed
     *
     * URL: POST /api/claude/tasks/{id}/complete
     * Body: { "result": "Task completed successfully. Files created: ..." }
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'result' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $task = ClaudeTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        if (!$task->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not running',
                'current_status' => $task->status,
            ], 400);
        }

        $task->markAsCompleted($request->input('result'));

        return response()->json([
            'success' => true,
            'message' => 'Task marked as completed',
            'task' => $task->fresh(),
        ]);
    }

    /**
     * Mark task as failed
     *
     * URL: POST /api/claude/tasks/{id}/fail
     * Body: { "error": "Error message..." }
     */
    public function fail(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'error' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $task = ClaudeTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        if (!$task->isRunning()) {
            return response()->json([
                'success' => false,
                'message' => 'Task is not running',
                'current_status' => $task->status,
            ], 400);
        }

        $task->markAsFailed($request->input('error'));

        return response()->json([
            'success' => true,
            'message' => 'Task marked as failed',
            'task' => $task->fresh(),
        ]);
    }

    /**
     * Remove the specified task
     */
    public function destroy(string $id): JsonResponse
    {
        $task = ClaudeTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }
}