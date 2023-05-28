<?php

/**
 * do clean in cli
 *
 */

require "bittorrent.php";
require 'cleanup.php';

$fd = fopen(sprintf('%s/nexus_cleanup_cli.lock', sys_get_temp_dir()), 'w+');
if (!flock($fd, LOCK_EX|LOCK_NB)) {
    do_log("can not get lock, skip!");
    exit();
}
register_shutdown_function(function () use ($fd) {
    flock($fd, LOCK_UN);
    fclose($fd);
});

$force = 0;
if (isset($_SERVER['argv'][1])) {
    $force = $_SERVER['argv'][1] ? 1 : 0;
}
$logPrefix = "[CLEANUP_CLI]";
$begin = time();
if ($force) {
    $result = docleanup(1, true);
} else {
    $result = autoclean(true);
}
$log = "$logPrefix, DONE: $result, cost time in seconds: " . (time() - $begin);
do_log($log);
printProgress($log);

