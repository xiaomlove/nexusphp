<?php
require "../include/bittorrent.php";
//require_once ("imdb/imdb.class.php");
dbconn();
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");
$imdblink = $_GET['url'];
$mode = $_GET['type'];
$cache_stamp = $_GET['cache'];
$imdb_id = parse_imdb_id($imdblink);
$Cache->new_page('imdb_id_'.$imdb_id.'_'.$mode);
if (!$Cache->get_page()){
	$infoblock = getimdb($imdb_id, $cache_stamp, $mode);
	if ($infoblock){
		$Cache->add_whole_row();
		print($infoblock);
		$Cache->end_whole_row();
		$Cache->cache_page();
		echo $Cache->next_row();
	}
}
else echo $Cache->next_row();
?>
