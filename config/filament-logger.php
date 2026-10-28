<?php

return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',

    'activity_resource' => \App\Filament\Logger\ActivityResource::class,
    'scoped_to_tenant' => true,
    'navigation_sort' => 4,

    'resources' => [
        'enabled' => true,
        'log_name' => 'Resource',
        'logger' => \Jacobtims\FilamentLogger\Loggers\ResourceLogger::class,
        'color' => 'success',

        'exclude' => [
            // The panel still registers the package resource while activity_resource points at
            // our override, so exclude it too or activity rows would log themselves in a loop.
            \Jacobtims\FilamentLogger\Resources\ActivityResource::class,
            // App\Filament\Resources\UserResource::class,
        ],
        'cluster' => null,
        'navigation_group' => 'Settings',
    ],

    'access' => [
        'enabled' => true,
        'logger' => \Jacobtims\FilamentLogger\Loggers\AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
    ],

    'notifications' => [
        'enabled' => true,
        'logger' => \Jacobtims\FilamentLogger\Loggers\NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
    ],

    'models' => [
        'enabled' => true,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => \Jacobtims\FilamentLogger\Loggers\ModelLogger::class,
        'register' => [
            // App\Models\User::class,
        ],
    ],

    'custom' => [
        // [
        //     'log_name' => 'Custom',
        //     'color' => 'primary',
        // ]
    ],
];
