<?php

return [
    'name' => '考核名称',
    'index' => '考核指标',
    'time_range' => '考核时间',
    'index_text_' . \App\Models\Exam::INDEX_UPLOADED => '上传量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_TIME_AVERAGE => '平均做种时间',
    'index_text_' . \App\Models\Exam::INDEX_DOWNLOADED => '下载量',
    'index_text_' . \App\Models\Exam::INDEX_BONUS => '魔力',
    'require_value' => '要求',
    'current_value' => '当前',
    'result' => '结果',
    'result_pass' => '通过！',
    'result_not_pass' => '<bold color="red">未通过！</bold>',
    'checkout_pass_message_subject' => '考核通过！',
    'checkout_pass_message_content' => '恭喜！你在规定时间内（:begin ~ :end）顺利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject' => '考核未通过，账号被禁用！',
    'checkout_not_pass_message_content' => '你在规定时间内（:begin ~ :end）未完成考核：:exam_name，账号已被禁用。',
];
