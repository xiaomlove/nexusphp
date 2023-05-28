<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Date / Time Formatting
    |--------------------------------------------------------------------------
    |
    | These are the formats that Filament will use to display dates and times
    | by default.
    |
    */

    'date_format' => 'Y-m-d',
    'date_time_format' => 'Y-m-d H:i',
    'time_format' => 'H:i:s',

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This is the storage disk Filament will use to find media. You may use any
    | of the disks defined in the `config/filesystems.php`.
    |
    */

    'default_filesystem_disk' => env('TABLES_FILESYSTEM_DRIVER', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Dark mode
    |--------------------------------------------------------------------------
    |
    | By enabling this feature, your users are able to select between a light
    | and dark appearance for tables, via Tailwind's Dark Mode feature.
    |
    | https://tailwindcss.com/docs/dark-mode
    |
    */

    'dark_mode' => false,

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the pagination of tables.
    |
    */

    'pagination' => [
        'default_records_per_page' => 10,
        'records_per_page_select_options' => [10, 50, 100, 200],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the general layout of tables.
    |
    */

    'layout' => [
        'actions' => [
            'cell' => [
                'alignment' => 'right',
            ],
            'modal' => [
                'actions' => [
                    'alignment' => 'left',
                ],
            ],
        ],
    ],

];
