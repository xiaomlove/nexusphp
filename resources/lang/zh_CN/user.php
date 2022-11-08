<?php

return [
    'edit_ban_reason' => '被管理人员禁用',
    'deleted_username' => '无此账号',
    'admin' => [
        'list' => [
            'page_title' => '用户列表'
        ]
    ],
    'labels' => [
        'seedbonus' => '魔力',
        'seed_points' => '做种积分',
        'uploaded' => '上传量',
        'downloaded' => '下载量',
        'invites' => '邀请',
        'attendance_card' => '补签卡',
        'props' => '道具',
    ],
    'class_names' => [
        \App\Models\User::CLASS_VIP => '贵宾',
        \App\Models\User::CLASS_RETIREE => '养老族',
        \App\Models\User::CLASS_UPLOADER => '发布员',
        \App\Models\User::CLASS_MODERATOR => '总版主',
        \App\Models\User::CLASS_ADMINISTRATOR => '管理员',
        \App\Models\User::CLASS_SYSOP => '维护开发员',
        \App\Models\User::CLASS_STAFF_LEADER => '主管',
    ],
    'change_username_lte_min_interval' => '上次修改时间：:last_change_time，未满足最小间隔：:interval 天',
    'destroy_by_admin' => '被管理员物理删除',
    'disable_by_admin' => '被管理員封禁',
    'genders' => [
        \App\Models\User::GENDER_MALE => '男',
        \App\Models\User::GENDER_FEMALE => '女',
        \App\Models\User::GENDER_UNKNOWN => '未知',
    ],
    'grant_props_notification' => [
        'subject' => '获得道具：:name',
        'body' => ':operator 授予你 :name， 有效期：:duration。',
    ],
];
