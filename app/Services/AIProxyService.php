<?php

namespace App\Services;

use App\Models\AIUsageLog;
use App\Support\Timing\Stopwatch;
use Illuminate\Support\Facades\Artisan;
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
    protected CircuitBreaker $circuitBreaker;
    protected Stopwatch $stopwatch;

    public function __construct(Stopwatch $stopwatch)
    {
        $this->apiKey = config('services.claude.api_key', '');
        // No default here on purpose: config/services.php owns it, so there is one
        // place to change when the model ID expires — not two that can drift apart.
        $this->model = config('services.claude.model');
        $this->circuitBreaker = new CircuitBreaker('claude_api');
        $this->stopwatch = $stopwatch;
    }

    /**
     * The ceiling covers thinking and answer together, and the configured model
     * thinks by default — 1024 left so little room for the answer that it broke
     * off mid-sentence. This is a ceiling, not a spend: you pay for what is
     * generated. Staying near 16000 also keeps non-streaming calls under the
     * HTTP timeout.
     */
    public const MAX_TOKENS = 16000;

    /**
     * Send a chat message to Claude
     */
    public function chat(
        string $tenant,
        string $message,
        array $context = [],
        ?string $systemPrompt = null,
        int $maxTokens = self::MAX_TOKENS
    ): array {
        $measurement = $this->stopwatch->start();

        // Circuit breaker check
        if (! $this->circuitBreaker->isAvailable()) {
            throw new \Exception('Claude API circuit breaker is open — service temporarily unavailable');
        }

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
            $this->circuitBreaker->recordFailure();
            Log::error('AI Proxy: Claude API error', [
                'tenant' => $tenant,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            if ($response->status() === 404) {
                $this->raiseModelAlert();
            }
            throw new \Exception('Claude API error: ' . $response->status());
        }

        $this->clearModelAlert();
        $this->circuitBreaker->recordSuccess();
        $data = $response->json();
        $text = $this->firstTextBlock($data['content'] ?? []);
        $usage = $data['usage'] ?? [];

        $executionMs = $measurement->elapsedMs();

        // Log usage
        $this->logUsage($tenant, $usage, $executionMs);

        return [
            'response' => $text,
            'usage' => [
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'execution_time_ms' => $executionMs,
            ],
        ];
    }

    /**
     * The answer is not always the first block. A thinking model puts its
     * thinking blocks ahead of the text, so reading content[0]['text'] returns
     * nothing — measured on production 2026-08-05, right after the switch to
     * Opus 5: 583 tokens generated, empty string returned, no error anywhere.
     *
     * A reply with no text block at all (a refusal, a thinking-only turn) is a
     * real outcome, not a failure: empty string, and the caller decides.
     *
     * @param array<int, array<string, mixed>> $content
     */
    protected function firstTextBlock(array $content): string
    {
        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'text') {
                return (string) ($block['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * A 404 from the messages endpoint means the configured model is not there —
     * almost always because the ID was retired. The log alone is not enough: on
     * 2026-08-05 that exact failure ran for 19 hours before a person read it.
     *
     * Only 404 raises this. A 429 or a 500 is the API having a moment; a model
     * that does not exist stays broken until someone changes the config.
     */
    protected function raiseModelAlert(): void
    {
        $this->alert([
            'key' => 'ai-proxy-model',
            '--severity' => 'critical',
            '--title' => 'AI-model niet beschikbaar',
            '--body' => "De Claude API kent het ingestelde model niet: {$this->model}. "
                . 'Waarschijnlijk uitgefaseerd — zet een geldig model in CLAUDE_MODEL. '
                . 'Zie docs/kb/patterns/model-id-verloopt.md.',
        ]);
    }

    protected function clearModelAlert(): void
    {
        $this->alert(['key' => 'ai-proxy-model', '--status' => 'up']);
    }

    /**
     * Alerting must never be the reason a working call fails.
     */
    private function alert(array $arguments): void
    {
        try {
            Artisan::call('health:alert', $arguments);
        } catch (\Throwable $e) {
            Log::warning('AI Proxy: health alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check rate limit for tenant
     */
    public function checkRateLimit(string $tenant): bool
    {
        $key = "ai_rate_limit:{$tenant}";
        // `?? 60` rather than config()'s default arg because an explicit
        // `null` in config is a valid state we still want to fall back from.
        $limit = config('services.claude.rate_limit') ?? 60; // requests per minute

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
    protected function logUsage(string $tenant, array $usage, int $executionMs): void
    {
        try {
            AIUsageLog::create([
                'tenant' => $tenant,
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                'execution_time_ms' => $executionMs,
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
            'avg_execution_time_ms' => (int) round($stats->avg_execution_time_ms ?? 0),
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
