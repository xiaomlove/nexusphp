<?php

return [

    'mysql' => [
        'host' => env('MYSQL_HOST', '127.0.0.1'),
        'port' => env('MYSQL_PORT', 3306),
        'username' => env('MYSQL_USERNAME', 'root'),
        'password' => env('MYSQL_PASSWORD', ''),
        'database' => env('MYSQL_DATABASE', 'nexusphp'),
    ],

    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DATABASE', 0),
    ],

];