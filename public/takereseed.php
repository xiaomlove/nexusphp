<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("",true));
loggedinorreturn();
if (get_user_class() < $askreseed_class)
permissiondenied();

$reseedid = intval($_GET["reseedid"] ?? 0);
$res = sql_query("SELECT seeders, last_reseed FROM torrents WHERE id=".sqlesc($reseedid)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
$row = mysql_fetch_array($res);
if ($row['seeders'] > 0)
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_torrent_not_dead']);
elseif (strtotime($row['last_reseed']) > (TIMENOW - 900))
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_reseed_sent_recently']);
else{
$res = sql_query("SELECT snatched.userid, snatched.torrentid, torrents.name as torrent_name, users.id FROM snatched inner join users on snatched.userid = users.id inner join torrents on snatched.torrentid = torrents.id  where snatched.finished = 'Yes' AND snatched.torrentid = $reseedid") or sqlerr();
while($row = mysql_fetch_assoc($res)) {
$rs_subject = $lang_takereseed_target[get_user_lang($row["userid"])]['msg_reseed_request'];
$pn_msg = $lang_takereseed_target[get_user_lang($row["userid"])]['msg_user'].$CURUSER["username"].$lang_takereseed_target[get_user_lang($row["userid"])]['msg_ask_reseed']."[url=" . get_protocol_prefix() . "$BASEURL/details.php?id=".$reseedid."]".$row["torrent_name"]."[/url]".$lang_takereseed_target[get_user_lang($row["userid"])]['msg_thank_you'];
sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $row[userid], '" . date("Y-m-d H:i:s") . "'," . sqlesc($rs_subject) . ", " . sqlesc($pn_msg) . ")") or sqlerr(__FILE__, __LINE__);
}
sql_query("UPDATE torrents SET last_reseed = ".sqlesc(date("Y-m-d H:i:s"))." WHERE id=".sqlesc($reseedid));
stdhead($lang_takereseed['head_reseed_request']);
begin_main_frame();
print("<center>".$lang_takereseed['std_it_worked']."</center>");
end_main_frame();
stdfoot();
}
?>
