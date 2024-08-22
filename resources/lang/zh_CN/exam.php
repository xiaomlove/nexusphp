<?php

return [
    'label' => '考核',
    'name' => '名称',
    'index' => '指标',
    'time_range' => '时间',
    'index_text_' . \App\Models\Exam::INDEX_UPLOADED => '上传增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_TIME_AVERAGE => '平均做种时间',
    'index_text_' . \App\Models\Exam::INDEX_DOWNLOADED => '下载增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_BONUS => '魔力增量',
    'index_text_' . \App\Models\Exam::INDEX_SEED_POINTS => '做种积分增量',
    'index_text_' . \App\Models\Exam::INDEX_UPLOAD_TORRENT_COUNT => '发种增量',
    'filters' => [
        \App\Models\Exam::FILTER_USER_CLASS => '用户等级',
        \App\Models\Exam::FILTER_USER_REGISTER_TIME_RANGE => '注册时间范围',
        \App\Models\Exam::FILTER_USER_DONATE => '是否捐赠',
        \App\Models\Exam::FILTER_USER_REGISTER_DAYS_RANGE => '注册天数范围',
    ],
    'require_value' => '要求',
    'current_value' => '当前',
    'result' => '结果',
    'result_pass_for_exam' => '通过！',
    'result_pass_for_task' => '完成！',
    'result_not_pass_for_exam' => '<span style="color: red">未通过！</span>',
    'result_not_pass_for_task' => '<span style="color: red">未完成！</span>',
    'checkout_pass_message_subject_for_exam' => '考核通过！',
    'checkout_pass_message_content_for_exam' => '恭喜！你在规定时间内（:begin ~ :end）顺利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject_for_exam' => '考核未通过，账号被禁用！',
    'checkout_not_pass_message_content_for_exam' => '你在规定时间内（:begin ~ :end）未完成考核：:exam_name，账号已被禁用。',

    'checkout_pass_message_subject_for_task' => '任务完成！',
    'checkout_pass_message_content_for_task' => '恭喜！你在规定时间内（:begin ~ :end）顺利完成了任务：:exam_name，获得奖励魔力：:success_reward_bonus',
    'checkout_not_pass_message_subject_for_task' => '任务未完成！',
    'checkout_not_pass_message_content_for_task' => '你在规定时间内（:begin ~ :end）未完成任务：:exam_name，扣除魔力：:fail_deduct_bonus。',

    'ban_log_reason' => '未完成考核：:exam_name(:begin ~ :end)',
    'ban_user_modcomment' => '未完成考核: :exam_name(:begin ~ :end), 被系统禁用.',
    'deduct_bonus_comment' => '未完成任务: :exam_name(:begin ~ :end), 扣除魔力：:fail_deduct_bonus.',
    'reward_bonus_comment' => '完成任务: :exam_name(:begin ~ :end), 奖励魔力：:success_reward_bonus.',

    'admin' => [
        'list' => [
            'page_title' => '考核列表'
        ]
    ],
    'recurring' => '周期性',
    'recurring_daily' => '每天一次',
    'recurring_weekly' => '每周一次',
    'recurring_monthly' => '每月一次',
    'recurring_help' => '如果指定为周期性，考核开始时间为当前周期的开始时间，结束时间为当前周期的结束时间，这里说的都是自然日/周/月。对于类型为考核的，每个周期结束后，如果用户仍然满足筛选条件，会自动为用户分配下个周期的考核。',

    'time_condition_invalid' => '时间参数不合理，有且只有三项之一：开始时间+结束时间/时长/周期性',

    'type_exam' => '考核',
    'type_task' => '任务',
    'type' => '类型',
    'type_help' => '考核是常规的考核，不通过会被封禁账号。任务可根据完成与否设置奖励魔力或扣除魔力',

    'fail_deduct_bonus' => '失败扣除魔力',
    'success_reward_bonus' => '完成奖励魔力',

    'action_claim_task' => '领取',
    'confirm_to_claim' => '确定要认领吗？',
    'claim_by_yourself_only' => '只能自己认领!',
    'not_match_target_user' => '你不是匹配的目标用户！',
    'has_other_on_the_way' => '有其他进行中的:type_text',
    'claimed_already' => '已经认领',
    'not_between_begin_end_time' => '不在开始结束时间范围内',
    'reach_max_user_count' => '认领人数已达上限',
    'claimed_user_count' => '认领人数',
    'max_user_count' => '最多认领人数(0表示无限制)',
    'background_color' => '信息框背景色',
];
