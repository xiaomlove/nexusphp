<?php

$config = require ROOT_PATH . 'config/nexus.php';
$connectionMysql = $config['mysql'];
$connectionMysql['driver'] = 'mysql';
$connectionMysql['charset'] = 'utf8mb4';
$connectionMysql['collation'] = 'utf8mb4_unicode_ci';
\Nexus\Database\NexusDB::bootEloquent($connectionMysql);


