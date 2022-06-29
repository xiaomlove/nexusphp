<?php

return [
    'setting' => [
        'nav_text' => '設置',
        'backup' => [
            'tab_header' => '備份',
            'enabled' => '是否啟用',
            'enabled_help' => '是否啟用備份功能',
            'frequency' => '頻率',
            'frequency_help' => '備份頻率',
            'hour' => '小時',
            'hour_help' => '在這個點鐘數進行備份',
            'minute' => '分鐘',
            'minute_help' => "在前面點鐘數的這一分鐘進行備份。如果頻率是按 'hourly'，此值會被忽略",
            'google_drive_client_id' => 'Google Drive client ID',
            'google_drive_client_secret' => 'Google Drive client secret',
            'google_drive_refresh_token' => 'Google Drive refresh token',
            'google_drive_folder_id' => 'Google Drive folder ID',
            'via_ftp' => '通過 FTP 保存',
            'via_ftp_help' => '是否通過 FTP 保存。如果通過，把配置信息添加到 .env 文件，參考 <a href="https://laravel.com/docs/master/filesystem#ftp-driver-configuration">Laravel 文檔</a>',
            'via_sftp' => '通過 SFTP 保存',
            'via_sftp_help' => '是否通過 SFTP 保存。如果通過，把配置信息添加到 .env 文件，參考 <a href="https://laravel.com/docs/master/filesystem#sftp-driver-configuration">Laravel 文檔</a>',
        ],
        'hr' => [
            'tab_header' => 'H&R',
            'mode' => '模式',
            'inspect_time' => '考察時長',
            'inspect_time_help' => '考察時長自下載完成後開始計算，單位：小時',
            'seed_time_minimum' => '達標做種時長',
            'seed_time_minimum_help' => '達標的最短做種時長，單位：小時，必須小於考察時長',
            'ignore_when_ratio_reach' => '達標分享率',
            'ignore_when_ratio_reach_help' => '達標的最小分享率',
            'ban_user_when_counts_reach' => 'H&R 數量上限',
            'ban_user_when_counts_reach_help' => 'H&R 數量達到此值，賬號會被禁用',
        ]
    ],
    'user' => [

    ]
];
