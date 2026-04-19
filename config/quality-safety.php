<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quality & Safety scan configuration
    |--------------------------------------------------------------------------
    |
    | Driving config for the `qv:scan` artisan command. The scanner auto-detects
    | composer.json / package.json in each project path — no need to declare
    | which ecosystems a project uses. The scan is read-only: it never modifies
    | code or dependencies.
    |
    */

    'projects' => [
        'havunadmin' => [
            'enabled' => env('QV_HAVUNADMIN_ENABLED', true),
            'path' => env('HAVUNADMIN_LOCAL_PATH', 'D:/GitHub/HavunAdmin'),
            'url' => 'https://havunadmin.havun.nl',
        ],

        'herdenkingsportaal' => [
            'enabled' => env('QV_HERDENKINGSPORTAAL_ENABLED', true),
            'path' => env('HERDENKINGSPORTAAL_LOCAL_PATH', 'D:/GitHub/Herdenkingsportaal'),
            'url' => 'https://herdenkingsportaal.nl',
        ],

        'studieplanner' => [
            'enabled' => env('QV_STUDIEPLANNER_ENABLED', true),
            'path' => env('STUDIEPLANNER_LOCAL_PATH', 'D:/GitHub/Studieplanner-api'),
            'url' => 'https://studieplanner-api.havun.nl',
        ],

        'judotoernooi' => [
            'enabled' => env('QV_JUDOTOERNOOI_ENABLED', true),
            'path' => env('JUDOTOERNOOI_LOCAL_PATH', 'D:/GitHub/JudoToernooi/laravel'),
            'url' => 'https://judotoernooi.havun.nl',
        ],

        'infosyst' => [
            'enabled' => env('QV_INFOSYST_ENABLED', true),
            'path' => env('INFOSYST_LOCAL_PATH', 'D:/GitHub/Infosyst'),
            'url' => 'https://infosyst.havun.nl',
        ],

        'safehavun' => [
            'enabled' => env('QV_SAFEHAVUN_ENABLED', true),
            'path' => env('SAFEHAVUN_LOCAL_PATH', 'D:/GitHub/SafeHavun'),
            'url' => 'https://safehavun.havun.nl',
        ],

        'havuncore' => [
            'enabled' => env('QV_HAVUNCORE_ENABLED', true),
            'path' => env('HAVUNCORE_LOCAL_PATH', base_path()),
            'url' => 'https://havuncore.havun.nl',
        ],

        // Server-only entry: triggers the `server` health check (no path/url).
        // composer/npm/ssl/observatory are skipped automatically.
        'server-prod' => [
            'enabled' => env('QV_SERVER_ENABLED', true),
            'host' => env('QV_SERVER_HOST', '188.245.159.115'),
            'user' => env('QV_SERVER_USER', 'root'),
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
        'disk_warning_pct' => 90,
        'disk_critical_pct' => 95,
        // Form-validation coverage (FormRequest + inline ::validate vs write-routes).
        // Heuristic, so be lenient: 60 % is "warn", 30 % is "critical".
        'forms_warning_pct' => 60,
        'forms_critical_pct' => 30,
        // Test-erosion: meer dan dit aantal markTestSkipped wijst op stille
        // uitschakeling van tests. markTestIncomplete telt apart (visible WIP).
        'test_skip_max' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mozilla Observatory
    |--------------------------------------------------------------------------
    |
    | HTTP endpoint for the Observatory v2 API and the minimum acceptable grade.
    | Anything below `min_grade` becomes a finding (`critical` if D/F, otherwise
    | `high`).
    |
    */

    'observatory' => [
        'endpoint' => env('QV_OBSERVATORY_ENDPOINT', 'https://observatory-api.mdn.mozilla.net/api/v2/scan'),
        'min_grade' => env('QV_OBSERVATORY_MIN_GRADE', 'B'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External binaries
    |--------------------------------------------------------------------------
    |
    | Override paths if composer / npm aren't on the PATH in the scheduled-runner
    | environment.
    |
    */

    'bin' => [
        'composer' => env('QV_COMPOSER_BIN', 'composer'),
        'npm' => env('QV_NPM_BIN', 'npm'),
        'ssh' => env('QV_SSH_BIN', 'ssh'),
        'git' => env('QV_GIT_BIN', 'git'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server health (SSH-based)
    |--------------------------------------------------------------------------
    |
    | Connection options + filters used by the `server` check. The check skips
    | any project entry that lacks a `host` field, so leaving `host` unset for
    | regular projects is safe.
    |
    */

    'server' => [
        'ssh_options' => [
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-o', 'StrictHostKeyChecking=accept-new',
        ],
        // Filesystems whose mount-point matches one of these prefixes are
        // ignored (tmpfs, snap loops, container overlays). Empty list = include all.
        'disk_ignore_mounts' => ['/dev', '/proc', '/sys', '/run', '/snap', '/var/lib/docker'],
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
