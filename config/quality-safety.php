<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quality & Safety scan configuration
    |--------------------------------------------------------------------------
    |
    | Driving config for the `qv:scan` artisan command. Per-project entries
    | describe where code lives on the dev machine (for local scans) and on
    | production (for SSL / live checks). The scan is read-only — it never
    | modifies code or dependencies.
    |
    */

    'projects' => [
        'havunadmin' => [
            'enabled' => env('QV_HAVUNADMIN_ENABLED', true),
            'path' => env('HAVUNADMIN_LOCAL_PATH', 'D:/GitHub/HavunAdmin'),
            'url' => 'https://havunadmin.havun.nl',
            'has_composer' => true,
            'has_npm' => true,
        ],

        'herdenkingsportaal' => [
            'enabled' => env('QV_HERDENKINGSPORTAAL_ENABLED', true),
            'path' => env('HERDENKINGSPORTAAL_LOCAL_PATH', 'D:/GitHub/Herdenkingsportaal'),
            'url' => 'https://herdenkingsportaal.nl',
            'has_composer' => true,
            'has_npm' => true,
        ],

        'studieplanner' => [
            'enabled' => env('QV_STUDIEPLANNER_ENABLED', true),
            'path' => env('STUDIEPLANNER_LOCAL_PATH', 'D:/GitHub/Studieplanner-api'),
            'url' => 'https://studieplanner-api.havun.nl',
            'has_composer' => true,
            'has_npm' => false,
        ],

        'judotoernooi' => [
            'enabled' => env('QV_JUDOTOERNOOI_ENABLED', true),
            'path' => env('JUDOTOERNOOI_LOCAL_PATH', 'D:/GitHub/JudoToernooi/laravel'),
            'url' => 'https://judotoernooi.havun.nl',
            'has_composer' => true,
            'has_npm' => true,
        ],

        'infosyst' => [
            'enabled' => env('QV_INFOSYST_ENABLED', true),
            'path' => env('INFOSYST_LOCAL_PATH', 'D:/GitHub/Infosyst'),
            'url' => 'https://infosyst.havun.nl',
            'has_composer' => true,
            'has_npm' => true,
        ],

        'safehavun' => [
            'enabled' => env('QV_SAFEHAVUN_ENABLED', true),
            'path' => env('SAFEHAVUN_LOCAL_PATH', 'D:/GitHub/SafeHavun'),
            'url' => 'https://safehavun.havun.nl',
            'has_composer' => true,
            'has_npm' => true,
        ],

        'havuncore' => [
            'enabled' => env('QV_HAVUNCORE_ENABLED', true),
            'path' => env('HAVUNCORE_LOCAL_PATH', base_path()),
            'url' => 'https://havuncore.havun.nl',
            'has_composer' => true,
            'has_npm' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'ssl_warning_days' => 30,
        'ssl_critical_days' => 7,
        'min_severity_to_log' => 'medium', // informational, low, medium, high, critical
    ],

    /*
    |--------------------------------------------------------------------------
    | External binaries
    |--------------------------------------------------------------------------
    |
    | Override paths if composer / npm / openssl aren't on the PATH in the
    | scheduled-runner environment.
    |
    */

    'bin' => [
        'composer' => env('QV_COMPOSER_BIN', 'composer'),
        'npm' => env('QV_NPM_BIN', 'npm'),
        'openssl' => env('QV_OPENSSL_BIN', 'openssl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => env('QV_STORAGE_DISK', 'local'),
        'root' => env('QV_STORAGE_ROOT', 'qv-scans'),
    ],
];
