<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Components
    |--------------------------------------------------------------------------
    |
    | These are the settings that Filament will use to control the appearance
    | and behaviour of form components.
    |
    */

    'components' => [

        'actions' => [

            'modal' => [

                'actions' => [
                    'alignment' => 'left',
                ],

            ],

        ],

        'date_time_picker' => [
            'first_day_of_week' => 1, // 0 to 7 are accepted values, with Monday as 1 and Sunday as 7 or 0.
            'display_formats' => [
                'date' => 'Y-m-d',
                'date_time' => 'Y-m-d H:i:s',
                'date_time_with_seconds' => 'Y-m-d H:i:s',
                'time' => 'H:i',
                'time_with_seconds' => 'H:i:s',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This is the storage disk Filament will use to put media. You may use any
    | of the disks defined in the `config/filesystems.php`.
    |
    */

    'default_filesystem_disk' => env('FORMS_FILESYSTEM_DRIVER', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Dark mode
    |--------------------------------------------------------------------------
    |
    | By enabling this feature, your users are able to select between a light
    | and dark appearance for forms, via Tailwind's Dark Mode feature.
    |
    | https://tailwindcss.com/docs/dark-mode
    |
    */

    'dark_mode' => false,

];
