<?php

use App\Models\BonusLogs;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\User;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();

$userid = $CURUSER['id'];
$torrentid = (int) $_POST['id'];
$value = (int) abs($_POST['value']);
if (! in_array($value, Setting::getBonusRewardOptions())) {
    exit(json_encode(fail('Invalid value.', $_POST)));
}

if ($value > $CURUSER['seedbonus']) {
    exit(json_encode(fail('You do not have such bonus!', $_POST)));
}
$arr = NexusDB::table('torrents')
    ->where('id', (int) $torrentid)
    ->select(['owner'])
    ->first();
$arr = $arr ? (array) $arr : null;
if (! $arr) {
    exit(json_encode(fail('Invalid torrent id!', $_POST)));
}

$torrentowner = $arr['owner'];
if ($torrentowner == $userid) {
    exit(json_encode(fail('You are giving magic to yourself.', $_POST)));
}
$t_ab = NexusDB::table('magic')
    ->where('torrentid', (int) $torrentid)
    ->where('userid', (int) $userid)
    ->count();
if ($t_ab != 0) {
    exit(json_encode(fail('You already gave the magic value!', $_POST)));
}
$todayStr = now()->startOfDay();
$todayCount = Reward::query()
    ->where('userid', $userid)
    ->where('created_at', '>=', $todayStr)
    ->count();
$timesLimit = Setting::getBonusRewardTimesLimit();
if ($timesLimit > 0 && $todayCount >= $timesLimit) {
    exit(json_encode(fail('You already reach times limit!', $_POST)));
}
$torrentOwnerInfo = User::query()->find($torrentowner, User::$commonFields);
if (! $torrentOwnerInfo) {
    exit(json_encode(fail('Invalid torrent owner!', $_POST)));
}
if (isset($userid) && isset($torrentid) && isset($value)) {
    NexusDB::insert('magic', [
        'torrentid' => (int) $torrentid,
        'userid' => (int) $userid,
        'value' => (int) $value,
    ]);
    KPS('-', $value, $CURUSER['id']); // selete
    BonusLogs::add($CURUSER['id'], $CURUSER['seedbonus'], $value, $CURUSER['seedbonus'] - $value, '', BonusLogs::BUSINESS_TYPE_REWARD_TORRENT);
    KPS('+', $value, $torrentowner); // add to the owner
    BonusLogs::add($torrentOwnerInfo['id'], $torrentOwnerInfo['seedbonus'], $value, $torrentOwnerInfo['seedbonus'] + $value, '', BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD);
    exit(json_encode(success('OK', $_POST)));
}
