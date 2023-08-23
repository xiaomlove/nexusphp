<?php

return [
    'invalid_inviter' => '非法邀請者！此邀請碼已被禁用！',
    'fields' => [
        'inviter' => '發邀者',
        'invitee' => '接收郵箱',
        'time_invited' => '發邀時間',
        'valid' => '是否有效',
        'invitee_register_uid' => '註冊用戶 UID',
        'invitee_register_email' => '註冊用戶郵箱',
        'invitee_register_username' => '註冊用戶名',
        'expired_at' => 'hash 過期時間',
        'time_invited_begin' => '發邀時間大於',
        'time_invited_end' => '發邀時間小於',
    ],
    'send_deny_reasons' => [
        'invite_system_closed' => '邀請系統已關閉',
        'no_permission' => ':class 或以上等級才可以發送邀請',
        'invite_not_enough' => '邀請數量不足',
    ],
    'send_allow_text' => '邀請其他人',
    'pre_register_username' => '預註冊用戶名',
    'pre_register_username_help' => '用戶使用此邀請碼註冊時用戶名和郵箱將不能更改',
    'require_pre_register_username' => '預註冊用戶名不能為空',
];
