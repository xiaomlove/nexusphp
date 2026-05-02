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
user_can('torrent-delete', true);
function bark($msg)
{
    global $lang_delete;
    stdhead();
    stdmsg($lang_delete['std_delete_failed'], $msg);
    stdfoot();
    exit;
}

if (! mkglobal('id')) {
    bark($lang_delete['std_missing_form_date']);
}

$id = intval($id ?? 0);
if (! $id) {
    exit();
}

$row = NexusDB::table('torrents')
    ->where('id', (int) $id)
    ->select(['name', 'owner', 'seeders', 'anonymous'])
    ->first();
$row = $row ? (array) $row : null;
if (! $row) {
    exit();
}

if ($CURUSER['id'] != $row['owner'] && ! user_can('torrentmanage')) {
    bark($lang_delete['std_not_owner']);
}

$rt = intval($_POST['reasontype'] ?? 0);

if (! is_int($rt) || $rt < 1 || $rt > 5) {
    bark($lang_delete['std_invalid_reason']."$rt.");
}

$reason = $_POST['reason'];

if ($rt == 1) {
    $reasonstr = 'Dead: 0 seeders, 0 leechers = 0 peers total';
} elseif ($rt == 2) {
    $reasonstr = 'Dupe'.($reason[0] ? (': '.trim($reason[0])) : '!');
} elseif ($rt == 3) {
    $reasonstr = 'Nuked'.($reason[1] ? (': '.trim($reason[1])) : '!');
} elseif ($rt == 4) {
    if (! $reason[2]) {
        bark($lang_delete['std_describe_violated_rule']);
    }
    $reasonstr = $SITENAME.' rules broken: '.trim($reason[2]);
} else {
    if (! $reason[3]) {
        bark($lang_delete['std_enter_reason']);
    }
    $reasonstr = trim($reason[3]);
}
$searchRep = new SearchRepository;
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    bark('Delete es fail.');
}
deletetorrent($id);

if ($row['anonymous'] == 'yes' && $CURUSER['id'] == $row['owner']) {
    write_log("Torrent $id ({$row['name']}) was deleted by its anonymous uploader ($reasonstr)", 'normal');
} else {
    write_log("Torrent $id ({$row['name']}) was deleted by {$CURUSER['username']} ($reasonstr)", 'normal');
}

// ===remove karma
KPS('-', $uploadtorrent_bonus, $row['owner']);

// Send pm to torrent uploader
if ($CURUSER['id'] != $row['owner'] && User::exists($row['owner'])) {
    $dt = date('Y-m-d H:i:s');
    $locale = get_user_locale($row['owner']);
    $subject = nexus_trans('torrent.msg_torrent_deleted', [], $locale);
    $msg = nexus_trans('torrent.msg_the_torrent_you_uploaded', [], $locale).$row['name'].nexus_trans('torrent.msg_was_deleted_by', [], $locale).'[url=userdetails.php?id='.$CURUSER['id'].']'.$CURUSER['username'].'[/url]'.nexus_trans('torrent.msg_reason_is', [], $locale).$reasonstr;
    Message::add([
        'sender' => 0,
        'receiver' => $row['owner'],
        'subject' => $subject,
        'msg' => $msg,
        'added' => $dt,
    ]);
}
stdhead($lang_delete['head_torrent_deleted']);

if (isset($_POST['returnto'])) {
    $ret = '<a href="'.htmlspecialchars($_POST['returnto']).'">'.$lang_delete['text_go_back'].'</a>';
} else {
    $ret = '<a href="index.php">'.$lang_delete['text_back_to_index'].'</a>';
}

?>
<h1><?php echo $lang_delete['text_torrent_deleted'] ?></h1>
<p><?php echo $ret ?></p>
<?php
stdfoot();
