<?php

return [
    'status_' . \App\Models\HitAndRun::STATUS_INSPECTING => '考察中',
    'status_' . \App\Models\HitAndRun::STATUS_REACHED => '已达标',
    'status_' . \App\Models\HitAndRun::STATUS_UNREACHED => '未达标',
    'status_' . \App\Models\HitAndRun::STATUS_PARDONED => '已免罪',

    'mode_' . \App\Models\HitAndRun::MODE_DISABLED => '停用',
    'mode_' . \App\Models\HitAndRun::MODE_MANUAL => '手动',
    'mode_' . \App\Models\HitAndRun::MODE_GLOBAL => '全局',

    'reached_by_seed_time_comment' => '截止：:now，做种时间: :seed_time Hour(s) 已达标 :seed_time_minimum Hour(s)',
    'reached_by_share_ratio_comment' => "截止：:now \n做种时间: :seed_time Hour(s) 未达标 :seed_time_minimum Hour(s) \n分享率: :share_ratio 达忽略标准：:ignore_when_ratio_reach",
    'reached_by_special_user_class_comment' => "你是：:user_class_text 或捐赠用户，无视此 H&R",
    'reached_message_subject' => 'H&R(ID: :hit_and_run_id) 已达标！',
    'reached_message_content' => '你于 :completed_at 下载完成的种子：:torrent_name(ID: :torrent_id) H&R 已达标，恭喜！',

    'unreached_comment' => "截止：:now \n做种时间： :seed_time Hour(s) 未达要求：:seed_time_minimum Hour(s) \n分享率：:share_ratio 亦未达忽略标准：:ignore_when_ratio_reach",
    'unreached_message_subject' => 'H&R(ID: :hit_and_run_id) 未达标!',
    'unreached_message_content' => '你于 :completed_at 下载完成的种子：:torrent_name(ID: :torrent_id) H&R 未达标！累计一定数量账号将会被禁用，请注意。',

    'unreached_disable_comment' => 'H&R 数量达上限被系统禁用。',
    'unreached_disable_message_content' => '由于累计 H&R 数量已达系统上限：:ban_user_when_counts_reach，你的账号已被禁用。',

    'bonus_cancel_comment' => '花费 :bonus 魔力进行了消除',
    'remove_confirm_msg' => '消除一个 H&R 需要扣除 :bonus 魔力，确定吗？',
];
