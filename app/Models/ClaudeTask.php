<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ClaudeTask extends Model
{
    protected $fillable = [
        'project',
        'task',
        'status',
        'priority',
        'result',
        'error',
        'created_by',
        'started_at',
        'completed_at',
        'execution_time_seconds',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeForProject(Builder $query, string $project): Builder
    {
        return $query->where('project', $project);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')");
    }

    /**
     * Mark task as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(string $result): void
    {
        $this->update([
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now(),
            'execution_time_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : 0,
        ]);
    }

    /**
     * Mark task as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
            'execution_time_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : 0,
        ]);
    }

    /**
     * Check if task is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if task is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if task is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
