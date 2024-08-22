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

    'result_pass_for_exam' => '通過！',
    'result_pass_for_task' => '完成！',
    'result_not_pass_for_exam' => '<span style="color: red">未通過！</span>',
    'result_not_pass_for_task' => '<span style="color: red">未完成！</span>',
    'checkout_pass_message_subject_for_exam' => '考核通過！',
    'checkout_pass_message_content_for_exam' => '恭喜！你在規定時間內（:begin ~ :end）順利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject_for_exam' => '考核未通過，賬號被禁用！',
    'checkout_not_pass_message_content_for_exam' => '你在規定時間內（:begin ~ :end）未完成考核：:exam_name，賬號已被禁用。',

    'checkout_pass_message_subject_for_task' => '任務完成！',
    'checkout_pass_message_content_for_task' => '恭喜！你在規定時間內（:begin ~ :end）順利完成了任務：:exam_name，獲得獎勵魔力：:success_reward_bonus',
    'checkout_not_pass_message_subject_for_task' => '任務未完成！',
    'checkout_not_pass_message_content_for_task' => '你在規定時間內（:begin ~ :end）未完成任務：:exam_name，扣除魔力：:fail_deduct_bonus。',

    'ban_log_reason' => '未完成考核：:exam_name(:begin ~ :end)',
    'ban_user_modcomment' => '未完成考核: :exam_name(:begin ~ :end), 被系統禁用.',
    'admin' => [
        'list' => [
            'page_title' => '考核列表'
        ]
    ],
    'recurring' => '周期性',
    'recurring_daily' => '每天一次',
    'recurring_weekly' => '每周一次',
    'recurring_monthly' => '每月一次',
    'recurring_help' => '如果指定為周期性，考核開始時間為當前周期的開始時間，結束時間為當前周期的結束時間，這裏說的都是自然日/周/月。對於類型為考核的，每個周期結束後，如果用戶仍然滿足篩選條件，會自動為用戶分配下個周期的考核。',

    'time_condition_invalid' => '時間參數不合理，有且只有三項之一：開始時間+結束時間/時長/周期性',

    'type_exam' => '考核',
    'type_task' => '任务',
    'type' => '类型',
    'type_help' => '考核是常规的考核，不通过会被封禁账号。任务可根据完成与否设置奖励魔力或扣除魔力',

    'fail_deduct_bonus' => '失败扣除魔力',
    'success_reward_bonus' => '完成奖励魔力',

    'action_claim_task' => '領取',
    'confirm_to_claim' => '確定要認領嗎？',
    'claim_by_yourself_only' => '只能自己認領!',
    'not_match_target_user' => '你不是匹配的目標用戶！',
    'has_other_on_the_way' => '有其他進行中的:type_text',
    'claimed_already' => '已經認領',
    'not_between_begin_end_time' => '不在開始結束時間範圍內',
    'reach_max_user_count' => '認領人數已達上限',
    'claimed_user_count' => '認領人數',
    'max_user_count' => '最多認領人數(0表示無限製)',
    'background_color' => '信息框背景色',
];
