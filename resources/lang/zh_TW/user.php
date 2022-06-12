<?php

return [
    'edit_ban_reason' => '被管理人員禁用',
    'deleted_username' => '無此賬號',
    'admin' => [
        'list' => [
            'page_title' => '用戶列表'
        ]
    ],
    'labels' => [
        'seedbonus' => '魔力',
        'seed_points' => '做種積分',
        'uploaded' => '上傳量',
        'downloaded' => '下載量',
        'invites' => '邀請',
        'attendance_card' => '補簽卡',
    ],
    'class_name' => [
        \App\Models\User::CLASS_VIP => '貴賓',
        \App\Models\User::CLASS_RETIREE => '養老族',
        \App\Models\User::CLASS_UPLOADER => '發布員',
        \App\Models\User::CLASS_MODERATOR => '總版主',
        \App\Models\User::CLASS_ADMINISTRATOR => '管理員',
        \App\Models\User::CLASS_SYSOP => '維護開發員',
        \App\Models\User::CLASS_STAFF_LEADER => '主管',
    ],
];
