<?php
defined('VERSION_NUMBER') || define('VERSION_NUMBER', '1.7.20');
defined('RELEASE_DATE') || define('RELEASE_DATE', '2022-08-05');
defined('IN_TRACKER') || define('IN_TRACKER', false);
defined('PROJECTNAME') || define("PROJECTNAME","NexusPHP");
defined('NEXUSPHPURL') || define("NEXUSPHPURL","https://nexusphp.org");
defined('NEXUSWIKIURL') || define("NEXUSWIKIURL","https://doc.nexusphp.org");
defined('VERSION') || define("VERSION","Powered by <a href=\"aboutnexus.php\">".PROJECTNAME."</a>");
defined('THISTRACKER') || define("THISTRACKER","General");
defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__) . '/');
defined('DEFAULT_TRACKER_URI') || define('DEFAULT_TRACKER_URI', '/announce.php');
if (!defined('RUNNING_IN_OCTANE')) {
    $runningInOctane = false;
    foreach (($_SERVER['argv'] ?? []) as $command) {
        if (preg_match('/swoole|roadrunner/i', $command)) {
            $runningInOctane = true;
            break;
        }
    }
    define('RUNNING_IN_OCTANE', $runningInOctane);
}
