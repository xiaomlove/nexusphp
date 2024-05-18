<?php

return [
    'label' => '考核',
    'name' => '考核名称',
    'index' => '考核指标',
    'time_range' => '考核时间',
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
    'result_pass' => '通过！',
    'result_not_pass' => '<span style="color: red">未通过！</span>',
    'checkout_pass_message_subject' => '考核通过！',
    'checkout_pass_message_content' => '恭喜！你在规定时间内（:begin ~ :end）顺利完成了考核：:exam_name。',
    'checkout_not_pass_message_subject' => '考核未通过，账号被禁用！',
    'checkout_not_pass_message_content' => '你在规定时间内（:begin ~ :end）未完成考核：:exam_name，账号已被禁用。',
    'ban_log_reason' => '未完成考核：:exam_name(:begin ~ :end)',
    'ban_user_modcomment' => '未完成考核: :exam_name(:begin ~ :end), 被系统禁用.',
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

    'fail_deduct_bonus' => '任务失败扣除魔力',
    'success_reward_bonus' => '任务完成奖励魔力',

];
