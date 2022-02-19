<?php

/**
 * do clean in cli
 *
 */

require "bittorrent.php";

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

try {
    if ($force) {
        require_once(ROOT_PATH . 'include/cleanup.php');
        $result = docleanup(1, true);
    } else {
        $result = autoclean();
    }
    $log = "[CLEANUP_CLI DONE!] $result";
    do_log($log);
    printProgress($log);
} catch (\Exception $exception) {
    $log = "ERROR: " . $exception->getMessage();
    do_log($log);
    printProgress($log);
    throw new \RuntimeException($exception->getMessage());
}

