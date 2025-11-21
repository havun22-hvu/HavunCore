<?php

namespace Havun\Core\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $table = 'havun_backup_logs';

    protected $fillable = [
        'project',
        'project_type',
        'backup_name',
        'backup_date',
        'backup_size',
        'backup_checksum',
        'disk_local',
        'disk_offsite',
        'offsite_path',
        'status',
        'error_message',
        'duration_seconds',
        'is_encrypted',
        'retention_years',
        'can_auto_delete',
        'notification_sent',
        'notified_at',
    ];

    protected $casts = [
        'backup_date' => 'datetime',
        'disk_local' => 'boolean',
        'disk_offsite' => 'boolean',
        'is_encrypted' => 'boolean',
        'can_auto_delete' => 'boolean',
        'notification_sent' => 'boolean',
        'notified_at' => 'datetime',
    ];

    /**
     * Get latest backup for a project
     */
    public static function latestByProject(string $project): ?self
    {
        return static::where('project', $project)
            ->orderBy('backup_date', 'desc')
            ->first();
    }

    /**
     * Scope: Succesvolle backups
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Vandaag
     */
    public function scopeToday($query)
    {
        return $query->whereDate('backup_date', today());
    }

    /**
     * Scope: Voor specifiek project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', $project);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->backup_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get backup age in hours
     */
    public function getAgeHoursAttribute(): float
    {
        return now()->diffInHours($this->backup_date);
    }

    /**
     * Check if backup is too old
     */
    public function isTooOld(int $maxHours = 25): bool
    {
        return $this->age_hours > $maxHours;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'failed' => 'danger',
            'partial' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => '✅ Success',
            'failed' => '❌ Failed',
            'partial' => '⚠️ Partial',
            default => '❓ Unknown',
        };
    }
}
