<?php

return [
    'edit_ban_reason' => 'Disable by administrator',
    'deleted_username' => 'user not exists',
    'admin' => [
        'list' => [
            'page_title' => 'User list'
        ]
    ],
    'labels' => [
        'seedbonus' => 'Bonus',
        'seed_points' => 'Seed points',
        'uploaded' => 'Uploaded',
        'downloaded' => 'Downloaded',
        'invites' => 'Invites',
        'attendance_card' => 'Attend card',
    ],
    'class_name' => [
        \App\Models\User::CLASS_VIP => 'Vip',
        \App\Models\User::CLASS_RETIREE => 'Retiree',
        \App\Models\User::CLASS_UPLOADER => 'Uploader',
        \App\Models\User::CLASS_MODERATOR => 'Moderator',
        \App\Models\User::CLASS_ADMINISTRATOR => 'Administrator',
        \App\Models\User::CLASS_SYSOP => 'Sysop',
        \App\Models\User::CLASS_STAFF_LEADER => 'Staff Leader',
    ],
];
