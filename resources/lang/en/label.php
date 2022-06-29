<?php

return [
    'setting' => [
        'nav_text' => 'Setting',
        'backup' => [
            'tab_header' => 'Backup',
            'enabled' => 'Enabled',
            'enabled_help' => 'Enable backup or not',
            'frequency' => 'Frequency',
            'frequency_help' => 'Backup frequency',
            'hour' => 'Hour',
            'hour_help' => 'Do backup at this hour',
            'minute' => 'Minute',
            'minute_help' => "The backup is performed at the minute of the previous hour. If the frequency is pressed 'hourly', this value will be ignored",
            'google_drive_client_id' => 'Google Drive client ID',
            'google_drive_client_secret' => 'Google Drive client secret',
            'google_drive_refresh_token' => 'Google Drive refresh token',
            'google_drive_folder_id' => 'Google Drive folder ID',
            'via_ftp' => 'Backup via FTP',
            'via_ftp_help' => 'Whether to save via FTP. If so, add the configuration information to the .env file, refer to <a href="https://laravel.com/docs/master/filesystem#ftp-driver-configuration">Laravel doc</a>',
            'via_sftp' => 'Backup via SFTP',
            'via_sftp_help' => 'Whether to save via FTP. If so, add the configuration information to the .env file, refer to <a href="https://laravel.com/docs/master/filesystem#sftp-driver-configuration">Laravel doc</a>',
        ],
        'hr' => [
            'tab_header' => 'H&R',
            'mode' => 'Mode',
            'inspect_time' => 'Inspect time',
            'inspect_time_help' => 'The duration of the examination is calculated from the completion of the download, in hours',
            'seed_time_minimum' => 'Seed time minimum',
            'seed_time_minimum_help' => 'The shortest time to do the seeds to meet the standard, in hours, must be less than the length of the expedition',
            'ignore_when_ratio_reach' => 'Achievement Sharing Rate',
            'ignore_when_ratio_reach_help' => 'The minimum sharing rate to meet the standard',
            'ban_user_when_counts_reach' => 'H&R counts limit',
            'ban_user_when_counts_reach_help' => 'The number of H&R reaches this value and the account will be disabled',
        ]
    ],
    'user' => [

    ]
];
