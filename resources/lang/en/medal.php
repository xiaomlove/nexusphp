<?php

return [
    'label' => 'Medal',
    'action_wearing' => 'Wear',
    'admin' => [
        'list' => [
            'page_title' => 'Medal list'
        ]
    ],
    'get_types' => [
        \App\Models\Medal::GET_TYPE_EXCHANGE => 'Exchange',
        \App\Models\Medal::GET_TYPE_GRANT => 'Grant',
    ],
    'fields' => [
        'get_type' => 'Get type',
        'description' => 'Description',
        'image_large' => 'Image',
        'price' => 'Price',
        'duration' => 'Valid after buy (days)',
        'sale_begin_time' => 'Sale begin time',
        'sale_begin_time_help' => 'User can buy after this time, leave blank without restriction',
        'sale_end_time' => 'Sale end time',
        'sale_end_time_help' => 'User can buy before this time, leave blank without restriction',
        'inventory' => 'Inventory',
        'inventory_help' => 'Leave blank without restriction',
        'sale_begin_end_time' => 'Available for sale',
        'users_count' => 'Sold counts',
    ],
    'buy_already' => 'Already buy',
    'buy_btn' => 'Buy',
    'confirm_to_buy' => 'Sure you want to buy?',
    'require_more_bonus' => 'Require more bonus',
    'grant_only' => 'Grant only',
];
