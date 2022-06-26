<?php

return [
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
];
