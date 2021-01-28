<?php
if(!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}
if (!file_exists($rootpath . '.env')) {
    header('Location: ' . getBaseUrl() . 'install/install.php');
    exit(0);
}
error_reporting(E_ALL);
ini_set('display_errors', 0);
if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
    define('REQUEST_ID', $_SERVER['HTTP_X_REQUEST_ID']);
} else {
    define('REQUEST_ID', intval(NEXUS_START * 10000));
}
define('ROOT_PATH', $rootpath);
define('VERSION_NUMBER', '1.6.0');
define('IS_ANNOUNCE', (basename($_SERVER['SCRIPT_FILENAME']) == 'announce.php'));
require $rootpath . 'vendor/autoload.php';
require $rootpath . 'nexus/Database/helpers.php';
require $rootpath . 'classes/class_advertisement.php';
require $rootpath . 'classes/class_cache_redis.php';
require $rootpath . 'include/config.php';
if (!IS_ANNOUNCE) {
    require $rootpath . get_langfile_path("functions.php");
}
$Cache = new class_cache_redis(); //Load the caching class
$Cache->setLanguageFolderArray(get_langfolder_list());
define('TIMENOW', time());
define('TIMENOW_STRING', date('Y-m-d H:i:s'));
$USERUPDATESET = array();
$query_name=array();

define ("UC_PEASANT", 0);
define ("UC_USER", 1);
define ("UC_POWER_USER", 2);
define ("UC_ELITE_USER", 3);
define ("UC_CRAZY_USER", 4);
define ("UC_INSANE_USER", 5);
define ("UC_VETERAN_USER", 6);
define ("UC_EXTREME_USER", 7);
define ("UC_ULTIMATE_USER", 8);
define ("UC_NEXUS_MASTER", 9);
define ("UC_VIP", 10);
define ("UC_RETIREE",11);
define ("UC_UPLOADER",12);
define ("UC_FORUM_MODERATOR", 12);
define ("UC_MODERATOR",13);
define ("UC_ADMINISTRATOR",14);
define ("UC_SYSOP",15);
define ("UC_STAFFLEADER",16);
ignore_user_abort(1);
@set_time_limit(60);
