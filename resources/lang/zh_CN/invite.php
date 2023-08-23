<?php

return [
    'invalid_inviter' => '非法邀请者！此邀请码已被禁用！',
    'fields' => [
        'inviter' => '发邀者',
        'invitee' => '接收邮箱',
        'time_invited' => '发邀时间',
        'valid' => '是否有效',
        'invitee_register_uid' => '注册用户 UID',
        'invitee_register_email' => '注册用户邮箱',
        'invitee_register_username' => '注册用户名',
        'expired_at' => 'hash 过期时间',
        'time_invited_begin' => '发邀时间大于',
        'time_invited_end' => '发邀时间小于',
    ],
    'send_deny_reasons' => [
        'invite_system_closed' => '邀请系统已关闭',
        'no_permission' => ':class 或以上等级才可以发送邀请',
        'invite_not_enough' => '邀请数量不足',
    ],
    'send_allow_text' => '邀请其他人',
    'pre_register_username' => '预注册用户名',
    'pre_register_username_help' => '用户使用此邀请码注册时用户名和邮箱将不能更改',
    'require_pre_register_username' => '预注册用户名不能为空',
];
