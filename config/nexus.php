<?php

return [

    'timezone' => nexus_env('TIMEZONE', 'PRC'),

    'log_file' => nexus_env('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => nexus_env('LOG_SPLIT', 'daily'),

    'mysql' => [
        'host' => nexus_env('DB_HOST', '127.0.0.1'),
        'port' => nexus_env('DB_PORT', 3306),
        'username' => nexus_env('DB_USERNAME', 'root'),
        'password' => nexus_env('DB_PASSWORD', ''),
        'database' => nexus_env('DB_DATABASE', 'nexusphp'),
    ],

    'redis' => [
        'host' => nexus_env('REDIS_HOST', '127.0.0.1'),
        'port' => nexus_env('REDIS_PORT', 6379),
        'database' => nexus_env('REDIS_DB', 0),
    ],

    'elasticsearch' => [
        'hosts' => [
            [
                'host' => nexus_env('ELASTICSEARCH_HOST','localhost'),
                'port' => nexus_env('ELASTICSEARCH_PORT','9200'),
                'scheme' => nexus_env('ELASTICSEARCH_SCHEME','https'),
                'user' => nexus_env('ELASTICSEARCH_USER','elastic'),
                'pass' => nexus_env('ELASTICSEARCH_PASS',''),
            ]
        ],

        'ssl_verification' => nexus_env('ELASTICSEARCH_SSL_VERIFICATION', ''),
    ]

];
