<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
define('IN_NEXUS', true);
define('NEXUS_START', microtime(true));
require ROOT_PATH . 'include/globalfunctions.php';
require ROOT_PATH . 'include/functions.php';
require ROOT_PATH . 'vendor/autoload.php';
require ROOT_PATH . 'nexus/Database/helpers.php';
require ROOT_PATH . 'include/constants.php';
$withLaravel = false;
if (file_exists(ROOT_PATH . '.env')) {
    require ROOT_PATH . 'include/eloquent.php';
    require ROOT_PATH . 'classes/class_cache_redis.php';
    $Cache = new class_cache_redis();
    $withLaravel = true;
}
define('WITH_LARAVEL', $withLaravel);
\Nexus\Nexus::boot();
