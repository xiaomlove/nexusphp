<?php
require "../include/bittorrent.php";
dbconn();

//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

$torrentid = intval($_GET['torrentid'] ?? 0);
if(isset($CURUSER))
{
    $searchRep = new \App\Repositories\SearchRepository();
	$res_bookmark = sql_query("SELECT * FROM bookmarks WHERE torrentid=" . sqlesc($torrentid) . " AND userid=" . sqlesc($CURUSER['id']));
	if (mysql_num_rows($res_bookmark) == 1){
	    $bookmarkResult = mysql_fetch_assoc($res_bookmark);
        $searchRep->deleteBookmark($bookmarkResult['id']);
		sql_query("DELETE FROM bookmarks WHERE torrentid=" . sqlesc($torrentid) . " AND userid=" . sqlesc($CURUSER['id'])) or sqlerr(__FILE__,__LINE__);
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
		echo "deleted";
	} else {
		sql_query("INSERT INTO bookmarks (torrentid, userid) VALUES (" . sqlesc($torrentid) . "," . sqlesc($CURUSER['id']) . ")") or sqlerr(__FILE__,__LINE__);
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
        $searchRep->addBookmark(mysql_insert_id());
		echo "added";
	}
}
else echo "failed";
?>
