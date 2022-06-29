<?php

return [
    'setting' => [
        'nav_text' => '设置',
        'backup' => [
            'tab_header' => '备份',
            'enabled' => '是否启用',
            'enabled_help' => '是否启用备份功能',
            'frequency' => '频率',
            'frequency_help' => '备份频率',
            'hour' => '小时',
            'hour_help' => '在这个点钟数进行备份',
            'minute' => '分钟',
            'minute_help' => "在前面点钟数的这一分钟进行备份。如果频率是按 'hourly'，此值会被忽略",
            'google_drive_client_id' => 'Google Drive client ID',
            'google_drive_client_secret' => 'Google Drive client secret',
            'google_drive_refresh_token' => 'Google Drive refresh token',
            'google_drive_folder_id' => 'Google Drive folder ID',
            'via_ftp' => '通过 FTP 保存',
            'via_ftp_help' => '是否通过 FTP 保存。如果通过，把配置信息添加到 .env 文件，参考 <a href="https://laravel.com/docs/master/filesystem#ftp-driver-configuration">Laravel 文档</a>',
            'via_sftp' => '通过 SFTP 保存',
            'via_sftp_help' => '是否通过 SFTP 保存。如果通过，把配置信息添加到 .env 文件，参考 <a href="https://laravel.com/docs/master/filesystem#sftp-driver-configuration">Laravel 文档</a>',
        ],
        'hr' => [
            'tab_header' => 'H&R',
            'mode' => '模式',
            'inspect_time' => '考察时长',
            'inspect_time_help' => '考察时长自下载完成后开始计算，单位：小时',
            'seed_time_minimum' => '达标做种时长',
            'seed_time_minimum_help' => '达标的最短做种时长，单位：小时，必须小于考察时长',
            'ignore_when_ratio_reach' => '达标分享率',
            'ignore_when_ratio_reach_help' => '达标的最小分享率',
            'ban_user_when_counts_reach' => 'H&R 数量上限',
            'ban_user_when_counts_reach_help' => 'H&R 数量达到此值，账号会被禁用',
        ]
    ],
    'user' => [

    ]
];
