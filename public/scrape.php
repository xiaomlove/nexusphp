<?php
require_once('../include/bittorrent_announce.php');
require ROOT_PATH . 'include/core.php';
//require_once('../include/benc.php');
dbconn_announce();

// BLOCK ACCESS WITH WEB BROWSERS AND CHEATS!
block_browser();
$passkey = $_GET['passkey'] ?? '';
if (empty($passkey)) {
    err('require passkey');
}
$redis = $Cache->getRedis();
$passkeyInvalidKey = "passkey_invalid";
// check passkey
if (!$az = $Cache->get_value('user_passkey_'.$passkey.'_content')){
    $azObj = \Nexus\Database\NexusDB::table('users')->where('passkey', (string) $passkey)->select(['id', 'username', 'downloadpos', 'enabled', 'uploaded', 'downloaded', 'class', 'parked', 'clientselect', 'showclienterror', 'passkey', 'donor', 'donoruntil', 'seedbonus', 'tracker_url_id'])->first();
    $az = $azObj ? (array) $azObj : null;
    do_log("[check passkey], currentUser: " . nexus_json_encode($az));
    $Cache->cache_value('user_passkey_'.$passkey.'_content', $az, 3600);
}
if (!$az) {
    $redis->set("$passkeyInvalidKey:$passkey", TIMENOW, ['ex' => 24*3600]);
    err("Invalid passkey! Re-download the .torrent from $BASEURL");
}
if ($az["enabled"] == "no")
    err("Your account is disabled!", 300);
elseif ($az["parked"] == "yes")
    err("Your account is parked! (Read the FAQ)", 300);
elseif ($az["downloadpos"] == "no")
    err("Your downloading privileges have been disabled! (Read the rules)", 300);

$userid = intval($az['id'] ?? 0);
unset($GLOBALS['CURUSER']);
$CURUSER = $GLOBALS["CURUSER"] = $az;

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

$scrapeRows = \Nexus\Database\NexusDB::select($query);

if (count($scrapeRows) < 1){
    warn("Torrent not registered with this tracker.", 86400);
}

$torrent_details = [];
foreach ($scrapeRows as $row) {
    $row = (array) $row;
    $torrent_details[$row['info_hash']] = [
        'complete' => (int)$row['seeders'],
        'downloaded' => (int)$row['times_completed'],
        'incomplete' => (int)$row['leechers']
    ];
}

$d = ['files' => $torrent_details];
\Nexus\Database\NexusDB::cache_put($cacheKey, $d, 1200);
benc_resp($d);
