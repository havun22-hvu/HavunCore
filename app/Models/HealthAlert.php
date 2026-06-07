<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A health/uptime alert surfaced in-app (no email).
 *
 * One row per source `key`: a "down" event upserts an open alert, an "up"
 * event resolves it. Fed by the server-side health check (health:alert command),
 * consumed by the HavunCore webapp notification panel.
 */
class HealthAlert extends Model
{
    protected $fillable = [
        'key',
        'scope',
        'project',
        'severity',
        'title',
        'body',
        'status',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }
}
