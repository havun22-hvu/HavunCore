<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChaosResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'experiment',
        'status',
        'duration_ms',
        'checks',
        'created_at',
    ];

    protected $casts = [
        'checks' => 'array',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function scopeForExperiment($query, string $experiment)
    {
        return $query->where('experiment', $experiment);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'fail');
    }
}
