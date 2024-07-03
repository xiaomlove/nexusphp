<?php

return [
    'ban_user_with_leech_warning_expired' => 'Banned by system because of leech warning expired.',
    'disable_user_unconfirmed' => 'Disable by system because of unconfirmed excess deadline.',
    'disable_user_no_transfer_alt_last_access_time' => 'Disable inactive user accounts, no transfer. Alt: last access time.',
    'disable_user_no_transfer_alt_register_time' => 'Disable inactive user accounts, no transfer. Alt: register time.',
    'disable_user_not_parked' => 'Disable inactive user accounts, not parked.',
    'disable_user_parked' => 'Disable inactive user accounts, parked.',
    'destroy_disabled_account' => 'Timed physical deletion of disabled accounts',
    'alarm_email_subject' => '[:site_name] background cleanup task exception',
    'alarm_email_body' => 'Current time: :now_time, level :level, Last run time was: :last_time, it has been more than: :elapsed_seconds seconds(:elapsed_seconds_human) since it was run, the set run interval is: :interval seconds(:interval_human), please check!',
    'alarm_email_subject_for_queue_failed_jobs' => '[:site_name]Asynchronous Task Exception',
    'alarm_email_body_for_queue_failed_jobs' => 'There are a total of :count failed asynchronous jobs since :since, recorded in database table :failed_job_table, please check it!',
];
