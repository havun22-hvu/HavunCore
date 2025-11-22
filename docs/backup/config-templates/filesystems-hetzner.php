<?php

/**
 * Hetzner Storage Box Configuration
 *
 * Add this to your config/filesystems.php in the 'disks' array
 *
 * For HavunAdmin: use root => '/havun-backups/havunadmin'
 * For Herdenkingsportaal: use root => '/havun-backups/herdenkingsportaal'
 */

return [
    'disks' => [

        // === HETZNER STORAGE BOX (Offsite Backup) ===
        'hetzner-storage-box' => [
            'driver' => 'sftp',
            'host' => env('HETZNER_STORAGE_HOST'),
            'port' => 23, // Hetzner uses port 23 for SFTP
            'username' => env('HETZNER_STORAGE_USERNAME'),
            'password' => env('HETZNER_STORAGE_PASSWORD'),

            // CHANGE THIS per project:
            // HavunAdmin: '/havun-backups/havunadmin'
            // Herdenkingsportaal: '/havun-backups/herdenkingsportaal'
            'root' => '/havun-backups/PROJECT_NAME_HERE',

            'timeout' => 60,
            'directoryPerm' => 0755,
            'visibility' => 'private',
        ],

        // === LOCAL BACKUP STORAGE ===
        'backups-local' => [
            'driver' => 'local',
            'root' => storage_path('backups'),
            'visibility' => 'private',
        ],
    ],
];
