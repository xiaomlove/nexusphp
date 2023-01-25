<?php

return [
    'label' => '勛章',
    'action_wearing' => '佩戴',
    'admin' => [
        'list' => [
            'page_title' => '勛章列表'
        ]
    ],
    'get_types' => [
        \App\Models\Medal::GET_TYPE_EXCHANGE => '兌換',
        \App\Models\Medal::GET_TYPE_GRANT => '授予',
    ],
    'fields' => [
        'get_type' => '獲取方式',
        'description' => '描述',
        'image_large' => '圖片',
        'price' => '價格',
        'duration' => '購買後有效期(天)',
        'sale_begin_time' => '上架開始時間',
        'sale_begin_time_help' => '此時間之後可以購買，留空不限製',
        'sale_end_time' => '上架結束時間',
        'sale_end_time_help' => '此時間之前可以購買，留空不限製',
        'inventory' => '庫存',
        'inventory_help' => '留空表示無限',
        'sale_begin_end_time' => '可購買時間',
        'users_count' => '已售數量',
    ],
    'buy_already' => '已經購買',
    'buy_btn' => '購買',
    'confirm_to_buy' => '確定要購買嗎？',
    'require_more_bonus' => '需要更多魔力值',
    'grant_only' => '僅授予',
];
