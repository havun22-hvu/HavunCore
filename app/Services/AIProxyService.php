<?php

namespace App\Services;

use App\Models\AIUsageLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI Proxy Service
 *
 * Central service for Claude API calls.
 * Handles rate limiting, usage logging, and API communication.
 */
class AIProxyService
{
    protected string $apiKey;
    protected string $model;
    protected string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key', '');
        $this->model = config('services.claude.model', 'claude-3-haiku-20240307');
    }

    /**
     * Send a chat message to Claude
     */
    public function chat(
        string $tenant,
        string $message,
        array $context = [],
        ?string $systemPrompt = null,
        int $maxTokens = 1024
    ): array {
        $startTime = microtime(true);

        // Build system prompt
        $system = $systemPrompt ?? $this->getDefaultSystemPrompt($tenant);

        // Build user message with context
        $userMessage = $message;
        if (!empty($context)) {
            $contextString = implode("\n", array_map(fn($item) => "- {$item}", $context));
            $userMessage = "Context:\n{$contextString}\n\nVraag: {$message}";
        }

        // Call Claude API
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
        ->timeout(60)
        ->post($this->apiUrl, [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage]
            ]
        ]);

        if (!$response->successful()) {
            Log::error('AI Proxy: Claude API error', [
                'tenant' => $tenant,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Claude API error: ' . $response->status());
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';
        $usage = $data['usage'] ?? [];

        $executionTime = microtime(true) - $startTime;

        // Log usage
        $this->logUsage($tenant, $usage, $executionTime);

        return [
            'response' => $text,
            'usage' => [
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'execution_time_ms' => round($executionTime * 1000),
            ],
        ];
    }

    /**
     * Check rate limit for tenant
     */
    public function checkRateLimit(string $tenant): bool
    {
        $key = "ai_rate_limit:{$tenant}";
        $limit = config('services.claude.rate_limit', 60); // requests per minute

        $current = Cache::get($key, 0);

        if ($current >= $limit) {
            return false;
        }

        Cache::put($key, $current + 1, 60); // expires in 60 seconds
        return true;
    }

    /**
     * Log usage to database
     */
    protected function logUsage(string $tenant, array $usage, float $executionTime): void
    {
        try {
            AIUsageLog::create([
                'tenant' => $tenant,
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                'execution_time_ms' => round($executionTime * 1000),
                'model' => $this->model,
            ]);
        } catch (\Exception $e) {
            Log::warning('AI Proxy: Failed to log usage', [
                'tenant' => $tenant,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get usage statistics for a tenant
     */
    public function getUsageStats(string $tenant, string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        $stats = AIUsageLog::where('tenant', $tenant)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                AVG(execution_time_ms) as avg_execution_time_ms
            ')
            ->first();

        return [
            'total_requests' => (int) ($stats->total_requests ?? 0),
            'total_input_tokens' => (int) ($stats->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($stats->total_output_tokens ?? 0),
            'total_tokens' => (int) ($stats->total_tokens ?? 0),
            'avg_execution_time_ms' => round($stats->avg_execution_time_ms ?? 0),
        ];
    }

    /**
     * Health check
     */
    public function healthCheck(): array
    {
        return [
            'healthy' => !empty($this->apiKey),
            'api_configured' => !empty($this->apiKey),
            'model' => $this->model,
        ];
    }

    /**
     * Get default system prompt for a tenant
     */
    protected function getDefaultSystemPrompt(string $tenant): string
    {
        return match ($tenant) {
            'infosyst' => 'Je bent een AI-assistent voor Infosyst, een platform voor maatschappelijke en politieke informatie. Geef onderbouwde antwoorden met bronvermelding waar mogelijk. Wees objectief en neutraal.',

            'herdenkingsportaal' => 'Je bent een behulpzame assistent voor het Herdenkingsportaal. Help gebruikers met vragen over het aanmaken van memorials, de monument editor, foto uploads, en blockchain opslag. Wees empathisch en geduldig.',

            'havunadmin' => 'Je bent een technische assistent voor HavunAdmin. Help met vragen over facturatie, klantenbeheer, en het admin systeem.',

            'havuncore' => 'Je bent een technische assistent voor HavunCore. Help met vragen over de centrale hub, Task Queue, Vault, en orchestratie.',

            default => 'Je bent een behulpzame AI-assistent. Antwoord in het Nederlands.',
        };
    }
}
