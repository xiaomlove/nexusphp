<?php
define('IN_NEXUS', true);
$rootpath = dirname(__DIR__) . '/';
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);
require $rootpath . 'include/globalfunctions.php';
require $rootpath . 'include/functions.php';
require $rootpath . 'include/core.php';
require $rootpath . 'classes/class_advertisement.php';
require $rootpath . 'classes/class_attendance.php';


