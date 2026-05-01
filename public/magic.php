<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

$userid = $CURUSER["id"];
$torrentid = (int) $_POST["id"];
$value = (int) abs($_POST['value']);
if (!in_array($value, \App\Models\Setting::getBonusRewardOptions())) {
    exit(json_encode(fail("Invalid value.", $_POST)));
}

if($value > $CURUSER['seedbonus']) exit(json_encode(fail('You do not have such bonus!', $_POST)));
$arr = \Nexus\Database\NexusDB::table('torrents')
    ->where('id', (int) $torrentid)
    ->select(['owner'])
    ->first();
$arr = $arr ? (array) $arr : null;
if (!$arr) exit(json_encode(fail("Invalid torrent id!", $_POST)));

$torrentowner = $arr['owner'];
if($torrentowner == $userid) exit(json_encode(fail('You are giving magic to yourself.', $_POST)));
$t_ab = get_row_count("magic", "WHERE torrentid=$torrentid and userid=$userid");
if ($t_ab != 0) exit(json_encode(fail("You already gave the magic value!", $_POST)));
$todayStr = now()->startOfDay();
$todayCount = \App\Models\Reward::query()
    ->where('userid', $userid)
    ->where('created_at', ">=", $todayStr)
    ->count();
$timesLimit = \App\Models\Setting::getBonusRewardTimesLimit();
if ($timesLimit > 0 && $todayCount >= $timesLimit) exit(json_encode(fail("You already reach times limit!", $_POST)));
$torrentOwnerInfo = \App\Models\User::query()->find($torrentowner, \App\Models\User::$commonFields);
if (!$torrentOwnerInfo) {
    exit(json_encode(fail("Invalid torrent owner!", $_POST)));
}
if (isset($userid) && isset($torrentid)&& isset($value)) {
    \Nexus\Database\NexusDB::insert('magic', [
        'torrentid' => (int) $torrentid,
        'userid' => (int) $userid,
        'value' => (int) $value,
    ]);
    KPS("-",$value,$CURUSER['id']);//selete
    \App\Models\BonusLogs::add($CURUSER['id'], $CURUSER['seedbonus'], $value, $CURUSER['seedbonus'] - $value, "", \App\Models\BonusLogs::BUSINESS_TYPE_REWARD_TORRENT);
    KPS("+",$value,$torrentowner);//add to the owner
    \App\Models\BonusLogs::add($torrentOwnerInfo['id'], $torrentOwnerInfo['seedbonus'], $value, $torrentOwnerInfo['seedbonus'] + $value, "", \App\Models\BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD);
    exit(json_encode(success("OK", $_POST)));
}
