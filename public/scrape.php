<?php
require_once('../include/bittorrent_announce.php');
require ROOT_PATH . 'include/core.php';
//require_once('../include/benc.php');
dbconn_announce();

// BLOCK ACCESS WITH WEB BROWSERS AND CHEATS!
block_browser();

preg_match_all('/info_hash=([^&]*)/i', $_SERVER["QUERY_STRING"], $info_hash_array);
$fields = "info_hash, times_completed, seeders, leechers";

if (count($info_hash_array[1]) < 1) {
    warn("Require info_hash.", 86400);
//	$query = "SELECT $fields FROM torrents ORDER BY id";
}
else {
	$query = "SELECT $fields FROM torrents WHERE " . hash_where_arr('info_hash', $info_hash_array[1]);
}

$cacheKey = md5(http_build_query($info_hash_array[1]));
$cacheData = \Nexus\Database\NexusDB::cache_get($cacheKey);
if ($cacheData) {
    do_log("[SCRAPE_FROM_CACHE]: " . $_SERVER["QUERY_STRING"]);
    benc_resp($cacheData);
    exit(0);
}

$res = sql_query($query);

if (mysql_num_rows($res) < 1){
    warn("Torrent not registered with this tracker.", 86400);
}

$torrent_details = [];
while ($row = mysql_fetch_assoc($res)) {
    $torrent_details[$row['info_hash']] = [
        'complete' => (int)$row['seeders'],
        'downloaded' => (int)$row['times_completed'],
        'incomplete' => (int)$row['leechers']
    ];
}

$d = ['files' => $torrent_details];
\Nexus\Database\NexusDB::cache_put($cacheKey, $d, 1200);
benc_resp($d);
