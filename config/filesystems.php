<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'hetzner-storage-box' => [
            'driver' => 'sftp',
            'host' => env('HETZNER_STORAGE_HOST'),
            'port' => 23,
            'username' => env('HETZNER_STORAGE_USERNAME'),
            'password' => env('HETZNER_STORAGE_PASSWORD'),
            'root' => '',
            'timeout' => 60,
            'directoryPerm' => 0755,
            'visibility' => 'private',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
