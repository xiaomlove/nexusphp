<?php

return [

    'hosts' => [
        [
            'host' => env('ELASTICSEARCH_HOST','localhost'),
            'port' => env('ELASTICSEARCH_PORT','9200'),
            'scheme' => env('ELASTICSEARCH_SCHEME','https'),
            'user' => env('ELASTICSEARCH_USER','elastic'),
            'pass' => env('ELASTICSEARCH_PASS',''),
        ]
    ],

    'ssl_verification' => env('ELASTICSEARCH_SSL_VERIFICATION', ''),
];
