<?php

return [
    'status_' . \App\Models\HitAndRun::STATUS_INSPECTING => '考察中',
    'status_' . \App\Models\HitAndRun::STATUS_REACHED => '已達標',
    'status_' . \App\Models\HitAndRun::STATUS_UNREACHED => '未達標',
    'status_' . \App\Models\HitAndRun::STATUS_PARDONED => '已免罪',

    'mode_' . \App\Models\HitAndRun::MODE_DISABLED => '停用',
    'mode_' . \App\Models\HitAndRun::MODE_MANUAL => '手動',
    'mode_' . \App\Models\HitAndRun::MODE_GLOBAL => '全局',

    'reached_by_seed_time_comment' => '截止：:now，做種時間: :seed_time Hour(s) 已達標 :seed_time_minimum Hour(s)',
    'reached_by_share_ratio_comment' => "截止：:now \n做種時間: :seed_time Hour(s) 未達標 :seed_time_minimum Hour(s) \n分享率: :share_ratio 達忽略標準：:ignore_when_ratio_reach",
    'reached_by_special_user_class_comment' => "你是：:user_class_text 或捐贈用戶，無視此 H&R",
    'reached_message_subject' => 'H&R(ID: :hit_and_run_id) 已達標！',
    'reached_message_content' => '你於 :completed_at 下載完成的種子：:torrent_name(ID: :torrent_id) H&R 已達標，恭喜！',

    'unreached_comment' => "截止：:now \n做種時間： :seed_time Hour(s) 未達要求：:seed_time_minimum Hour(s) \n分享率：:share_ratio 亦未達忽略標準：:ignore_when_ratio_reach",
    'unreached_message_subject' => 'H&R(ID: :hit_and_run_id) 未達標!',
    'unreached_message_content' => '你於 :completed_at 下載完成的種子：:torrent_name(ID: :torrent_id) H&R 未達標！累計一定數量賬號將會被禁用，請註意。',

    'unreached_disable_comment' => 'H&R 數量達上限被系統禁用。',
    'unreached_disable_message_content' => '由於累計 H&R 數量已達系統上限：:ban_user_when_counts_reach，你的賬號已被禁用。',

    'bonus_cancel_comment' => '花費 :bonus 魔力進行了消除',
    'remove_confirm_msg' => '消除一個 H&R 需要扣除 :bonus 魔力，確定嗎？',
];
