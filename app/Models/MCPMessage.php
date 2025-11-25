<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MCPMessage extends Model
{
    protected $table = 'mcp_messages';

    protected $fillable = [
        'project',
        'content',
        'tags',
        'external_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to filter by project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }

    /**
     * Get recent messages
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get messages by tag
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
