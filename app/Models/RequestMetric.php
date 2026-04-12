<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Request Metric
 *
 * Tracks API request performance for observability.
 */
class RequestMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'method',
        'path',
        'route_name',
        'status_code',
        'response_time_ms',
        'ip_address',
        'tenant',
        'user_agent',
        'memory_usage_kb',
        'created_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'memory_usage_kb' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Scope recent metrics
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope by path
     */
    public function scopeForPath($query, string $path)
    {
        return $query->where('path', $path);
    }

    /**
     * Scope errors (4xx + 5xx)
     */
    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Scope server errors (5xx only)
     */
    public function scopeServerErrors($query)
    {
        return $query->where('status_code', '>=', 500);
    }

    /**
     * Scope by tenant
     */
    public function scopeForTenant($query, string $tenant)
    {
        return $query->where('tenant', $tenant);
    }
}
