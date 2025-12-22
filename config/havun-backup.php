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
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'health_check_schedule' => '0 * * * *', // Hourly
        'max_backup_age_hours' => 25,
        'min_backup_size_bytes' => 1024, // 1 KB minimum
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
