<?php
define('IN_TRACKER', true);
define("PROJECTNAME","NexusPHP");
define("NEXUSPHPURL","http://www.nexusphp.com");
define("NEXUSWIKIURL","http://www.nexusphp.com/wiki");
define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
define("THISTRACKER","General");
$showversion = " - Powered by ".PROJECTNAME;
$rootpath=realpath(dirname(__FILE__) . '/..');
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);
$rootpath .= "/";
require $rootpath . 'include/config.php';
require $rootpath . 'include/functions.php';
require $rootpath . 'include/globalfunctions.php';
require $rootpath . get_langfile_path("functions.php");

require $rootpath . 'include/database/interface_db.php';
require $rootpath . 'include/database/class_db_mysqli.php';
require $rootpath . 'include/database/class_db.php';
require $rootpath . 'include/database/helpers.php';
require $rootpath . 'include/database/class_exception.php';

require $rootpath . 'classes/class_advertisement.php';
require $rootpath . 'classes/class_cache_redis.php';

require $rootpath . 'include/core.php';

if (!session_id()) {
    session_start();
}
