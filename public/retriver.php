<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();
if (get_user_class() < $updateextinfo_class) {
permissiondenied();
}
$id = intval($_GET["id"] ?? 0);
$type = intval($_GET["type"] ?? 0);
$siteid = $_GET["siteid"] ?? 0; // 1 for IMDb

if (!isset($id) || !$id || !is_numeric($id) || !isset($type) || !$type || !is_numeric($type) || !isset($siteid) || !$siteid)
die();

$r = sql_query("SELECT * from torrents WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
if(mysql_num_rows($r) != 1)
die();

$row = mysql_fetch_assoc($r);

switch ($siteid)
{
	case 1 :
	{
		$imdb_id = parse_imdb_id($row["url"]);
		if ($imdb_id)
		{
			$thenumbers = $imdb_id;
			$imdb = new \Nexus\Imdb\Imdb();
			set_cachetimestamp($id,"cache_stamp");

			$imdb->purgeSingle($imdb_id);

			try {
				$imdb->updateCache($imdb_id);
				$Cache->delete_value('imdb_id_'.$thenumbers.'_movie_name');
				$Cache->delete_value('imdb_id_'.$thenumbers.'_large', true);
				$Cache->delete_value('imdb_id_'.$thenumbers.'_median', true);
				$Cache->delete_value('imdb_id_'.$thenumbers.'_minor', true);
			} catch (\Exception $e) {
				$log = $e->getMessage() . ", trace: " . $e->getTraceAsString();
				do_log($log, 'error');
			}
            nexus_redirect(getSchemeAndHttpHost() . "/details.php?id=$id");
		}
		break;
	}
	case \Nexus\PTGen\PTGen::SITE_IMDB:
	case \Nexus\PTGen\PTGen::SITE_DOUBAN:
	case \Nexus\PTGen\PTGen::SITE_BANGUMI:
		{
			$ptGen = new \Nexus\PTGen\PTGen();
			try {
				$ptGen->updateTorrentPtGen($row, $siteid);
			} catch (\Exception $e) {
				$log = $e->getMessage() . ", trace: " . $e->getTraceAsString();
				do_log($log, 'error');
			}
			nexus_redirect(getSchemeAndHttpHost() . "/details.php?id=$id");
			break;
		}
	default :
	{
		die("Error!");
		break;
	}
}

?>
