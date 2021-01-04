<?php
# IMPORTANT: Do not edit below unless you know what you are doing!
define('IN_TRACKER', true);
$rootpath=realpath(dirname(__FILE__) . '/..')."/";

require $rootpath . 'include/config.php';
require $rootpath . 'include/functions.php';
require $rootpath . 'include/globalfunctions.php';
require $rootpath . get_langfile_path("functions.php");

require $rootpath . 'include/database/interface_db.php';
require $rootpath . 'include/database/class_db_mysqli.php';
require $rootpath . 'include/database/class_db.php';
require $rootpath . 'include/database/helpers.php';
require $rootpath . 'include/database/class_exception.php';

require $rootpath . 'classes/class_cache_redis.php';

require $rootpath . 'include/core.php';
require $rootpath . 'include/functions_announce.php';

if (!session_id()) {
    session_start();
}
?>
