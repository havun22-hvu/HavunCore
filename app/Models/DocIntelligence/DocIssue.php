<?php

namespace App\Models\DocIntelligence;

use Illuminate\Database\Eloquent\Model;

class DocIssue extends Model
{
    protected $connection = 'doc_intelligence';
    protected $table = 'doc_issues';

    protected $fillable = [
        'project',
        'issue_type',
        'severity',
        'title',
        'details',
        'affected_files',
        'suggested_action',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'details' => 'array',
        'affected_files' => 'array',
        'resolved_at' => 'datetime',
    ];

    // Issue types
    const TYPE_INCONSISTENT = 'inconsistent';
    const TYPE_DUPLICATE = 'duplicate';
    const TYPE_OUTDATED = 'outdated';
    const TYPE_MISSING = 'missing';
    const TYPE_BROKEN_LINK = 'broken_link';
    const TYPE_ORPHANED = 'orphaned';

    // Severities
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';

    // Statuses
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_IGNORED = 'ignored';

    /**
     * Mark issue as resolved
     */
    public function resolve(string $resolvedBy = 'user'): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Mark issue as ignored
     */
    public function ignore(): void
    {
        $this->update([
            'status' => self::STATUS_IGNORED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Get human-readable issue type
     */
    public function getTypeLabel(): string
    {
        return match($this->issue_type) {
            self::TYPE_INCONSISTENT => '⚠️ Inconsistent',
            self::TYPE_DUPLICATE => '📋 Duplicate',
            self::TYPE_OUTDATED => '📅 Outdated',
            self::TYPE_MISSING => '❓ Missing',
            self::TYPE_BROKEN_LINK => '🔗 Broken Link',
            self::TYPE_ORPHANED => '👻 Orphaned',
            default => $this->issue_type,
        };
    }

    /**
     * Get severity color/emoji
     */
    public function getSeverityLabel(): string
    {
        return match($this->severity) {
            self::SEVERITY_HIGH => '🔴 High',
            self::SEVERITY_MEDIUM => '🟡 Medium',
            self::SEVERITY_LOW => '🟢 Low',
            default => $this->severity,
        };
    }

    /**
     * Scope: only open issues
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope: filter by project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', strtolower($project));
    }

    /**
     * Scope: filter by severity
     */
    public function scopeWithSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: high priority issues (high severity or inconsistent)
     */
    public function scopeHighPriority($query)
    {
        return $query->where(function ($q) {
            $q->where('severity', self::SEVERITY_HIGH)
              ->orWhere('issue_type', self::TYPE_INCONSISTENT);
        });
    }
}
