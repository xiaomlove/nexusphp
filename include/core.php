<?php
if(!defined('IN_TRACKER')) {
    die('Hacking attempt!');
}
define('ROOT_PATH', $rootpath);
define('VERSION_NUMBER', '1.6.0');
define('CURRENT_SCRIPT', strstr(basename($_SERVER['SCRIPT_FILENAME']), '.', true));
define('IS_ANNOUNCE', CURRENT_SCRIPT == 'announce');
define('REQUEST_ID', $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['REQUEST_ID'] ?? intval(NEXUS_START * 10000));
if (!file_exists($rootpath . '.env')) {
    $installScriptRelativePath = 'install/install.php';
    $installScriptFile = $rootpath . "public/$installScriptRelativePath";
    if (file_exists($installScriptFile)) {
        redirect($installScriptRelativePath);
    }
}
require $rootpath . 'vendor/autoload.php';
require $rootpath . 'nexus/Database/helpers.php';
require $rootpath . 'classes/class_cache_redis.php';
require $rootpath . 'include/config.php';

ini_set('date.timezone', nexus_config('nexus.timezone'));
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);

if (!in_array(CURRENT_SCRIPT, ['announce', 'scrape'])) {
    require $rootpath . get_langfile_path("functions.php");
    checkGuestVisit();
}
$Cache = new class_cache_redis(); //Load the caching class
$Cache->setLanguageFolderArray(get_langfolder_list());
define('TIMENOW', time());
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
