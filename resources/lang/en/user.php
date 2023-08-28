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
        'class' => 'Class',
        'vip_added' => 'VIP status is obtained by bonus',
        'vip_added_help' => 'Is the VIP status redeemed by bonus.',
        'vip_until' => 'VIP status end time',
        'vip_until_help' => "The time format is 'Year-Year-Month-Day Hour:Minute:Second The time when the VIP status ends.' VIP status is obtained by bonus' must be set to 'Yes' for this rule to take effect.",
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
        'already_valid_forever' => ':meta_key_text already valid forever',
    ],
    'edit_notifications' => [
        'change_class' => [
            'promote' => 'Promote',
            'demote' => 'Demote',
            'subject' => 'Class changed',
            'body' => 'You had been :action to :new_class, administrator: :operator, reason: :reason.',
        ],
    ],
    'username_already_exists' => 'Username::username already exists',
    'username_invalid' => 'Username::username invalid',
];
