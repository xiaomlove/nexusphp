<?php

return [
    'change_type' => [
        \App\Models\UsernameChangeLog::CHANGE_TYPE_USER => 'User',
        \App\Models\UsernameChangeLog::CHANGE_TYPE_ADMIN => 'Administrator',
    ],
    'labels' => [
        'username_old' => 'Old username',
        'username_new' => 'New username',
        'change_type' => 'Change type',
    ],
];
