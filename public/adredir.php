<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
if ($enablead_advertisement != 'yes')
	stderr($lang_adredir['std_error'], $lang_adredir['std_ad_system_disabled']);
$id=$_GET['id'] ?? 0;
if (!$id)
	stderr($lang_adredir['std_error'], $lang_adredir['std_invalid_ad_id']);
$redir=htmlspecialchars_decode(urldecode($_GET['url']));
if (!$redir)
	stderr($lang_adredir['std_error'], $lang_adredir['std_no_redirect_url']);
$adcount=get_row_count("advertisements", "WHERE id=".sqlesc($id));
if (!$adcount)
	stderr($lang_adredir['std_error'], $lang_adredir['std_invalid_ad_id']);
if ($adclickbonus_advertisement){
	$clickcount=get_row_count("adclicks", "WHERE adid=".sqlesc($id)." AND userid=".sqlesc($CURUSER['id']));
	if (!$clickcount)
		KPS("+",$adclickbonus_advertisement,$CURUSER['id']);
}
sql_query("INSERT INTO adclicks (adid, userid, added) VALUES (".sqlesc($id).", ".sqlesc($CURUSER['id']).", ".sqlesc(date("Y-m-d H:i:s")).")");
header("Location: $redir");
