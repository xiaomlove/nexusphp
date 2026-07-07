<?php

declare(strict_types=1);

defined('IN_NEXUS') || define('IN_NEXUS', true);
defined('IN_TRACKER') || define('IN_TRACKER', true);
defined('IN_ANNOUNCE_V2') || define('IN_ANNOUNCE_V2', true);

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'include/constants.php';
require_once $rootpath . 'include/globalfunctions.php';
require_once $rootpath . 'vendor/autoload.php';

\Nexus\Nexus::boot();

require_once $rootpath . 'nexus/Database/helpers.php';
require_once $rootpath . 'classes/class_cache_redis.php';
require_once $rootpath . 'include/eloquent.php';

ini_set('date.timezone', nexus_config('nexus.timezone'));
ini_set('display_errors', '0');

$Cache = new class_cache_redis();

defined('TIMENOW') || define('TIMENOW', time());

// Promotion filters are part of accounting policy. Other web-only plugin hooks
// are deliberately kept outside the hot path.
$GLOBALS['hook'] = $hook = new \Nexus\Plugin\Hook();
$GLOBALS['plugin'] = $plugin = new \Nexus\Plugin\Plugin();
$plugin->start();
