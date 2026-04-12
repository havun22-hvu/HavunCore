<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Slow Query
 *
 * Tracks database queries exceeding the configured threshold.
 */
class SlowQuery extends Model
{
    public $timestamps = false;

    protected $table = 'slow_queries';

    protected $fillable = [
        'project',
        'query',
        'time_ms',
        'connection',
        'request_path',
        'created_at',
    ];

    protected $casts = [
        'time_ms' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Scope recent slow queries
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope queries slower than threshold
     */
    public function scopeSlowerThan($query, float $ms)
    {
        return $query->where('time_ms', '>=', $ms);
    }
}
