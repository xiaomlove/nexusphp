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
	$userid = (int) $CURUSER['id'];
	$existing = \Nexus\Database\NexusDB::table('bookmarks')
		->where('torrentid', $torrentid)
		->where('userid', $userid)
		->first();
	if ($existing) {
		$existing = (array) $existing;
		$searchRep->deleteBookmark($existing['id']);
		\Nexus\Database\NexusDB::table('bookmarks')
			->where('torrentid', $torrentid)
			->where('userid', $userid)
			->delete();
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
		echo "deleted";
	} else {
		$newId = (int) \Nexus\Database\NexusDB::insert('bookmarks', [
			'torrentid' => $torrentid,
			'userid' => $userid,
		]);
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
		$searchRep->addBookmark($newId);
		echo "added";
	}
}
else echo "failed";
?>
