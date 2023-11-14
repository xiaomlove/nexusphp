<?php

return [
    'admin' => [
        'list' => [
            'page_title' => '考核用户列表'
        ]
    ],
    'status' => [
        \App\Models\ExamUser::STATUS_FINISHED => '已结束',
        \App\Models\ExamUser::STATUS_AVOIDED => '已免除',
        \App\Models\ExamUser::STATUS_NORMAL => '考核中',
    ],
    'end_can_not_before_begin' => '结束时间：:end 不能在开始时间：:begin 之前',
    'status_not_allow_update_end' => '当前状态不为：:status_text，无法变更结束时间',
];
