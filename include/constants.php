<?php
define('VERSION_NUMBER', '1.6.0-beta7');
define('RELEASE_DATE', '2020-05-15');
define('IN_TRACKER', true);
define("PROJECTNAME","NexusPHP");
define("NEXUSPHPURL","https://nexusphp.org");
define("NEXUSWIKIURL","https://doc.nexusphp.org");
define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
define("THISTRACKER","General");
$showversion = " - Powered by ".PROJECTNAME;
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CURRENT_SCRIPT', strstr(basename($_SERVER['SCRIPT_FILENAME']), '.', true));
define('IS_ANNOUNCE', CURRENT_SCRIPT == 'announce');
define('REQUEST_ID', $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['REQUEST_ID'] ?? str_pad(str_replace('.', '', NEXUS_START), 14, "0", STR_PAD_RIGHT));
