<?php

namespace Havun\Core\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreLog extends Model
{
    protected $table = 'havun_restore_logs';

    public $timestamps = false;

    protected $fillable = [
        'project',
        'backup_name',
        'restore_date',
        'restore_type',
        'restored_by',
        'restore_reason',
        'status',
        'error_message',
    ];

    protected $casts = [
        'restore_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Scope: Voor specifiek project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }

    /**
     * Scope: Succesvolle restores
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Failed restores
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => '✅ Success',
            'failed' => '❌ Failed',
            default => '❓ Unknown',
        };
    }

    /**
     * Get restore type label
     */
    public function getRestoreTypeLabelAttribute(): string
    {
        return match ($this->restore_type) {
            'production' => '🚀 Production',
            'test' => '🧪 Test',
            'archive' => '📦 Archive',
            default => '❓ Unknown',
        };
    }
}
