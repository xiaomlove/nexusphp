<?php
defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.7.0');
defined('RELEASE_DATE') || define('RELEASE_DATE', '2022-03-31');
defined('IN_TRACKER') || define('IN_TRACKER', true);
defined('PROJECTNAME') || define("PROJECTNAME","NexusPHP");
defined('NEXUSPHPURL') || define("NEXUSPHPURL","https://nexusphp.org");
defined('NEXUSWIKIURL') || define("NEXUSWIKIURL","https://doc.nexusphp.org");
defined('VERSION') || define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
defined('THISTRACKER') || define("THISTRACKER","General");
defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__) . '/');
if (!defined('RUNNING_IN_OCTANE')) {
    if (!empty($_SERVER['PWD']) && str_contains($_SERVER['PWD'], 'vendor/laravel/octane/bin')) {
        define('RUNNING_IN_OCTANE', true);
    } else {
        define('RUNNING_IN_OCTANE', false);
    }
}
