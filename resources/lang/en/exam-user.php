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
];
