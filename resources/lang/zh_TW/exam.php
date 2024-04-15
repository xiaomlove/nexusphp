<?php

return [
    'label' => '考核',
    'name' => '考核名稱',
    'index' => '考核指標',
    'time_range' => '考核時間',
    'index_text_' . \App\Models\Exam::INDEX_UPLOADED => '上傳增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_TIME_AVERAGE => '平均做種時間',
    'index_text_' . \App\Models\Exam::INDEX_DOWNLOADED => '下載增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_BONUS => '魔力增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_POINTS => '做種積分增量',
    'index_text_' . \App\Models\Exam::INDEX_UPLOAD_TORRENT_COUNT => '發種增量',
    'filters' => [
        \App\Models\Exam::FILTER_USER_CLASS => '用戶等級',
        \App\Models\Exam::FILTER_USER_REGISTER_TIME_RANGE => '註冊時間範圍',
        \App\Models\Exam::FILTER_USER_DONATE => '是否捐贈',
        \App\Models\Exam::FILTER_USER_REGISTER_DAYS_RANGE => '註冊天數範圍',
    ],
    'require_value' => '要求',
    'current_value' => '當前',
    'result' => '結果',
    'result_pass' => '通過！',
    'result_not_pass' => '<span style="color: red">未通過！</span>',
    'checkout_pass_message_subject' => '考核通過！',
    'checkout_pass_message_content' => '恭喜！你在規定時間內（:begin ~ :end）順利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject' => '考核未通過，賬號被禁用！',
    'checkout_not_pass_message_content' => '你在規定時間內（:begin ~ :end）未完成考核：:exam_name，賬號已被禁用。',
    'ban_log_reason' => '未完成考核：:exam_name(:begin ~ :end)',
    'ban_user_modcomment' => '未完成考核: :exam_name(:begin ~ :end), 被系統禁用.',
    'admin' => [
        'list' => [
            'page_title' => '考核列表'
        ]
    ],
    'recurring' => '周期性',
    'recurring_weekly' => '每周一次',
    'recurring_monthly' => '每月一次',
    'recurring_help' => '如果指定為周期性，考核開始時間為當前周期的開始時間，結束時間為當前周期的結束時間，這裏說的都是自然周/月。每個周期結束後，如果用戶仍然滿足篩選條件，會自動為用戶分配下個周期的任務。',

    'time_condition_invalid' => '時間參數不合理，有且只有三項之一：開始時間+結束時間/時長/周期性',
];
