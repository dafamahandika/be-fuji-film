<?php
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Filesystem;

return [

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage/foto_paket',
            'visibility' => 'public',
        ],

        'foto_paket' => [
            'driver' => 'local',
            'root' => storage_path('app/public/foto_paket'),
            'url' => env('APP_URL') . '/storage/foto_paket',
            'visibility' => 'public',
        ],
    ],
    
];
