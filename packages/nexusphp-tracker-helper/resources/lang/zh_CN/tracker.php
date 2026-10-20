<?php

return [
    'name' => 'Go-Tracker',
    'internal_host' => 'Tracker 内网地址',
    'internal_host_help' => '完整的地址，包含协议+域名+端口，如：http://127.0.0.1:7777',
    'is_frontend_use_tracker_api' => '前端是否使用 Tracker 接口',
    'is_frontend_use_tracker_api_help' => '若使用，种子列表页的做种/下载数，种子详情页同伴列表，会使用 Tracker 接口获取实时数据。否则展示的是数据库中稍微滞后的数据',

    'is_test_mode' => '是否测试模式',
    'is_test_mode_help' => '需要在 Nginx 做流量镜像配置，如果你不清楚是什么，不要启用',
    'test_mode_uid' => '测试用户 UID',
    'test_mode_uid_help' => '一行一个或英文逗号分割。测试模式下，位于这个名单内的用户数据同步到数据库，其他用户数据不做同步。非测试模式下这里数据忽略，全部用户数据均定时同步至数据库',

    'alarm_subject' => 'Go-Tracker 服务异常',
    'alarm_content' => "第 :alarm_count 次告警!<br/>状态接口：:status_api 没有正常返回，请检查！<br/>最近状态如下:<br/> :latest_status",
    'token' => 'Token',
    'token_help' => '过期后接口不可用，并且可能导致应用退出',

];
