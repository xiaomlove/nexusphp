<?php

return [
    'change_type' => [
        \App\Models\UsernameChangeLog::CHANGE_TYPE_USER => '用户',
        \App\Models\UsernameChangeLog::CHANGE_TYPE_ADMIN => '管理员',
    ],
    'labels' => [
        'username_old' => '旧用户名',
        'username_new' => '新用户名',
        'change_type' => '修改类型',
    ],
];
