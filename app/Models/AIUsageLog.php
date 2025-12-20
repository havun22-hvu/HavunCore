<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI Usage Log
 *
 * Tracks Claude API usage per tenant for billing and monitoring.
 */
class AIUsageLog extends Model
{
    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'tenant',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'execution_time_ms',
        'model',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'execution_time_ms' => 'integer',
    ];

    /**
     * Scope by tenant
     */
    public function scopeForTenant($query, string $tenant)
    {
        return $query->where('tenant', $tenant);
    }

    /**
     * Scope recent logs
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get daily stats for a tenant
     */
    public static function dailyStats(string $tenant): array
    {
        $stats = static::forTenant($tenant)
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('
                COUNT(*) as requests,
                SUM(total_tokens) as tokens,
                AVG(execution_time_ms) as avg_time
            ')
            ->first();

        return [
            'requests' => (int) ($stats->requests ?? 0),
            'tokens' => (int) ($stats->tokens ?? 0),
            'avg_time_ms' => round($stats->avg_time ?? 0),
        ];
    }

    /**
     * Get all tenants with their usage
     */
    public static function allTenantsStats(string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        return static::where('created_at', '>=', $since)
            ->selectRaw('
                tenant,
                COUNT(*) as requests,
                SUM(total_tokens) as tokens
            ')
            ->groupBy('tenant')
            ->get()
            ->keyBy('tenant')
            ->toArray();
    }
}
