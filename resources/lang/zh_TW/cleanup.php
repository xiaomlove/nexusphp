<?php

return [
    'ban_user_with_leech_warning_expired' => '上傳警告到期，被系統禁用.',
    'disable_user_unconfirmed' => '超時未確認，被系統封禁.',
    'disable_user_no_transfer_alt_last_access_time' => '封禁非活躍的無流量賬號，由最近訪問時間斷定.',
    'disable_user_no_transfer_alt_register_time' => '封禁非活躍的無流量賬號，由註冊時間時間斷定.',
    'disable_user_not_parked' => '定時封禁未掛起的非活躍賬號.',
    'disable_user_parked' => '定時封禁已掛起的非活躍賬號.',
    'destroy_disabled_account' => '定時物理刪除已封禁賬號',
    'alarm_email_subject' => '[:site_name]後臺清理任務異常',
    'alarm_email_body' => '當前時間：:now_time, 級別 :level 上次運行時間是：:last_time，已經超過：:elapsed_seconds 秒（:elapsed_seconds_human）沒有運行，設置的運行間隔是：:interval 秒（:interval_human），請檢查！',
    'alarm_email_subject_for_queue_failed_jobs' => '[:site_name]異步任務異常',
    'alarm_email_body_for_queue_failed_jobs' => '自 :since 起共有 :count 條失敗的異步任務，記錄在數據表 :failed_job_table 中，請檢查！',
];
