<?php
define('NEXUS_START', microtime(true));
define('IN_TRACKER', true);
define("PROJECTNAME","NexusPHP");
define("NEXUSPHPURL","http://nexusphp.org");
define("NEXUSWIKIURL","http://doc.nexusphp.org");
define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
define("THISTRACKER","General");
$showversion = " - Powered by ".PROJECTNAME;
$rootpath= dirname(__DIR__);
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);
$rootpath .= "/";
require $rootpath . 'include/functions.php';
require $rootpath . 'include/globalfunctions.php';
require $rootpath . 'include/core.php';


