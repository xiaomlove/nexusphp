<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
parked();
$id = (int)$_GET["id"];

if (!$id)
	die('Invalid id.');
$dlkey = $_GET["dlkey"];

if (!$dlkey)
	die('Invalid key');
$res = sql_query("SELECT * FROM attachments WHERE id = ".sqlesc($id)." AND dlkey = ".sqlesc($dlkey)." LIMIT 1") or sqlerr(__FILE__, __LINE__);
$row = mysql_fetch_assoc($res);
if (!$row)
	die('No attachment found.');
$filelocation = $httpdirectory_attachment."/".$row['location'];
if (!is_file($filelocation) || !is_readable($filelocation))
	die('File not found or cannot be read.');
$f = fopen($filelocation, "rb");
if (!$f)
die("Cannot open file");

header("Content-Length: " . $row['filesize']);
header("Content-Type: application/octet-stream");

if ( str_replace("Gecko", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'])
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("Firefox", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("Opera", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("IE", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode($row[filename])));
}
else
{
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode($row[filename])));
}

do
{
$s = fread($f, 4096);
print($s);
} while (!feof($f));
sql_query("UPDATE attachments SET downloads = downloads + 1 WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
$Cache->delete_value('attachment_'.$dlkey.'_content');
exit;
?>
