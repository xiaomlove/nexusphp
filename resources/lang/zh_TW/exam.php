<?php

return [
    'name' => '考核名稱',
    'index' => '考核指標',
    'time_range' => '考核時間',
    'index_text_' . \App\Models\Exam::INDEX_UPLOADED => '上傳量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_TIME_AVERAGE => '平均做種時間',
    'index_text_' . \App\Models\Exam::INDEX_DOWNLOADED => '下載量',
    'index_text_' . \App\Models\Exam::INDEX_BONUS => '魔力',
    'require_value' => '要求',
    'current_value' => '當前',
    'result' => '結果',
    'result_pass' => '通過！',
    'result_not_pass' => '<bold color="red">未通過！</bold>',
    'checkout_pass_message_subject' => '考核通過！',
    'checkout_pass_message_content' => '恭喜！你在規定時間內（:begin ~ :end）順利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject' => '考核未通過，賬號被禁用！',
    'checkout_not_pass_message_content' => '你在規定時間內（:begin ~ :end）未完成考核：:exam_name，賬號已被禁用。',
];
