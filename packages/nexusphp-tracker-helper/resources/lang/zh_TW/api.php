<?php

return [
    'label' => 'Go Tracker API',
    'api' => [
        'user_info' => '獲取用戶信息',
        'torrent_info' => '獲取種子基本信息',
        'promotion_info' => '獲取種子促銷信息',
        'torrent_peer_list' => '獲取種子同伴列表',
        'torrent_peer_count' => '獲取種子同伴數量',
        'snatched_info' => '獲取 snatched 信息',
        'peer_info' => '獲取 peer 信息',
        'peer_ttl' => '獲取 peer 有效時間',
        'seed_box_check' => '盒子檢測',
    ],
    'api_endpoint' => 'API 接口',
    'submit_request' => '發起請求',
    'request_parameters' => [
        'user_id' => '用戶 ID',
        'torrent_id' => '種子 ID',
        'peer_id' => 'Peer ID',
        'ip' => 'IP',
    ],
];
