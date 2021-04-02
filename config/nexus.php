<?php

return [

    'timezone' => nexus_env('TIMEZONE', 'PRC'),

    'log_file' => nexus_env('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => nexus_env('LOG_SPLIT', 'daily'),

    'use_cron_trigger_cleanup' => nexus_env('USE_CRON_TRIGGER_CLEANUP', false),

    'mysql' => [
        'host' => nexus_env('MYSQL_HOST', '127.0.0.1'),
        'port' => nexus_env('MYSQL_PORT', 3306),
        'username' => nexus_env('MYSQL_USERNAME', 'root'),
        'password' => nexus_env('MYSQL_PASSWORD', ''),
        'database' => nexus_env('MYSQL_DATABASE', 'nexusphp'),
    ],

    'redis' => [
        'host' => nexus_env('REDIS_HOST', '127.0.0.1'),
        'port' => nexus_env('REDIS_PORT', 6379),
        'database' => nexus_env('REDIS_DATABASE', 0),
    ],

];
