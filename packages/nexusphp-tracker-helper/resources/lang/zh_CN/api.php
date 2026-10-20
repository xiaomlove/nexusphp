<?php

return [
    'label' => 'Go Tracker API',
    'api' => [
        'user_info' => '获取用户信息',
        'torrent_info' => '获取种子基本信息',
        'promotion_info' => '获取种子促销信息',
        'torrent_peer_list' => '获取种子同伴列表',
        'torrent_peer_count' => '获取种子同伴数量',
        'snatched_info' => '获取 snatched 信息',
        'peer_info' => '获取 peer 信息',
        'peer_ttl' => '获取 peer 有效时间',
        'seed_box_check' => '盒子检测',
    ],
    'api_endpoint' => 'API 接口',
    'submit_request' => '发起请求',
    'request_parameters' => [
        'user_id' => '用户 ID',
        'torrent_id' => '种子 ID',
        'peer_id' => 'Peer ID',
        'ip' => 'IP',
    ],
];
