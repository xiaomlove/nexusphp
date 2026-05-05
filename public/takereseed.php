<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
//require(get_langfile_path("",true));
loggedinorreturn();
user_can('askreseed', true);

$reseedid = intval($_GET["reseedid"] ?? 0);
$rowObj = \Nexus\Database\NexusDB::table('torrents')->where('id', (int) $reseedid)->select(['seeders', 'last_reseed'])->first();
$row = $rowObj ? (array) $rowObj : ['seeders' => 0, 'last_reseed' => null];
$seederCount = (int) \Nexus\Database\NexusDB::table('peers')->where('torrent', (int) $reseedid)->count();
if ($seederCount > 0)
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_torrent_not_dead']);
elseif (strtotime($row['last_reseed']) > (TIMENOW - 900))
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_reseed_sent_recently']);
else{
$reseedRows = \Nexus\Database\NexusDB::select("SELECT snatched.userid, snatched.torrentid, torrents.name as torrent_name, users.id FROM snatched inner join users on snatched.userid = users.id inner join torrents on snatched.torrentid = torrents.id  where snatched.finished = 'Yes' AND snatched.torrentid = " . (int) $reseedid);
foreach ($reseedRows as $row) {
	$row = (array) $row;
    $locale = get_user_locale($row['userid']);
$rs_subject = nexus_trans("torrent.msg_reseed_request", [], $locale);
$pn_msg = nexus_trans("torrent.msg_reseed_user", [], $locale).$CURUSER["username"].nexus_trans("torrent.msg_ask_reseed", [], $locale)."[url=" . get_protocol_prefix() . "$BASEURL/details.php?id=".$reseedid."]".$row["torrent_name"]."[/url]".nexus_trans("torrent.msg_thank_you", [], $locale);
    \App\Models\Message::add([
        'sender' => 0,
        'receiver' => $row['userid'],
        'subject' => $rs_subject,
        'msg' => $pn_msg,
        'added' => now(),
    ]);
}
\App\Models\Torrent::query()->where("id", $reseedid)->update([
    "last_reseed" => now(),
    "seeders" => $seederCount,
]);
stdhead($lang_takereseed['head_reseed_request']);
begin_main_frame();
print("<center>".$lang_takereseed['std_it_worked']."</center>");
end_main_frame();
stdfoot();
}
?>
