<?php
defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.6.0-beta7');
defined('RELEASE_DATE') || define('RELEASE_DATE', '2020-05-15');
defined('IN_TRACKER') || define('IN_TRACKER', true);
defined('PROJECTNAME') || define("PROJECTNAME","NexusPHP");
defined('NEXUSPHPURL') || define("NEXUSPHPURL","https://nexusphp.org");
defined('NEXUSWIKIURL') || define("NEXUSWIKIURL","https://doc.nexusphp.org");
defined('VERSION') || define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
defined('THISTRACKER') || define("THISTRACKER","General");
$showversion = " - Powered by ".PROJECTNAME;
defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__) . '/');
defined('CURRENT_SCRIPT') || define('CURRENT_SCRIPT', strstr(basename($_SERVER['SCRIPT_FILENAME']), '.', true));
defined('IS_ANNOUNCE') || define('IS_ANNOUNCE', CURRENT_SCRIPT == 'announce');
defined('REQUEST_ID') || define('REQUEST_ID', $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['REQUEST_ID'] ?? bin2hex(random_bytes(11)) . str_replace('.', '', substr(uniqid('', true), 12)));
