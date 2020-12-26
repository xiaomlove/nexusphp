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

require $rootpath . 'classes/interface_db.php';
require $rootpath . 'classes/class_db_mysqli.php';
require $rootpath . 'classes/class_db.php';
require $rootpath . 'include/functions_db.php';

require $rootpath . 'include/core.php';
