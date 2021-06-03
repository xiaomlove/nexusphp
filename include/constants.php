<?php
defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.6.0-beta8');
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

//define the REQUEST_ID
if (!defined('REQUEST_ID')) {
    if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'];
    } elseif (!empty($_SERVER['REQUEST_ID'])) {
        $requestId = $_SERVER['REQUEST_ID'];
    } else {
        $prefix = ($_SERVER['SCRIPT_FILENAME'] ?? '') . implode('', $_SERVER['argv'] ?? []);
        $prefix = substr(md5($prefix), 0, 4);
        // 4 + 23 = 27 characters, after replace '.', 26
        $requestId = str_replace('.', '', uniqid($prefix, true));
        $requestId .= bin2hex(random_bytes(3));
    }
    define('REQUEST_ID', $requestId);
}
