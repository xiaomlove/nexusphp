<?php

$config = require ROOT_PATH . 'config/nexus.php';
$connectionMysql = $config['mysql'];
\Nexus\Database\NexusDB::bootEloquent($connectionMysql);


