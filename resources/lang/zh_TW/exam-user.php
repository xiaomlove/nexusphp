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
    'end_can_not_before_begin' => '結束時間：:end 不能在開始時間：:begin 之前',
    'status_not_allow_update_end' => '當前狀態不為：:status_text，無法變更結束時間',
];
