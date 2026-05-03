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

$editsecret = \Nexus\Database\NexusDB::table('users')->where('id', (int) $id)->value('editsecret');

if ($editsecret === null)
	httperr();

$sec = hash_pad($editsecret);
if (preg_match('/^ *$/s', $sec))
	httperr();
if ($md5 != md5($sec . $email . $sec))
	httperr();

$affected = \Nexus\Database\NexusDB::table('users')->where('id', (int) $id)->where('editsecret', (string) $editsecret)->update([
	'editsecret' => '',
	'email' => (string) $email,
]);

if (!$affected)
	httperr();

header("Location: " . get_protocol_prefix() . "$BASEURL/usercp.php?action=security&type=saved");
?>
