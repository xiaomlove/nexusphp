<?php

use \Illuminate\Database\Capsule\Manager as Capsule;

$config = require ROOT_PATH . 'config/nexus.php';
$connectionMysql = $config['mysql'];
$connectionMysql['driver'] = 'mysql';
$connectionMysql['charset'] = 'utf8mb4';
$connectionMysql['collation'] = 'utf8mb4_unicode_ci';
$capsule = new Capsule;
$connectionName = \Nexus\Database\DB::ELOQUENT_CONNECTION_NAME;
$capsule->addConnection($connectionMysql, $connectionName);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getConnection($connectionName)->enableQueryLog();


