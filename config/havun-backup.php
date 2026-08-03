<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure local and offsite storage locations for backups.
    |
    */

    'storage' => [
        'local' => [
            'disk' => 'backups-local',
            'path' => env('BACKUP_LOCAL_PATH', storage_path('backups')),
        ],
        'offsite' => [
            'disk' => 'hetzner-storage-box',
            'path' => '/havun-backups',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Encryption
    |--------------------------------------------------------------------------
    |
    | Configure backup encryption settings. When enabled, backups will be
    | encrypted using AES-256 encryption.
    |
    | ⚠️ WARNING: Store the encryption password securely! Without it,
    | backups cannot be restored.
    |
    */

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Project Configurations
    |--------------------------------------------------------------------------
    |
    | Define backup configuration for each project.
    |
    */

    'projects' => [
        'havunadmin' => [
            'enabled' => env('BACKUP_HAVUNADMIN_ENABLED', true),
            'type' => 'laravel-app',
            'priority' => 'critical', // critical, high, medium, low
            'schedule' => '0 3 * * *', // Daily at 03:00

            'paths' => [
                'root' => env('HAVUNADMIN_PATH', '/var/www/havunadmin/production'),
                'database' => env('HAVUNADMIN_DATABASE', 'havunadmin_production'),
            ],

            'include' => [
                'database' => true,
                'files' => [
                    'storage/app/invoices',
                    'storage/app/exports',
                ],
                'config' => true, // Backup .env
            ],

            'retention' => [
                'hot_retention_days' => 30,
                'archive_retention_years' => 7, // Belastingdienst compliance
                'auto_cleanup_archive' => false, // NEVER auto-delete!
            ],

            'compliance' => [
                'required' => true,
                'type' => 'belastingdienst', // Dutch tax law
                'data_classification' => 'financial',
            ],

            'encryption' => [
                'enabled' => true,
                'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
            ],

            'notifications' => [
                'email' => [env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com')],
                'on_success' => 'daily-digest',
                'on_failure' => 'immediate',
            ],
        ],

        'herdenkingsportaal' => [
            'enabled' => env('BACKUP_HERDENKINGSPORTAAL_ENABLED', true),
            'type' => 'laravel-app',
            'priority' => 'critical',
            'schedule' => '0 4 * * *', // Daily at 04:00

            'paths' => [
                'root' => env('HERDENKINGSPORTAAL_PATH', '/var/www/herdenkingsportaal/production'),
                'database' => env('HERDENKINGSPORTAAL_DATABASE', 'herdenkingsportaal_production'),
            ],

            'include' => [
                'database' => true,
                'files' => [
                    'storage/app/public/monuments',
                    'storage/app/public/profiles',
                    'storage/app/uploads',
                ],
                'config' => true,
            ],

            'retention' => [
                'hot_retention_days' => 30,
                'archive_retention_years' => 7, // GDPR + compliance
                'auto_cleanup_archive' => false, // NEVER auto-delete!
            ],

            'compliance' => [
                'required' => true,
                'type' => 'gdpr',
                'data_classification' => 'personal-data',
            ],

            'encryption' => [
                'enabled' => true,
                'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
            ],

            'notifications' => [
                'email' => [env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com')],
                'on_success' => 'daily-digest',
                'on_failure' => 'immediate',
            ],
        ],

        'studieplanner' => [
            'enabled' => env('BACKUP_STUDIEPLANNER_ENABLED', true),
            'type' => 'laravel-app',
            'priority' => 'medium',
            'schedule' => '0 5 * * *', // Daily at 05:00

            'paths' => [
                'root' => env('STUDIEPLANNER_PATH', '/var/www/studieplanner/production'),
                'database' => env('STUDIEPLANNER_DATABASE', 'studieplanner'),
            ],

            'include' => [
                'database' => true,
                'files' => [
                    'storage/app/public',
                ],
                'config' => true,
            ],

            'retention' => [
                'hot_retention_days' => 30,
                'archive_retention_years' => 1, // Geen fiscale/GDPR vereisten
                'auto_cleanup_archive' => true,
            ],

            'compliance' => [
                'required' => false,
                'data_classification' => 'internal',
            ],

            'encryption' => [
                'enabled' => true,
                'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
            ],

            'notifications' => [
                'email' => [env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com')],
                'on_success' => 'weekly-digest',
                'on_failure' => 'immediate',
            ],
        ],

        'havuncore' => [
            'enabled' => env('BACKUP_HAVUNCORE_ENABLED', false), // Disabled by default
            'type' => 'laravel-package',
            'priority' => 'high',
            'schedule' => '0 5 * * 0', // Weekly (Sunday 05:00)

            'paths' => [
                'root' => env('HAVUNCORE_PATH', base_path()),
            ],

            'include' => [
                'database' => false,
                'files' => [
                    'src',
                    'config',
                    'database/migrations',
                    'storage/vault',
                ],
                'config' => true,
            ],

            'retention' => [
                'hot_retention_days' => 90,
                'archive_retention_years' => 3,
                'auto_cleanup_archive' => true, // OK to cleanup after 3 years
            ],

            'compliance' => [
                'required' => false,
                'data_classification' => 'internal',
            ],

            'encryption' => [
                'enabled' => true,
            ],

            'notifications' => [
                'email' => [env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com')],
                'on_success' => 'weekly-digest',
                'on_failure' => 'immediate',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificatie — wat er élke nacht moet liggen
    |--------------------------------------------------------------------------
    |
    | Dit is de *verwachting*; de uitvoerder is /usr/local/bin/havun-backup.sh
    | (cron 03:00). `qv:scan --only=backup-coverage` toetst de een aan de ander:
    | bestaat elk verwacht bestand in de nieuwste datummap, is het vers genoeg
    | en niet leeg.
    |
    | Waarom aan de uitkomst meten en niet aan een lijst: tot 01-08-2026 werd
    | `projects` hierboven door niets gelezen, en dus "dekte" het vier projecten
    | terwijl het script er acht deed — inclusief twee databases van apps die
    | 18-07 van de server af waren. Een register dat niemand uitvoert, meldt
    | niets als het fout staat.
    |
    | Een live project (server_path in havun-projects.php) dat hier ontbreekt,
    | is zélf een bevinding: dan heeft niemand opgeschreven wat er bewaard moet
    | blijven. Uitzonderen kan, met een reden — nooit stilzwijgend.
    |
    */

    'verificatie' => [
        'root' => env('BACKUP_VERIFY_ROOT', '/var/backups/havun'),

        // Het backupscript (root) schrijft hier na afloop de uitkomst van zijn
        // eigen run: welke bestanden het maakte, hoe groot, en welke database
        // elke app volgens zijn `.env` gebruikt. Wereldleesbaar, zonder
        // wachtwoorden.
        //
        // Nodig omdat de check op de server als `www-data` draait: die kan de
        // backupmap niet lezen en heeft geen root-sleutel (en die geven zou de
        // webserver-user root maken). Van 01-08 tot 02-08-2026 rapporteerde de
        // nachtelijke cron daardoor `errors=1, high=0` — bewaking die niets
        // meet. Bestaat dit bestand niet, dan draait de scan ergens anders en
        // valt hij terug op SSH.
        'manifest' => env('BACKUP_MANIFEST_PATH', '/var/lib/havun/backup-manifest.json'),

        // Projectslug (uit havun-projects.php) => bestandsnamen onder <root>/<datum>/,
        // productie én staging. Drie projecten hebben een draaiende
        // staging-omgeving (geverifieerd 01-08-2026: /var/www/*/staging plus de
        // nginx-vhosts); die dumps horen er dus te zijn.
        //
        // Een bestandsnaam als waarde gebruikt de standaard-ondergrens
        // (monitoring.min_backup_size_bytes). Hoort een artefact legitiem klein
        // te zijn — users.json is 1,6 KB en comprimeert naar ~600 bytes — dan
        // zet je de naam als sleutel met zijn eigen ondergrens als waarde.
        'verwacht' => [
            'havunadmin' => [
                'havunadmin_production.sql.gz',
                'havunadmin_staging.sql.gz',
                'havunadmin_storage.tar.gz',
            ],
            'herdenkingsportaal' => [
                'herdenkingsportaal_prod.sql.gz',
                'herdenkingsportaal_storage.tar.gz',
                'herdenkingsportaal_staging.sql.gz',
            ],
            'havuncore' => ['havuncore.sql.gz'],
            'judotoernooi' => ['judo_toernooi.sql.gz', 'staging_judo_toernooi.sql.gz'],
            'safehavun' => ['safehavun.sql.gz'],
            'studieplanner-api' => ['studieplanner.sql.gz'],

            // `users.json` is de enige plek waar de VPD-gebruikers bestaan.
            // Tot 01-08-2026 werd er niets van bewaard behalve een handmatige
            // kopie bij de deploy van 28-07; sindsdien loopt hij mee in de
            // nachtelijke run. Eigen ondergrens: het bestand is 1,6 KB en
            // comprimeert naar ~600 bytes, dus de SQL-drempel van 1 KB zou hem
            // elke nacht als "lege dump" melden.
            'vpdupdate' => ['vpdupdate_users.json.gz' => 300],
        ],

        // Uitgezonderd, met reden. Een lege reden telt niet.
        'uitgezonderd' => [
            'havun' => 'statische site, alle inhoud staat in git',
            'havuncore-webapp' => 'build-output; de bron staat in git en wordt bij deploy opnieuw gebouwd',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'health_check_schedule' => '0 * * * *', // Hourly
        'max_backup_age_hours' => 25,
        'min_backup_size_bytes' => 1024, // 1 KB minimum

        // Hoe oud het manifest mag zijn voordat de *meting* zelf een bevinding
        // is. Het wordt na elke backuprun herschreven (03:00), dus meer dan een
        // etmaal betekent dat de meetketen stilstaat — ongeacht hoe gezond de
        // bestanden erin eruitzien.
        'max_meting_age_hours' => 26,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'channels' => ['mail'], // mail, slack, discord
        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'havun22@gmail.com')  ,
            'from' => env('MAIL_FROM_ADDRESS', 'noreply@havun.nl'),
        ],
        'slack' => [
            'webhook' => env('SLACK_BACKUP_WEBHOOK'),
            'channel' => '#backups',
        ],
    ],
];
