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
        'class' => '等级',
        'vip_added' => '贵宾资格为通过魔力值获得',
        'vip_added_help' => '该贵宾资格是否为通过魔力值换取。',
        'vip_until' => '贵宾资格结束时间',
        'vip_until_help' => "时间格式为'年年年年-月月-日日 时时:分分:秒秒'。贵宾资格结束的时间。'贵宾资格为通过魔力值获得'必须设为'是'此规则才能生效。",
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
    'metas' => [
        'already_valid_forever' => ':meta_key_text already valid forever',
    ],
    'edit_notifications' => [
        'change_class' => [
            'promote' => '提升',
            'demote' => '降级',
            'subject' => '等级变化',
            'body' => '你被:action为:new_class，管理员：:operator, 原因：:reason。',
        ],
    ],
    'username_already_exists' => '用户名：:username 已经存在',
    'username_invalid' => '用户名：:username 无效',
];
