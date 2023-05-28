<?php
require_once("../include/bittorrent.php");

if (!preg_match(':^/(\d{1,10})/([\w]{32})/(.+)$:', $_SERVER["PATH_INFO"], $matches))
	httperr();

$id = intval($matches[1] ?? 0);
$md5 = $matches[2];
$email = urldecode($matches[3]);
//print($email);
//die();

if (!$id)
	httperr();
dbconn();

$res = sql_query("SELECT editsecret FROM users WHERE id = $id");
$row = mysql_fetch_array($res);

if (!$row)
	httperr();

$sec = hash_pad($row["editsecret"]);
if (preg_match('/^ *$/s', $sec))
	httperr();
if ($md5 != md5($sec . $email . $sec))
	httperr();

sql_query("UPDATE users SET editsecret='', email=" . sqlesc($email) . " WHERE id=$id AND editsecret=" . sqlesc($row["editsecret"]));

if (!mysql_affected_rows())
	httperr();

header("Refresh: 0; url=" . get_protocol_prefix() . "$BASEURL/usercp.php?action=security&type=saved");
?>