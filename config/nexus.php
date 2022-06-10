<?php

return [

    'timezone' => nexus_env('TIMEZONE', 'PRC'),

    'log_file' => nexus_env('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => nexus_env('LOG_SPLIT', 'daily'),

    'mysql' => [
        'driver' => 'mysql',
        'url' => nexus_env('DATABASE_URL'),
        'host' => nexus_env('DB_HOST', '127.0.0.1'),
        'port' => (int)nexus_env('DB_PORT', 3306),
        'username' => nexus_env('DB_USERNAME', 'root'),
        'password' => nexus_env('DB_PASSWORD', ''),
        'database' => nexus_env('DB_DATABASE', 'nexusphp'),
        'unix_socket' => nexus_env('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            PDO::MYSQL_ATTR_SSL_CA => nexus_env('MYSQL_ATTR_SSL_CA'),
        ]) : [],
    ],

    'redis' => [
        'host' => nexus_env('REDIS_HOST', '127.0.0.1'),
        'port' => (int)nexus_env('REDIS_PORT', 6379),
        'database' => nexus_env('REDIS_DB', 0),
        'password' => nexus_env('REDIS_PASSWORD'),
    ],

    'elasticsearch' => [
        'hosts' => [
            [
                'host' => nexus_env('ELASTICSEARCH_HOST','localhost'),
                'port' => (int)nexus_env('ELASTICSEARCH_PORT','9200'),
                'scheme' => nexus_env('ELASTICSEARCH_SCHEME','https'),
                'user' => nexus_env('ELASTICSEARCH_USER','elastic'),
                'pass' => nexus_env('ELASTICSEARCH_PASS',''),
            ]
        ],

        'ssl_verification' => nexus_env('ELASTICSEARCH_SSL_VERIFICATION', ''),
    ]

];
