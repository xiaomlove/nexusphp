<?php
defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.6.4');
defined('RELEASE_DATE') || define('RELEASE_DATE', '2022-03-28');
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

defined('PLATFORM_ADMIN') || define('PLATFORM_ADMIN', 'admin');
defined('PLATFORM_USER') || define('PLATFORM_USER', 'user');
defined('PLATFORMS') || define('PLATFORMS', [PLATFORM_ADMIN, PLATFORM_USER]);
defined('CURRENT_PLATFORM') || define('CURRENT_PLATFORM', $_SERVER['HTTP_PLATFORM'] ?? '');
defined('IS_PLATFORM_ADMIN') || define('IS_PLATFORM_ADMIN', CURRENT_PLATFORM == PLATFORM_ADMIN);
defined('IS_PLATFORM_USER') || define('IS_PLATFORM_USER', CURRENT_PLATFORM == PLATFORM_USER);


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
