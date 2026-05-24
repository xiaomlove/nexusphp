<?php

use App\Repositories\TorrentRepository;
use Nexus\Database\NexusDB;
use Nexus\PTGen\PTGen;

require_once '../include/bittorrent.php';
dbconn();
loggedinorreturn();
user_can('updateextinfo', true);
$id = intval($_GET['id'] ?? 0);
$type = intval($_GET['type'] ?? 0);
$siteid = $_GET['siteid'] ?? 0; // 1 for IMDb

if (! isset($id) || ! $id || ! is_numeric($id) || ! isset($type) || ! $type || ! is_numeric($type) || ! isset($siteid) || ! $siteid) {
    exit();
}

$row = NexusDB::table('torrents')->where('id', (int) $id)->first();
$row = $row ? (array) $row : null;
if (! $row) {
    exit();
}

switch ($siteid) {
    case 1:

        $imdb_id = parse_imdb_id($row['url']);
        if ($imdb_id) {
            //			$thenumbers = $imdb_id;
            //			$imdb = new \Nexus\Imdb\Imdb();
            //			set_cachetimestamp($id,"cache_stamp");
            //
            //			$imdb->purgeSingle($imdb_id);
            //
            //			try {
            //				$imdb->updateCache($imdb_id);
            //				$Cache->delete_value('imdb_id_'.$thenumbers.'_movie_name');
            //				$Cache->delete_value('imdb_id_'.$thenumbers.'_large', true);
            //				$Cache->delete_value('imdb_id_'.$thenumbers.'_median', true);
            //				$Cache->delete_value('imdb_id_'.$thenumbers.'_minor', true);
            //			} catch (\Exception $e) {
            //				$log = $e->getMessage() . ", trace: " . $e->getTraceAsString();
            //				do_log($log, 'error');
            //			}
            $torrentRep = new TorrentRepository;
            $torrentRep->fetchImdb($id);
            nexus_redirect(getSchemeAndHttpHost()."/details.php?id=$id");
        }
        break;

    case PTGen::SITE_IMDB:
    case PTGen::SITE_DOUBAN:
    case PTGen::SITE_BANGUMI:

        $ptGen = new PTGen;
        try {
            $ptGen->updateTorrentPtGen($id);
        } catch (Exception $e) {
            $log = $e->getMessage().', trace: '.$e->getTraceAsString();
            do_log($log, 'error');
        }
        nexus_redirect(getSchemeAndHttpHost()."/details.php?id=$id");
        break;

    default:

        exit('Error!');
        break;

}
