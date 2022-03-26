<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path("",true));
loggedinorreturn();
function bark($msg) {
  global $lang_fastdelete;
  stdhead();
  stdmsg($lang_fastdelete['std_delete_failed'], $msg);
  stdfoot();
  exit;
}

if (!mkglobal("id"))
    bark($lang_fastdelete['std_missing_form_data']);

$id = intval($id ?? 0);
int_check($id);
$sure = $_GET["sure"];

$res = sql_query("SELECT name,owner,seeders,anonymous FROM torrents WHERE id = $id");
$row = mysql_fetch_array($res);
if (!$row)
    die();

if (get_user_class() < $torrentmanage_class)
    bark($lang_fastdelete['text_no_permission']);

if (!$sure)
	{
	stderr($lang_fastdelete['std_delete_torrent'], $lang_fastdelete['std_delete_torrent_note']."<a class=altlink href=fastdelete.php?id=$id&sure=1>".$lang_fastdelete['std_here_if_sure'],false);
	}

$searchRep = new \App\Repositories\SearchRepository();
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    bark('Delete es fail.');
}
deletetorrent($id);
KPS("-",$uploadtorrent_bonus,$row["owner"]);
if ($row['anonymous'] == 'yes' && $CURUSER["id"] == $row["owner"]) {
	write_log("Torrent $id ($row[name]) was deleted by its anonymous uploader",'normal');
} else {
	write_log("Torrent $id ($row[name]) was deleted by $CURUSER[username]",'normal');
}
//Send pm to torrent uploader
if ($CURUSER["id"] != $row["owner"]){
	$dt = sqlesc(date("Y-m-d H:i:s"));
	$subject = sqlesc($lang_fastdelete_target[get_user_lang($row["owner"])]['msg_torrent_deleted']);
	$msg = sqlesc($lang_fastdelete_target[get_user_lang($row["owner"])]['msg_the_torrent_you_uploaded'].$row['name'].$lang_fastdelete_target[get_user_lang($row["owner"])]['msg_was_deleted_by']."[url=userdetails.php?id=".$CURUSER['id']."]".$CURUSER['username']."[/url]".$lang_fastdelete_target[get_user_lang($row["owner"])]['msg_blank']);
	sql_query("INSERT INTO messages (sender, receiver, subject, added, msg) VALUES(0, $row[owner], $subject, $dt, $msg)") or sqlerr(__FILE__, __LINE__);
}
header("Refresh: 0; url=torrents.php");
?>
