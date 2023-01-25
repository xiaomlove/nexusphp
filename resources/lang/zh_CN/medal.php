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
        'sale_begin_time' => '上架开始时间',
        'sale_begin_time_help' => '此时间之后可以购买，留空不限制',
        'sale_end_time' => '上架结束时间',
        'sale_end_time_help' => '此时间之前可以购买，留空不限制',
        'inventory' => '库存',
        'inventory_help' => '留空表示无限',
        'sale_begin_end_time' => '可购买时间',
        'users_count' => '已售数量',
    ],
    'buy_already' => '已经购买',
    'buy_btn' => '购买',
    'confirm_to_buy' => '确定要购买吗？',
    'require_more_bonus' => '需要更多魔力值',
    'grant_only' => '仅授予',
    'before_sale_begin_time' => '未到可购买时间',
    'after_sale_end_time' => '已过可购买时间',
    'inventory_empty' => '库存不足',
];
