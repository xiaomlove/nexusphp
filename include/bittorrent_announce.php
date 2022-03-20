<?php
defined('IN_NEXUS') || define('IN_NEXUS', true);
defined('IN_TRACKER') || define('IN_TRACKER', true);
$rootpath= dirname(__DIR__) . '/';
require_once $rootpath . 'include/constants.php';
require_once $rootpath . 'include/globalfunctions.php';
require_once $rootpath . 'include/functions_announce.php';
require_once $rootpath . 'vendor/autoload.php';
\Nexus\Nexus::boot();


