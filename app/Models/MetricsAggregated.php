<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Metrics Aggregated
 *
 * Hourly and daily rollups of request metrics.
 */
class MetricsAggregated extends Model
{
    protected $table = 'metrics_aggregated';

    protected $fillable = [
        'period',
        'period_start',
        'path',
        'request_count',
        'error_count',
        'server_error_count',
        'avg_response_time_ms',
        'p95_response_time_ms',
        'p99_response_time_ms',
        'min_response_time_ms',
        'max_response_time_ms',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'request_count' => 'integer',
        'error_count' => 'integer',
        'server_error_count' => 'integer',
        'avg_response_time_ms' => 'decimal:2',
        'p95_response_time_ms' => 'decimal:2',
        'p99_response_time_ms' => 'decimal:2',
        'min_response_time_ms' => 'decimal:2',
        'max_response_time_ms' => 'decimal:2',
    ];

    /**
     * Scope by period type
     */
    public function scopeHourly($query)
    {
        return $query->where('period', 'hourly');
    }

    /**
     * Scope by period type
     */
    public function scopeDaily($query)
    {
        return $query->where('period', 'daily');
    }

    /**
     * Scope by path (null = global aggregate)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('path');
    }

    /**
     * Scope for a specific path
     */
    public function scopeForPath($query, string $path)
    {
        return $query->where('path', $path);
    }
}
