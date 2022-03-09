<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
define('IN_NEXUS', true);
define('NEXUS_START', microtime(true));
require ROOT_PATH . 'vendor/autoload.php';
require ROOT_PATH . 'nexus/Database/helpers.php';
require ROOT_PATH . 'include/constants.php';
$withLaravel = false;
if (file_exists(ROOT_PATH . '.env')) {
    require ROOT_PATH . 'include/eloquent.php';
    $withLaravel = true;
}
define('WITH_LARAVEL', $withLaravel);
