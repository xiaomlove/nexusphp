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
        'props' => '道具',
        'class' => '等級',
        'vip_added' => '貴賓資格為通過魔力值獲得',
        'vip_added_help' => '該貴賓資格是否為通過魔力值換取。',
        'vip_until' => '貴賓資格結束時間',
        'vip_until_help' => "時間格式為'年年年年-月月-日日 時時:分分:秒秒'。貴賓資格結束的時間。'貴賓資格為通過魔力值獲得'必須設為'是'此規則才能生效。",
    ],
    'class_names' => [
        \App\Models\User::CLASS_VIP => '貴賓',
        \App\Models\User::CLASS_RETIREE => '養老族',
        \App\Models\User::CLASS_UPLOADER => '發布員',
        \App\Models\User::CLASS_MODERATOR => '總版主',
        \App\Models\User::CLASS_ADMINISTRATOR => '管理員',
        \App\Models\User::CLASS_SYSOP => '維護開發員',
        \App\Models\User::CLASS_STAFF_LEADER => '主管',
    ],
    'change_username_lte_min_interval' => '上次修改時間：:last_change_time，未滿足最小間隔：:interval 天',
    'destroy_by_admin' => '被管理員物理刪除',
    'disable_by_admin' => '被管理员封禁',
    'genders' => [
        \App\Models\User::GENDER_MALE => '男',
        \App\Models\User::GENDER_FEMALE => '女',
        \App\Models\User::GENDER_UNKNOWN => '未知',
    ],
    'grant_props_notification' => [
        'subject' => '獲得道具：:name',
        'body' => ':operator 授予你 :name， 有效期：:duration。',
    ],
    'metas' => [
        'already_valid_forever' => ':meta_key_text 已經永久有效',
    ],
    'edit_notifications' => [
        'change_class' => [
            'promote' => '提升',
            'demote' => '降級',
            'subject' => '等級變化',
            'body' => '你被:action為:new_class，管理員：:operator, 原因：:reason。',
        ],
    ],
    'username_already_exists' => '用戶名：:username 已經存在',
    'username_invalid' => '用戶名：:username 無效',
];
