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
            'remote_path' => '/var/www/havunadmin/production',
            'url' => 'https://havunadmin.havun.nl',
        ],

        'havunclub' => [
            'enabled' => env('QV_HAVUNCLUB_ENABLED', true),
            'path' => env('HAVUNCLUB_LOCAL_PATH', 'D:/GitHub/HavunClub'),
            'remote_path' => '/var/www/havunclub/production',
            'url' => 'https://havunclub.havun.nl',
        ],

        'herdenkingsportaal' => [
            'enabled' => env('QV_HERDENKINGSPORTAAL_ENABLED', true),
            'path' => env('HERDENKINGSPORTAAL_LOCAL_PATH', 'D:/GitHub/Herdenkingsportaal'),
            'remote_path' => '/var/www/herdenkingsportaal/production',
            'url' => 'https://herdenkingsportaal.nl',
        ],

        'studieplanner' => [
            'enabled' => env('QV_STUDIEPLANNER_ENABLED', true),
            'path' => env('STUDIEPLANNER_LOCAL_PATH', 'D:/GitHub/Studieplanner-api'),
            'remote_path' => '/var/www/studieplanner/production',
            'url' => 'https://api.studieplanner.havun.nl',
        ],

        'judotoernooi' => [
            'enabled' => env('QV_JUDOTOERNOOI_ENABLED', true),
            'path' => env('JUDOTOERNOOI_LOCAL_PATH', 'D:/GitHub/JudoToernooi/laravel'),
            'remote_path' => '/var/www/judotoernooi/repo-prod/laravel',
            'url' => 'https://judotournament.org',
        ],

        'infosyst' => [
            'enabled' => env('QV_INFOSYST_ENABLED', true),
            'path' => env('INFOSYST_LOCAL_PATH', 'D:/GitHub/Infosyst'),
            'remote_path' => '/var/www/infosyst/production',
            'url' => 'https://infosyst.havun.nl',
        ],

        'safehavun' => [
            'enabled' => env('QV_SAFEHAVUN_ENABLED', true),
            'path' => env('SAFEHAVUN_LOCAL_PATH', 'D:/GitHub/SafeHavun'),
            'remote_path' => '/var/www/safehavun/production',
            'url' => 'https://safehavun.havun.nl',
        ],

        'havuncore' => [
            'enabled' => env('QV_HAVUNCORE_ENABLED', true),
            'path' => env('HAVUNCORE_LOCAL_PATH', base_path()),
            'remote_path' => '/var/www/havuncore/production',
            'url' => 'https://havuncore.havun.nl',
        ],

        // Studieplanner mobile (Expo) — no composer, no URL, no SSL.
        // Registered so `critical-paths:verify --project=studieplanner-mobile`
        // can resolve the repo root for file-existence checks.
        'studieplanner-mobile' => [
            'enabled' => env('QV_STUDIEPLANNER_MOBILE_ENABLED', true),
            'path' => env('STUDIEPLANNER_MOBILE_LOCAL_PATH', 'D:/GitHub/Studieplanner'),
        ],

        // Munus — docs-only specificatie/planning project. Geen Laravel,
        // geen composer/npm/SSL/Observatory checks (auto-geskipt door
        // afwezigheid van composer.json/package.json). Wel: docs:audit voor
        // KB-onderhoud (markdown structure/links/zombie-refs).
        'munus' => [
            'enabled' => env('QV_MUNUS_ENABLED', true),
            'path' => env('MUNUS_LOCAL_PATH', 'D:/GitHub/Munus'),
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
    | Form-validation coverage mode
    |--------------------------------------------------------------------------
    |
    | Selects which of the two coverage estimates the `forms` check gates on:
    |   - 'usages'      : counts FormRequest type-hint injection points (default,
    |                     route-proportional — a shared FormRequest is credited
    |                     once per route it guards).
    |   - 'occurrences' : legacy class-definition count (rollback valve).
    | Both numbers are always reported in the finding payload regardless of mode.
    | See docs/kb/runbooks/forms-coverage-heuristic.md.
    |
    */

    'forms_coverage_mode' => env('QV_FORMS_COVERAGE_MODE', 'usages'),

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
        // Heuristic-limitatie: telt alle markTestSkipped strings, ook
        // `if (file_exists(...)) { ... } else { markTestSkipped(...) }` waar
        // de else-tak runtime onbereikbaar is. Threshold dus loose: HP heeft
        // 25 statische, runtime maar 7. Lager = veel false positives.
        'test_skip_max' => 10,
        // Repo-hygiene residu (.env.bak* lifecycle). Zie:
        // docs/kb/reference/repo-hygiene-policy.md
        'residu_archive_after_days' => 14,
        'residu_purge_after_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Repo hygiene (residu check)
    |--------------------------------------------------------------------------
    |
    | Drives the `residu` sub-check of `qv:scan`. Detects .env backup files in
    | production checkouts that exceed the lifecycle described in
    | docs/kb/reference/repo-hygiene-policy.md.
    |
    */

    'residu' => [
        // Server SSH target — single host shared with the `server` check.
        // Each project's `remote_path` is scanned over this SSH session.
        'host' => env('QV_RESIDU_HOST', '188.245.159.115'),
        'user' => env('QV_RESIDU_USER', 'root'),
        // Where Laag 2 archives live. Used for the >90-day purge candidate detection.
        'archive_root' => env('QV_RESIDU_ARCHIVE_ROOT', '/var/backups/havun-env'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Test-erosion exclusions
    |--------------------------------------------------------------------------
    |
    | Deletions of these paths never count as erosion. Laravel's default
    | `ExampleTest.php` files contain only `assertTrue(true)` — removing them
    | is sanitization, not test-loss.
    |
    */

    'test_erosion' => [
        'ignored_deletions' => [
            'tests/Unit/ExampleTest.php',
            'tests/Feature/ExampleTest.php',
        ],
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
