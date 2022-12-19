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
    ],
    'buy_already' => 'Already buy',
    'buy_btn' => 'Buy',
    'confirm_to_buy' => 'Sure you want to buy?',
    'require_more_bonus' => 'Require more bonus',
];
