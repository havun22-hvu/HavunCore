<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chaos Engineering Enabled
    |--------------------------------------------------------------------------
    | Safety switch — must be explicitly enabled.
    */
    'enabled' => env('CHAOS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Project Endpoints (for endpoint-probe experiment)
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'havuncore' => 'https://havuncore.havun.nl/api/health',
        'havunadmin' => 'https://havunadmin.havun.nl/up',
        'herdenkingsportaal' => 'https://herdenkingsportaal.nl/up',
        'infosyst' => 'https://infosyst.havun.nl/up',
        'safehavun' => 'https://safehavun.havun.nl/up',
        'judotoernooi' => 'https://judotoernooi.havun.nl/up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Defaults
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'recovery_timeout' => 60,
        'success_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Health Thresholds
    |--------------------------------------------------------------------------
    */
    'health' => [
        'disk_warning_percent' => 80,
        'disk_critical_percent' => 90,
        'db_slow_threshold_ms' => 50,
        'memory_warning_mb' => 256,
    ],
];
