<?php

return [
    'label' => '勋章',
    'action_wearing' => '佩戴',
    'admin' => [
        'list' => [
            'page_title' => '勋章列表'
        ]
    ],
    'get_types' => [
        \App\Models\Medal::GET_TYPE_EXCHANGE => '兑换',
        \App\Models\Medal::GET_TYPE_GRANT => '授予',
    ],
    'fields' => [
        'get_type' => '获取方式',
        'description' => '描述',
        'image_large' => '图片',
        'price' => '价格',
        'duration' => '购买后有效期(天)',
    ],
    'buy_already' => '已经购买',
    'buy_btn' => '购买',
    'confirm_to_buy' => '确定要购买吗？',
    'require_more_bonus' => '需要更多魔力值',
    'grant_only' => '仅授予',
];
