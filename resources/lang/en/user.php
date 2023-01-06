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
        'props' => 'Props',
    ],
    'class_names' => [
        \App\Models\User::CLASS_VIP => 'Vip',
        \App\Models\User::CLASS_RETIREE => 'Retiree',
        \App\Models\User::CLASS_UPLOADER => 'Uploader',
        \App\Models\User::CLASS_MODERATOR => 'Moderator',
        \App\Models\User::CLASS_ADMINISTRATOR => 'Administrator',
        \App\Models\User::CLASS_SYSOP => 'Sysop',
        \App\Models\User::CLASS_STAFF_LEADER => 'Staff Leader',
    ],
    'change_username_lte_min_interval' => 'Last change time: :last_change_time, unmet minimum interval: :interval days',
    'destroy_by_admin' => 'Physical delete by administrator',
    'disable_by_admin' => 'Disable by administrator',
    'genders' => [
        \App\Models\User::GENDER_MALE => 'Male',
        \App\Models\User::GENDER_FEMALE => 'Female',
        \App\Models\User::GENDER_UNKNOWN => 'Unknown',
    ],
    'grant_props_notification' => [
        'subject' => 'Get Props：:name',
        'body' => ':operator Grant you :name, Validity period: :duration.',
    ],
    'metas' => [
        'already_valid_forever' => ':meta_key_text 已經永久有效',
    ],
];
