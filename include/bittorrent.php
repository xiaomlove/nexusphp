<?php
define('NEXUS_START', microtime(true));
define('IN_NEXUS', true);
$rootpath = dirname(__DIR__) . '/';
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);
require $rootpath . 'include/core.php';
require $rootpath . 'classes/class_advertisement.php';
require $rootpath . 'classes/class_attendance.php';

