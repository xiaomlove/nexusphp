<?php

return [
    'admin' => [
        'list' => [
            'page_title' => 'Exam users'
        ]
    ],
    'status' => [
        \App\Models\ExamUser::STATUS_FINISHED => 'Finished',
        \App\Models\ExamUser::STATUS_AVOIDED => 'Avoided',
        \App\Models\ExamUser::STATUS_NORMAL => 'Normal',
    ],
    'end_can_not_before_begin' => "End time: :end can't be before begin time: :begin",
];
