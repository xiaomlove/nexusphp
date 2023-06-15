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
        'bonus_addition_factor' => '魔力加成系數',
        'bonus_addition' => '魔力加成',
        'bonus_addition_factor_help' => '如：0.01 表示 1% 的加成，留空無加成',
        'gift_fee_factor' => '贈送手續費系數',
        'gift_fee' => '手續費',
        'gift_fee_factor_help' => '贈送給其他用戶時額外收取手續費等於價格乘以此系數',
    ],
    'buy_already' => '已經購買',
    'buy_btn' => '購買',
    'confirm_to_buy' => '確定要購買嗎？',
    'require_more_bonus' => '需要更多魔力值',
    'grant_only' => '僅授予',
    'before_sale_begin_time' => '未到可購買時間',
    'after_sale_end_time' => '已過可購買時間',
    'inventory_empty' => '庫存不足',
    'gift_btn' => '贈送',
    'confirm_to_gift' => '確定要贈送給用戶 ',
    'max_allow_wearing' => '最多允許同時佩戴 :count 個勛章',
    'wearing_status_text' => [
        0 => '未佩戴',
        1 => '已佩戴'
    ],
];
