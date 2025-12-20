<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIProxyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * AI Proxy Controller
 *
 * Central AI proxy for all Havun projects.
 * Handles Claude API calls, rate limiting, and usage logging.
 *
 * Endpoint: POST /api/ai/chat
 */
class AIProxyController extends Controller
{
    protected AIProxyService $aiProxy;

    public function __construct(AIProxyService $aiProxy)
    {
        $this->aiProxy = $aiProxy;
    }

    /**
     * Send a chat message to Claude API
     *
     * POST /api/ai/chat
     *
     * Body:
     * - tenant: string (required) - Project identifier (infosyst, herdenkingsportaal, havunadmin)
     * - message: string (required) - User message
     * - context: array (optional) - Additional context to include in prompt
     * - system_prompt: string (optional) - Override default system prompt
     * - max_tokens: int (optional) - Max response tokens (default 1024)
     *
     * Response:
     * - success: bool
     * - response: string - Claude's response
     * - usage: object - Token usage stats
     */
    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string|in:infosyst,herdenkingsportaal,havunadmin,havuncore',
            'message' => 'required|string|min:1|max:10000',
            'context' => 'nullable|array',
            'system_prompt' => 'nullable|string|max:5000',
            'max_tokens' => 'nullable|integer|min:100|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check rate limit
        $tenant = $request->input('tenant');
        if (!$this->aiProxy->checkRateLimit($tenant)) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Try again later.',
                'retry_after' => 60,
            ], 429);
        }

        try {
            $result = $this->aiProxy->chat(
                tenant: $tenant,
                message: $request->input('message'),
                context: $request->input('context', []),
                systemPrompt: $request->input('system_prompt'),
                maxTokens: $request->input('max_tokens', 1024)
            );

            return response()->json([
                'success' => true,
                'response' => $result['response'],
                'usage' => $result['usage'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'AI service unavailable: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Get usage statistics for a tenant
     *
     * GET /api/ai/usage?tenant=infosyst&period=day
     */
    public function usage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'period' => 'nullable|in:hour,day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $stats = $this->aiProxy->getUsageStats(
            tenant: $request->input('tenant'),
            period: $request->input('period', 'day')
        );

        return response()->json([
            'success' => true,
            'tenant' => $request->input('tenant'),
            'period' => $request->input('period', 'day'),
            'stats' => $stats,
        ]);
    }

    /**
     * Health check for AI service
     *
     * GET /api/ai/health
     */
    public function health(): JsonResponse
    {
        $status = $this->aiProxy->healthCheck();

        return response()->json([
            'success' => true,
            'status' => $status['healthy'] ? 'ok' : 'degraded',
            'api_configured' => $status['api_configured'],
            'model' => $status['model'],
        ], $status['healthy'] ? 200 : 503);
    }
}
