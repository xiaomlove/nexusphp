<?php

/**
 * do clean in cli
 *
 */

require "bittorrent.php";

$fd = fopen(sprintf('%s/nexus_cleanup_cli.lock', sys_get_temp_dir()), 'w+');
if (!flock($fd, LOCK_EX|LOCK_NB)) {
    do_log("can not get lock, skip!");
}
register_shutdown_function(function () use ($fd) {
    fclose($fd);
});

try {
    $result = autoclean();
    do_log("[CLEANUP_CLI DONE!] $result");
} catch (\Exception $exception) {
    do_log("ERROR: " . $exception->getMessage());
    throw new \RuntimeException($exception->getMessage());
}

