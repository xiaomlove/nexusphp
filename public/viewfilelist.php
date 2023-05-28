<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());

//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
header("Cache-Control: no-cache, must-revalidate" ); 
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

$id = intval($_GET['id'] ?? 0);
if(isset($CURUSER))
{
	$s = "<table class=\"main\" border=\"1\" cellspacing=0 cellpadding=\"5\">\n";

	$subres = sql_query("SELECT * FROM files WHERE torrent = ".sqlesc($id)." ORDER BY id");
	$s.="<tr><td class=colhead>".$lang_viewfilelist['col_path']."</td><td class=colhead align=center><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" /></td></tr>\n";
	while ($subrow = mysql_fetch_array($subres)) {
		$s .= "<tr><td class=rowfollow>" . $subrow["filename"] . "</td><td class=rowfollow align=\"right\">" . mksize($subrow["size"]) . "</td></tr>\n";
	}
	$s .= "</table>\n";
	echo $s;
}
?>
