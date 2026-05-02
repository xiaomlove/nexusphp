<?php

use App\Models\Message;
use App\Models\User;
use App\Repositories\SearchRepository;
use Nexus\Database\NexusDB;

require_once '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
// require_once(get_langfile_path("",true));
loggedinorreturn();
function bark($msg)
{
    global $lang_fastdelete;
    stdhead();
    stdmsg($lang_fastdelete['std_delete_failed'], $msg);
    stdfoot();
    exit;
}

if (! mkglobal('id')) {
    bark($lang_fastdelete['std_missing_form_data']);
}

$id = intval($id ?? 0);
int_check($id);
$sure = $_GET['sure'];

$row = NexusDB::table('torrents')
    ->where('id', (int) $id)
    ->select(['name', 'owner', 'seeders', 'anonymous'])
    ->first();
$row = $row ? (array) $row : null;
if (! $row) {
    exit();
}

if (! user_can('torrentmanage') || ! user_can('torrent-delete')) {
    bark($lang_fastdelete['text_no_permission']);
}

if (! $sure) {
    stderr($lang_fastdelete['std_delete_torrent'], $lang_fastdelete['std_delete_torrent_note']."<a class=altlink href=fastdelete.php?id=$id&sure=1>".$lang_fastdelete['std_here_if_sure'], false);
}

$searchRep = new SearchRepository;
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    bark('Delete es fail.');
}
deletetorrent($id);
KPS('-', $uploadtorrent_bonus, $row['owner']);
if ($row['anonymous'] == 'yes' && $CURUSER['id'] == $row['owner']) {
    write_log("Torrent $id ($row[name]) was deleted by its anonymous uploader", 'normal');
} else {
    write_log("Torrent $id ($row[name]) was deleted by $CURUSER[username]", 'normal');
}
// Send pm to torrent uploader
if (User::query()->where('id', $row['owner'])->exists()) {
    if ($CURUSER['id'] != $row['owner']) {
        $locale = get_user_locale($row['owner']);
        $dt = date('Y-m-d H:i:s');
        $subject = nexus_trans('torrent.msg_torrent_deleted', [], $locale);
        $msg = nexus_trans('torrent.msg_the_torrent_you_uploaded', [], $locale)
            .$row['name']
            .nexus_trans('torrent.msg_was_deleted_by', ['admin' => $CURUSER['username']], $locale);
        Message::add([
            'sender' => 0,
            'receiver' => $row['owner'],
            'subject' => $subject,
            'msg' => $msg,
            'added' => $dt,
        ]);
    }
}
header('Location: torrents.php');
