<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutofixProposal extends Model
{
    protected $fillable = [
        'project',
        'exception_class',
        'message',
        'file',
        'line',
        'fix_proposal',
        'status',
        'risk_level',
        'result_message',
        'source',
        'context',
    ];

    protected $casts = [
        'line' => 'integer',
        'context' => 'array',
    ];

    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check rate limit: max 1 proposal per error per hour.
     */
    public static function isRateLimited(string $project, string $exceptionClass, string $file, ?int $line): bool
    {
        return static::where('project', $project)
            ->where('exception_class', $exceptionClass)
            ->where('file', $file)
            ->where('line', $line)
            ->where('created_at', '>=', now()->subHour())
            ->exists();
    }
}
