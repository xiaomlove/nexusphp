<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();
parked();

if ($enablead_advertisement != 'yes') {
    stderr($lang_adredir['std_error'], $lang_adredir['std_ad_system_disabled']);
}
$id = $_GET['id'] ?? 0;
if (! $id) {
    stderr($lang_adredir['std_error'], $lang_adredir['std_invalid_ad_id']);
}
$redir = htmlspecialchars_decode(urldecode($_GET['url']));
if (! $redir) {
    stderr($lang_adredir['std_error'], $lang_adredir['std_no_redirect_url']);
}
$adcount = NexusDB::table('advertisements')->where('id', (int) $id)->count();
if (! $adcount) {
    stderr($lang_adredir['std_error'], $lang_adredir['std_invalid_ad_id']);
}
if ($adclickbonus_advertisement) {
    $clickcount = NexusDB::table('adclicks')
        ->where('adid', (int) $id)
        ->where('userid', (int) $CURUSER['id'])
        ->count();
    if (! $clickcount) {
        KPS('+', $adclickbonus_advertisement, $CURUSER['id']);
    }
}
NexusDB::insert('adclicks', [
    'adid' => (int) $id,
    'userid' => (int) $CURUSER['id'],
    'added' => date('Y-m-d H:i:s'),
]);
header("Location: $redir");
