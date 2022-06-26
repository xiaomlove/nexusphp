<?php

return [
    'admin' => [
        'list' => [
            'page_title' => '考核用戶列表'
        ]
    ],
    'status' => [
        \App\Models\ExamUser::STATUS_FINISHED => '已結束',
        \App\Models\ExamUser::STATUS_AVOIDED => '已免除',
        \App\Models\ExamUser::STATUS_NORMAL => '考核中',
    ],
];
