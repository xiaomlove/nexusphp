<?php

use Nexus\Database\NexusDB;

require_once '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();
function bark($msg)
{
    global $lang_takeflush;
    stdhead();
    stdmsg($lang_takeflush['std_failed'], $msg);
    stdfoot();
    exit;
}

$id = intval($_GET['id'] ?? 0);
int_check($id, true);

if (get_user_class() >= UC_MODERATOR || $CURUSER['id'] == "$id") {
    $deadtime = deadtime();
    $lastAction = date('Y-m-d H:i:s', $deadtime);
    $effected = NexusDB::table('peers')
        ->where('last_action', '<', $lastAction)
        ->where('userid', (int) $id)
        ->delete();

    stderr($lang_takeflush['std_success'], "$effected ".$lang_takeflush['std_ghost_torrents_cleaned']);
} else {
    bark($lang_takeflush['std_cannot_flush_others']);
}
