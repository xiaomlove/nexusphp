<?php

return [
    'name' => 'Go-Tracker',
    'internal_host' => 'Tracker 內網地址',
    'internal_host_help' => '完整的地址，包含協議+域名+端口，如：http://127.0.0.1:7777',
    'is_frontend_use_tracker_api' => '前端是否使用 Tracker 接口',
    'is_frontend_use_tracker_api_help' => '若使用，種子列表頁的做種/下載數，種子詳情頁同伴列表，會使用 Tracker 接口獲取實時數據。否則展示的是數據庫中稍微滯後的數據',

    'is_test_mode' => '是否測試模式',
    'is_test_mode_help' => '需要在 Nginx 做流量鏡像配置，如果你不清楚是什麽，不要啟用',
    'test_mode_uid' => '測試用戶 UID',
    'test_mode_uid_help' => '一行一個或英文逗號分割。測試模式下，位於這個名單內的用戶數據同步到數據庫，其他用戶數據不做同步。非測試模式下這裏數據忽略，全部用戶數據均定時同步至數據庫',

    'alarm_subject' => 'Go-Tracker 服務異常',
    'alarm_content' => "第 :alarm_count 次告警!<br/>狀態接口：:status_api 沒有正常返回，請檢查！<br/>最近狀態如下:<br/> :latest_status",
];
