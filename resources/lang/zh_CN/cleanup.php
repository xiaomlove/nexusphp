<?php

return [
    'ban_user_with_leech_warning_expired' => '上传警告到期，被系统禁用.',
    'disable_user_unconfirmed' => '超时未确认，被系统封禁.',
    'disable_user_no_transfer_alt_last_access_time' => '封禁非活跃的无流量账号，由最近访问时间断定.',
    'disable_user_no_transfer_alt_register_time' => '封禁非活跃的无流量账号，由注册时间时间断定.',
    'disable_user_not_parked' => '定时封禁未挂起的非活跃账号.',
    'disable_user_parked' => '定时封禁已挂起的非活跃账号.',
    'destroy_disabled_account' => '定时物理删除已封禁账号',
    'alarm_email_subject' => '[:site_name]后台清理任务异常',
    'alarm_email_body' => '当前时间：:now_time, 级别 :level 上次运行时间是：:last_time，已经超过：:elapsed_seconds 秒（:elapsed_seconds_human）没有运行，设置的运行间隔是：:interval 秒（:interval_human），请检查！',
];
