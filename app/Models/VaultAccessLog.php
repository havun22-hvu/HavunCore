<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project',
        'action',
        'resource_type',
        'resource_key',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Log an access event
     */
    public static function log(
        string $project,
        string $action,
        string $resourceType,
        string $resourceKey,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'project' => $project,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_key' => $resourceKey,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * Scope by project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }

    /**
     * Scope recent logs
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
