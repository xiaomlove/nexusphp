<?php
ob_start();
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
$langFile = ROOT_PATH . get_langfile_path();
if (file_exists($langFile)) {
	require $langFile;
}
stdhead($lang_staffpanel["Administration"] ?? 'Administration');
print("<h1 align=center>" . ($lang_staffpanel["Administration"] ?? 'Administration') . "</h1>");
if (get_user_class() < UC_MODERATOR)
{
	stdmsg("Error", "Access denied!!!");
	stdfoot();
	exit;
}
begin_main_frame();

///////////////////// SysOp Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (get_user_class() >= UC_SYSOP) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For SysOp Only"] ?? 'For SysOp Only') . "  ::..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$query = "SELECT * FROM sysoppanel";
	$sql = sql_query($query);
	while ($row = mysql_fetch_array($sql)) {
		$id = $row['id'];
		$name = $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}
	print("</table>");
	print("<br />");
	print("<br />");
}
///////////////////// Admin Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (get_user_class() >= UC_ADMINISTRATOR) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For Administrator Only"] ?? 'For Administrator Only') . " :..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$query = "SELECT * FROM adminpanel";
	$sql = sql_query($query);
	while ($row = mysql_fetch_array($sql)) {
		$id = $row['id'];
		$name =  $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}
	print("</table>");
	print("<br />");
	print("<br />");
}
///////////////////// Moderator Only \\\\\\\\\\\\\\\\\\\\\\\\\\\\
if (get_user_class() >= UC_MODERATOR) {
	echo("<h1 align=center>..:: " . ($lang_staffpanel["For Moderator Only"] ?? 'For Moderator Only') . "  ::..</h1>");
	print("<br />");
	print("<br />");
	print("<table width=80% border=1 cellspacing=0 cellpadding=5 align=center>");
	echo("<td class=colhead align=left>" . ($lang_staffpanel["Option Name"] ?? 'Option Name') . "</td><td class=colhead align=left>" . ($lang_staffpanel["Info"] ?? 'Info') . "</td>");
	$query = "SELECT * FROM modpanel";
	$sql = sql_query($query);
	while ($row = mysql_fetch_array($sql)) {
		$id = $row['id'];
		$name =  $lang_staffpanel[$row['name']] ?? $row['name'];
		$url = $row['url'];
		$info = $lang_staffpanel[$row['info']] ?? $row['info'];

		echo("<tr><td class=rowfollow align=left><strong><a href=$url>$name</a></strong></td> <td class=rowfollow align=left>$info</td></tr>");
	}

	print("</table>");
	print("<br />");
	print("<br />");
}
end_main_frame();
stdfoot();
?>
