<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MCPMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MCPMessageController extends Controller
{
    /**
     * Get all messages for all projects
     *
     * GET /api/mcp/messages
     */
    public function index(): JsonResponse
    {
        $projects = ['HavunCore', 'HavunAdmin', 'Herdenkingsportaal'];
        $messages = [];

        foreach ($projects as $project) {
            $messages[$project] = MCPMessage::forProject($project)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($msg) {
                    return [
                        'id' => $msg->external_id ?? (string) $msg->id,
                        'project' => $msg->project,
                        'content' => $msg->content,
                        'tags' => $msg->tags ?? [],
                        'timestamp' => $msg->created_at->toIso8601String(),
                    ];
                })
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Get messages for a specific project
     *
     * GET /api/mcp/messages/{project}
     */
    public function show(string $project): JsonResponse
    {
        $messages = MCPMessage::forProject($project)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->external_id ?? (string) $msg->id,
                    'project' => $msg->project,
                    'content' => $msg->content,
                    'tags' => $msg->tags ?? [],
                    'timestamp' => $msg->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'project' => $project,
            'messages' => $messages,
        ]);
    }

    /**
     * Store a new message
     *
     * POST /api/mcp/messages
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project' => 'required|string',
            'content' => 'required|string',
            'tags' => 'nullable|array',
            'external_id' => 'nullable|string',
        ]);

        // Check if message with external_id already exists
        if ($request->has('external_id')) {
            $existing = MCPMessage::where('external_id', $request->input('external_id'))->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Message already exists',
                    'data' => $existing,
                ], 200);
            }
        }

        $message = MCPMessage::create([
            'project' => $request->input('project'),
            'content' => $request->input('content'),
            'tags' => $request->input('tags', []),
            'external_id' => $request->input('external_id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message stored',
            'data' => $message,
        ], 201);
    }

    /**
     * Delete a message
     *
     * DELETE /api/mcp/messages/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        // Try to find by external_id first, then by id
        $message = MCPMessage::where('external_id', $id)->first();
        if (!$message) {
            $message = MCPMessage::find($id);
        }

        if (!$message) {
            return response()->json([
                'success' => false,
                'error' => 'Message not found',
            ], 404);
        }

        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted',
        ]);
    }

    /**
     * Sync messages from MCP server (bulk import)
     *
     * POST /api/mcp/messages/sync
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => 'required|array',
            'messages.*.project' => 'required|string',
            'messages.*.content' => 'required|string',
            'messages.*.id' => 'required|string',
        ]);

        $imported = 0;
        $skipped = 0;

        foreach ($request->input('messages') as $msg) {
            // Check if already exists
            $existing = MCPMessage::where('external_id', $msg['id'])->first();
            if ($existing) {
                $skipped++;
                continue;
            }

            MCPMessage::create([
                'project' => $msg['project'],
                'content' => $msg['content'],
                'tags' => $msg['tags'] ?? [],
                'external_id' => $msg['id'],
                'created_at' => isset($msg['timestamp']) ? new \DateTime($msg['timestamp']) : now(),
            ]);
            $imported++;
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }
}
