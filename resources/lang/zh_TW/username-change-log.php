<?php

return [
    'change_type' => [
        \App\Models\UsernameChangeLog::CHANGE_TYPE_USER => '用戶',
        \App\Models\UsernameChangeLog::CHANGE_TYPE_ADMIN => '管理員',
    ],
    'labels' => [
        'username_old' => '舊用戶名',
        'username_new' => '新用戶名',
        'change_type' => '修改類型',
    ],
];
