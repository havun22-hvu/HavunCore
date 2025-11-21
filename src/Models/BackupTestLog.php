<?php

namespace Havun\Core\Models;

use Illuminate\Database\Eloquent\Model;

class BackupTestLog extends Model
{
    protected $table = 'havun_backup_test_logs';

    public $timestamps = false;

    protected $fillable = [
        'project',
        'test_quarter',
        'test_date',
        'backup_tested',
        'test_result',
        'test_report',
        'checked_items',
    ];

    protected $casts = [
        'test_date' => 'datetime',
        'checked_items' => 'array',
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
     * Scope: Voor specifiek kwartaal
     */
    public function scopeForQuarter($query, string $quarter)
    {
        return $query->where('test_quarter', $quarter);
    }

    /**
     * Scope: Passed tests
     */
    public function scopePassed($query)
    {
        return $query->where('test_result', 'pass');
    }

    /**
     * Scope: Failed tests
     */
    public function scopeFailed($query)
    {
        return $query->where('test_result', 'fail');
    }

    /**
     * Get current quarter (e.g. "2025-Q4")
     */
    public static function getCurrentQuarter(): string
    {
        $month = now()->month;
        $quarter = ceil($month / 3);
        return now()->year . '-Q' . $quarter;
    }

    /**
     * Get test result label
     */
    public function getTestResultLabelAttribute(): string
    {
        return match ($this->test_result) {
            'pass' => '✅ Passed',
            'fail' => '❌ Failed',
            default => '❓ Unknown',
        };
    }

    /**
     * Check if all items were checked
     */
    public function allItemsChecked(): bool
    {
        if (!$this->checked_items) {
            return false;
        }

        return !in_array(false, $this->checked_items, true);
    }
}
