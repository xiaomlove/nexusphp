<?php
require '../include/bittorrent_announce.php';
require ROOT_PATH . 'include/core.php';
//do_log(nexus_json_encode($_SERVER));
//1. BLOCK ACCESS WITH WEB BROWSERS AND CHEATS!
$agent = $_SERVER["HTTP_USER_AGENT"] ?? '';
block_browser();
//2. GET ANNOUNCE VARIABLES
// get string type passkey, info_hash, peer_id, event, ip from client
foreach (array("passkey","info_hash","peer_id","event") as $x)
{
    if(isset($_GET[$x]))
        $GLOBALS[$x] = $_GET[$x];
}
// get integer type port, downloaded, uploaded, left from client
foreach (array("port","downloaded","uploaded","left") as $x)
{
    $GLOBALS[$x] = intval($_GET[$x] ?? 0);
}
//check info_hash, peer_id and passkey
foreach (array("info_hash","peer_id","port","downloaded","uploaded","left") as $x)
    if (!isset($GLOBALS[$x])) warn("Missing key: $x");
foreach (array("info_hash","peer_id") as $x)
    if (strlen($GLOBALS[$x]) != 20) warn("Invalid $x (" . strlen($GLOBALS[$x]) . " - " . rawurlencode($GLOBALS[$x]) . ")");
if (isset($passkey) && strlen($passkey) != 32) warn("Invalid passkey (" . strlen($passkey) . " - $passkey)");

$redis = $Cache->getRedis();
$torrentNotExistsKey = "torrent_not_exists";
$passkeyInvalidKey = "passkey_invalid";
$isReAnnounce = false;
$reAnnounceInterval = 5;
$frequencyInterval = 30;
$isStoppedOrCompleted = !empty($GLOBALS['event']) && in_array($GLOBALS['event'], array('completed', 'stopped'));
$userAuthenticateKey = "";
if (!empty($_GET['passkey'])) {
    $passkey = $userAuthenticateKey = $_GET['passkey'];
    if ($redis->get("$passkeyInvalidKey:$passkey")) {
        $msg = "Passkey invalid";
        do_log("[ANNOUNCE] $msg");
        warn($msg);
    }
    $lockParams = [];
    foreach(['info_hash', 'passkey'] as $lockField) {
        $lockParams[$lockField] = $_GET[$lockField];
    }
    $lockString = http_build_query($lockParams);
    $lockKey = "isReAnnounce:" . md5($lockString);
    if (!$redis->set($lockKey, TIMENOW, ['nx', 'ex' => $reAnnounceInterval])) {
        $isReAnnounce = true;
    }
} else {
    warn("Require passkey");
}

if ($redis->get("$torrentNotExistsKey:$info_hash")) {
    $msg = "Torrent not exists";
    do_log("[ANNOUNCE] $msg");
    err($msg);
}
$infoHashHex = sha1($info_hash);
$torrentReAnnounceKey = sprintf('reAnnounceCheckByInfoHash:%s:%s', $userAuthenticateKey, $infoHashHex);
if (!$isStoppedOrCompleted && !$isReAnnounce && !$redis->set($torrentReAnnounceKey, TIMENOW, ['nx', 'ex' => $frequencyInterval])) {
    $msg = "Request too frequent(h)";
    do_log(sprintf("[ANNOUNCE] %s key: %s already exists, value: %s", $msg, $torrentReAnnounceKey, TIMENOW));
    warn($msg, 300);
}
dbconn_announce();

//4. GET IP AND CHECK PORT
$ip = getip();	// avoid to get the spoof ip from some agent
$_GET['ip'] = $ip;
if (!$port || $port > 0xffff)
	warn("invalid port");

$ipv4 = $ipv6 = '';
if (isIPV4($ip)) {
    $ipv4 = $ip;
} elseif (isset($_GET['ipv4']) && isIPV4($_GET['ipv4'])) {
    $ipv4 = $_GET['ipv4'];
}
if (isIPV6($ip)) {
    $ipv6 = $ip;
} elseif (isset($_GET['ipv6']) && isIPV6($_GET['ipv6'])) {
    $ipv6 = $_GET['ipv6'];
}
$peerIPV46 = "";
if ($ipv4) {
    $peerIPV46 .= ", ipv4 = " . sqlesc($ipv4);
}
if ($ipv6) {
    $peerIPV46 .= ", ipv6 = " . sqlesc($ipv6);
}

// check port and connectable
if (portblacklisted($port))
	warn("Port $port is blacklisted.");

//5. GET PEER LIST
// Number of peers that the client would like to receive from the tracker.This value is permitted to be zero. If omitted, typically defaults to 50 peers.
$rsize = 50;
foreach(array("numwant", "num want", "num_want") as $k)
{
	if (isset($_GET[$k]))
	{
		$rsize = intval($_GET[$k] ?? 0);
		break;
	}
}

// set if seeder based on left field
$seeder = ($left == 0) ? "yes" : "no";

// check passkey
if (!$az = $Cache->get_value('user_passkey_'.$passkey.'_content')){
	$rows = \Nexus\Database\NexusDB::select("SELECT id, username, downloadpos, enabled, uploaded, downloaded, class, parked, clientselect, showclienterror, passkey, donor, donoruntil, seedbonus, tracker_url_id FROM users WHERE passkey=". sqlesc($passkey)." LIMIT 1");
	$az = $rows[0] ?? null;
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
$isDonor = is_donor($az);
$az['__is_donor'] = $isDonor;
$log = "user: $userid, isDonor: $isDonor, seeder: $seeder, ip: $ip, ipv4: $ipv4, ipv6: $ipv6";
//check tracker url
$trackerUrl = get_tracker_schema_and_host($az['tracker_url_id'], true);
$currentUrl = getSchemeAndHttpHost();
if (!str_contains($trackerUrl, $currentUrl)) {
    do_log("announce check tracker url, trackerUrl: $trackerUrl does not contains: $currentUrl");
    warn("you should announce to: $trackerUrl");
}

//3. CHECK IF CLIENT IS ALLOWED
//$clicheck_res = check_client($peer_id,$agent,$client_familyid);
/**
 * refactor check client
 *
 * @since v1.6.0-beta14
 */
$agentAllowRep = new \App\Repositories\AgentAllowRepository();
$clicheck_res = '';
try {
    $checkClientResult = $agentAllowRep->checkClient($peer_id, $agent);
    $client_familyid = $checkClientResult->id;
} catch (\Exception $exception) {
    $clicheck_res = $exception->getMessage();
}

if($clicheck_res){
	if ($az['showclienterror'] == 'no')
	{
		\Nexus\Database\NexusDB::statement("UPDATE users SET showclienterror = 'yes' WHERE id = " . (int) $userid);
		$Cache->delete_value('user_passkey_'.$passkey.'_content');
	}
	err($clicheck_res);
}
elseif ($az['showclienterror'] == 'yes'){
	$USERUPDATESET[] = "showclienterror = 'no'";
	$Cache->delete_value('user_passkey_'.$passkey.'_content');
}

// check torrent based on info_hash
$tsField = \Nexus\Database\NexusDB::unixTimestampField('added');
$infoHashBind = \Nexus\Database\NexusDB::fromHex(':info_hash');
$checkTorrentSql = "SELECT torrents.id, size, owner, sp_state, seeders, leechers, times_completed, $tsField AS ts, added, banned, hr, approval_status, price, categories.mode FROM torrents left join categories on torrents.category = categories.id WHERE info_hash = $infoHashBind limit 1";
if (!$torrent = $Cache->get_value('torrent_hash_'.$info_hash.'_content')){
    $stmt = \Nexus\Database\NexusDB::getInstance()->prepare($checkTorrentSql);
    $stmt->execute(['info_hash' => bin2hex($info_hash)]);
	$torrent = $stmt->fetch(\PDO::FETCH_BOTH) ?: null;
    $Cache->cache_value('torrent_hash_'.$info_hash.'_content', $torrent, 350);
}
if (!$torrent) {
    $firstNeedle = "info_hash=";
    $queryString = $_SERVER['QUERY_STRING'];
    $start = strpos($queryString, $firstNeedle) + strlen($firstNeedle);
    $end = strpos($queryString, "&", $start);
    $infoHashUrlEncode = substr($queryString, $start, $end - $start);
    do_log("[TORRENT NOT EXISTS] $checkTorrentSql, params: $queryString, infoHashUrlEncode: $infoHashUrlEncode");
    $redis->set("$torrentNotExistsKey:$info_hash", TIMENOW, ['ex' => 24*3600]);
    err("torrent not registered with this tracker");
}
$GLOBALS['torrent'] = $torrent;
$torrentid = $torrent["id"];
if ($torrent['banned'] == 'yes') {
    if (!user_can('seebanned', false, $az['id'])) {
        err("torrent banned");
    }
}
if ($torrent['approval_status'] != \App\Models\Torrent::APPROVAL_STATUS_ALLOW && get_setting('torrent.approval_status_none_visible') == 'no') {
    if (!user_can('seebanned', false, $az['id'])) {
        err("torrent review not approved");
    }
}

if ($left > $torrent['size']) {
    //disable download
    (new \App\Repositories\UserRepository())->updateDownloadPrivileges(null, $userid, 'no', 'fake_announce');
    do_log(sprintf("fake announce, user: %s, torrent: %s, announce left: %s > size: %s", $userid, $torrentid, $left, $torrent['size']), 'warn');
    warn("fake announce");
}

// select peers info from peers table for this torrent

$numpeers = $torrent["seeders"]+$torrent["leechers"];

$log .= ", torrent: $torrentid";
if ($seeder == 'yes'){ //Don't report seeds to other seeders
	$only_leech_query = " AND seeder = 'no' ";
	$newnumpeers = $torrent["leechers"];
}
else{
	$only_leech_query = "";
	$newnumpeers = $numpeers;
}
if ($newnumpeers > $rsize)
	$limit = " ORDER BY RAND() LIMIT $rsize";
else $limit = "";

$announce_wait = \App\Repositories\TrackerRepository::MIN_ANNOUNCE_WAIT_SECOND;
$lastActionField = \Nexus\Database\NexusDB::unixTimestampField('last_action');
$prevActionField = \Nexus\Database\NexusDB::unixTimestampField('prev_action');
$fields = "id, seeder, peer_id, ip, ipv4, ipv6, port, uploaded, downloaded, userid, last_action, $lastActionField as last_action_unix_timestamp, prev_action, (".TIMENOW." - $lastActionField) AS announcetime, $prevActionField AS prevts";
//$peerlistsql = "SELECT ".$fields." FROM peers WHERE torrent = ".$torrentid." AND connectable = 'yes' ".$only_leech_query.$limit;
/**
 * return all peers,include connectable no
 * @since 1.6.0-beta12
 */
$peerlistsql = "SELECT ".$fields." FROM peers WHERE torrent = " . $torrentid . $only_leech_query . $limit;

$announce_one_begin = (0+$announce_interval)/2;
$announce_one_end = ($announce_interval+$annintertwo)/2;
$announce_two_end = ($annintertwo+$anninterthree)/2;
$announce_three_end = $anninterthree;//can not bigger, cleanup will consider dead and delete it

$real_annnounce_interval = mt_rand($announce_one_begin, $announce_one_end);
if ($anninterthreeage && ($anninterthree > $announce_wait) && (TIMENOW - $torrent['ts']) >= ($anninterthreeage * 86400))
$real_annnounce_interval = mt_rand($announce_two_end, $announce_three_end);
elseif ($annintertwoage && ($annintertwo > $announce_wait) && (TIMENOW - $torrent['ts']) >= ($annintertwoage * 86400))
$real_annnounce_interval = mt_rand($announce_one_end, $announce_two_end);

//$resp = "d" . benc_str("interval") . "i" . $real_annnounce_interval . "e" . benc_str("min interval") . "i" . $announce_wait . "e". benc_str("complete") . "i" . $torrent["seeders"] . "e" . benc_str("incomplete") . "i" . $torrent["leechers"] . "e" . benc_str("peers");
$rep_dict = [
    "interval" => (int)$real_annnounce_interval,
    "min interval" => (int)$announce_wait,
    "complete" => (int)$torrent["seeders"],
    "incomplete" => (int)$torrent["leechers"],
    "downloaded" => (int)$torrent["times_completed"],
    "peers" => '',
    "peers6" => '',
];

$GLOBALS['rep_dict'] = $rep_dict;
if ($isReAnnounce) {
    do_log("$log, [YES_RE_ANNOUNCE]");
    benc_resp($rep_dict);
    exit();
}
$log .= ", [NO_RE_ANNOUNCE]";
unset($self);
$peerRows = \Nexus\Database\NexusDB::select($peerlistsql);
if (isset($event) && $event == "stopped") {
    // Don't fetch peers for stopped event
} else {
    // bencoding the peers info get for this announce
    foreach ($peerRows as $row) {
        $row["peer_id"] = hash_pad($row["peer_id"]);

        // $peer_id is the announcer's peer_id while $row["peer_id"] is randomly selected from the peers table
        if ($row["peer_id"] === $peer_id && $row['userid'] == $userid) {
            $self = $row;
            continue;
        }

        if (!empty($row['ipv4'])) {
            $rep_dict['peers'] .= inet_pton($row["ipv4"]) . pack("n", $row["port"]);
        }
        if (!empty($row['ipv6'])) {
            $rep_dict['peers6'] .= inet_pton($row["ipv6"]) . pack("n", $row["port"]);
        }
    }
}
$peerIdBind = \Nexus\Database\NexusDB::fromHex(':peer_id');
$selfwhere = "torrent = $torrentid AND peer_id = $peerIdBind AND userid = $userid";
//no found in the above random selection
if (!isset($self))
{
	$stmt = \Nexus\Database\NexusDB::getInstance()->prepare("SELECT $fields FROM peers WHERE $selfwhere LIMIT 1");
    $stmt->execute(['peer_id' => bin2hex($peer_id)]);
	$row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
	if ($row)
	{
		$self = $row;
	}
}
if (isset($self)) {
    $log .= sprintf(
        ', [SELF], TIMENOW: %s(%s), last_action: %s, last_action_unix_timestamp: %s, announcetime: %s, prev_action: %s, prevts: %s',
        TIMENOW, date('Y-m-d H:i:s', TIMENOW), $self['last_action'], $self['last_action_unix_timestamp'], $self['announcetime'], $self['prev_action'], $self['prevts']
    );
}

// min announce time
if(isset($self) && empty($_GET['event']) && $self['prevts'] > (TIMENOW - $announce_wait)) {
    do_log($log);
    do_log(sprintf(
        'timezone: %s, self prevts(%s -> %s, %s) > now(%s, %s) - announce_wait(%s)',
        ini_get('date.timezone'), $self['prev_action'], $self['prevts'], date('Y-m-d H:i:s', $self['prevts']), TIMENOW, date('Y-m-d H:i:s', TIMENOW), $announce_wait
    ));
    warn('There is a minimum announce time of ' . $announce_wait . ' seconds', $announce_wait);
}

$isSeedBoxRuleEnabled = get_setting('seed_box.enabled') == 'yes';
$isIPSeedBox = false;
if ($isSeedBoxRuleEnabled) {
    if (!empty($ipv4)) {
        $isIPSeedBox = isIPSeedBox($ipv4, $userid);
    }
    if (!$isIPSeedBox && !empty($ipv6)) {
        $isIPSeedBox = isIPSeedBox($ipv6, $userid);
    }
}
$log .= ", [SEED_BOX], isSeedBoxRuleEnabled: $isSeedBoxRuleEnabled, isIPSeedBox: $isIPSeedBox";

do_log($log);

//handle paid torrent
if (
    $seeder == 'no'
    && isset($az['seedbonus'])
    && isset($torrent['price'])
    && $torrent['price'] > 0
    && $torrent['owner'] != $userid
    && get_setting("torrent.paid_torrent_enabled") == "yes"
) {
    $torrentRep = new \App\Repositories\TorrentRepository();
    $buyStatus = $torrentRep->getBuyStatus($userid, $torrentid);
    do_log("user: $userid buy torrent: $torrentid, status: $buyStatus");
    if ($buyStatus > 0) {
        do_log(sprintf("user: %s buy torrent： %s fail count: %s", $userid, $torrentid, $buyStatus), "error");
        if ($buyStatus > 3) {
            //warn
            \App\Utils\MsgAlert::getInstance()->add(
                "announce_paid_torrent_too_many_times",
                time() + 86400,
                "announce to paid torrent and fail too many times, please make sure you have enough bonus!",
                "",
                "black"
            );
        }
        if ($buyStatus > 10) {
            //disable download
            (new \App\Repositories\UserRepository())->updateDownloadPrivileges(null, $userid, 'no', 'announce_paid_torrent_too_many_times');
        }
        \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent($userid, $torrentid));
        //already fail, add fail times
        $torrentRep->addBuyFailCache($userid, $torrentid);
        warn("purchase in progress, please try again later, and make sure you have enough bonus", 300);
    }
    if ($buyStatus == \App\Repositories\TorrentRepository::BUY_STATUS_UNKNOWN) {
        //just enqueue job
        \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent($userid, $torrentid));
        warn("purchase started, please wait", 300);
    }
}

$leechTimeNoSeeder = "";

// current peer_id, or you could say session with tracker not found in table peers
if (!isset($self))
{
    $sameIPRows = \Nexus\Database\NexusDB::select("select id from peers where torrent = " . (int) $torrentid . " and userid = " . (int) $userid . " and ip = " . sqlesc($ip) . " limit 1");
    $sameIPRecord = $sameIPRows[0] ?? null;
    if (!empty($sameIPRecord) && $seeder == 'yes') {
        warn("You cannot seed the same torrent in the same location from more than 1 client.", 300);
    }
	$validRows = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS cnt FROM peers WHERE torrent=" . (int) $torrentid . " AND userid=" . (int) $userid);
	$validCount = (int) ($validRows[0]['cnt'] ?? 0);
	if ($validCount >= 1 && $seeder == 'no') err("You already are downloading the same torrent. You may only leech from one location at a time.", 300);
	if ($validCount >= 3 && $seeder == 'yes') err("You cannot seed the same torrent from more than 3 locations.", 300);

	if ($az["class"] < UC_VIP)
	{
		$ratio = (($az["downloaded"] > 0) ? ($az["uploaded"] / $az["downloaded"]) : 1);
		$gigs = $az["downloaded"] / (1024*1024*1024);
		if ($waitsystem == "yes")
		{
			if($gigs > 10)
			{
				$elapsed = strtotime(date("Y-m-d H:i:s")) - $torrent["ts"];
				if ($ratio < 0.4) $wait = 24;
				elseif ($ratio < 0.5) $wait = 12;
				elseif ($ratio < 0.6) $wait = 6;
				elseif ($ratio < 0.8) $wait = 3;
				else $wait = 0;

				if ($elapsed < $wait)
				warn("Your ratio is too low! You need to wait " . mkprettytime($wait * 3600 - $elapsed) . " to start, please read $BASEURL/faq.php#id46 for details", $elapsed);
			}
		}
		if ($maxdlsystem == "yes")
		{
			if($gigs > 10)
			if ($ratio < 0.5) $max = 1;
			elseif ($ratio < 0.65) $max = 2;
			elseif ($ratio < 0.8) $max = 3;
			elseif ($ratio < 0.95) $max = 4;
			else $max = 0;
			if ($max > 0)
			{
				$slotRows = \Nexus\Database\NexusDB::select("SELECT COUNT(*) AS num FROM peers WHERE userid=" . (int) $userid . " AND seeder='no'");
				$row = $slotRows[0] ?? ['num' => 0];
				if ($row['num'] >= $max) err("Your slot limit is reached! You may at most download $max torrents at the same time, please read $BASEURL/faq.php#id66 for details");
			}
		}
	}
}
else // continue an existing session
{
	$upthis = $trueupthis = max(0, $uploaded - $self["uploaded"]);
	$downthis = $truedownthis = max(0, $downloaded - $self["downloaded"]);
	$announcetime = ($self["seeder"] == "yes" ? "seedtime = seedtime + {$self['announcetime']}" : "leechtime = leechtime + {$self['announcetime']}");
	$is_cheater = false;
    if ($self['announcetime'] > 0 && $isSeedBoxRuleEnabled && !($az['class'] >= \App\Models\User::CLASS_VIP || $isDonor) && !$isIPSeedBox) {
        $notSeedBoxMaxSpeedMbps = get_setting('seed_box.not_seed_box_max_speed');
        $upSpeedMbps = number_format(($trueupthis / $self['announcetime'] / 1024 / 1024) * 8);
        do_log("notSeedBoxMaxSpeedMbps: $notSeedBoxMaxSpeedMbps, upSpeedMbps: $upSpeedMbps");
        if ($upSpeedMbps > $notSeedBoxMaxSpeedMbps) {
            (new \App\Repositories\UserRepository())->updateDownloadPrivileges(null, $userid, 'no', 'upload_over_speed');
            do_log("user: $userid downloading privileges have been disabled! (over speed), upSpeedMbps: $upSpeedMbps > notSeedBoxMaxSpeedMbps: $notSeedBoxMaxSpeedMbps", 'error');
            warn("Your downloading privileges have been disabled! (over speed)", 300);
        }
    }

	if ($cheaterdet_security){
		if ($az['class'] < $nodetect_security && $self['announcetime'] > 10)
		{
			$is_cheater = check_cheater($userid, $torrent['id'], $upthis, $downthis, $self['announcetime'], $torrent['seeders'], $torrent['leechers']);
		}
	}

	do_log("upthis: $upthis, downthis: $downthis, announcetime: $announcetime, is_cheater: $is_cheater");
    if (!isset($snatchInfo)) {
        $snatchInfo = get_snatch_info($torrentid, $userid);
    }
	if (!$is_cheater && ($trueupthis > 0 || $truedownthis > 0))
	{
        $dataTraffic = getDataTraffic($torrent, $_GET, $az, $self, $snatchInfo, apply_filter('torrent_promotion', $torrent));
        $USERUPDATESET[] = "uploaded = uploaded + " . $dataTraffic['uploaded_increment_for_user'];
        $USERUPDATESET[] = "downloaded = downloaded + " . $dataTraffic['downloaded_increment_for_user'];
	}
    if ($torrent['seeders'] <= 0 && $seeder == 'no' && $self['announcetime'] > 0) {
        $leechTimeNoSeeder = ", leech_time_no_seeder = leech_time_no_seeder + {$self['announcetime']}";
    }
}

$dt = sqlesc(date("Y-m-d H:i:s"));
$updateset = array();
$hasChangeSeederLeecher = false;
// set non-type event
if (!isset($event))
	$event = "";
if (isset($self) && $event == "stopped")
{
	\Nexus\Database\NexusDB::statement("DELETE FROM peers WHERE id = " . (int) $self['id']);
	if (\Nexus\Database\NexusDB::getInstance()->affectedRows() && !empty($snatchInfo))
	{
		$updateset[] = ($self["seeder"] == "yes" ? "seeders = seeders - 1" : "leechers = leechers - 1");
        $hasChangeSeederLeecher = true;
		\Nexus\Database\NexusDB::statement("UPDATE snatched SET uploaded = uploaded + $trueupthis, downloaded = downloaded + $truedownthis, to_go = $left, $announcetime $leechTimeNoSeeder, last_action = ".$dt." WHERE id = " . (int) $snatchInfo['id']);
	}
}
elseif(isset($self))
{
	$finished = $finished_snatched = '';
	if ($event == "completed")
	{
		//sql_query("UPDATE snatched SET  finished  = 'yes', completedat = $dt WHERE torrentid = $torrentid AND userid = $userid");
		$finished .= ", finishedat = ".TIMENOW;
		$finished_snatched = ", completedat = ".$dt . ", finished  = 'yes'";
		$updateset[] = "times_completed = times_completed + 1";
	}

	\Nexus\Database\NexusDB::statement("UPDATE peers SET ip = ".sqlesc($ip).", port = $port, uploaded = $uploaded, downloaded = $downloaded, to_go = $left, prev_action = last_action, last_action = $dt, seeder = '$seeder', agent = ".sqlesc($agent).", is_seed_box = ". intval($isIPSeedBox) . " $finished $peerIPV46 WHERE id = " . (int) $self['id']);

	if (\Nexus\Database\NexusDB::getInstance()->affectedRows())
	{
		if ($seeder <> $self["seeder"]) {
            $updateset[] = ($seeder == "yes" ? "seeders = seeders + 1, leechers = leechers - 1" : "seeders = seeders - 1, leechers = leechers + 1");
            $hasChangeSeederLeecher = true;
        }
		if (!empty($snatchInfo)) {
            \Nexus\Database\NexusDB::statement("UPDATE snatched SET uploaded = uploaded + $trueupthis, downloaded = downloaded + $truedownthis, to_go = $left, $announcetime, last_action = ".$dt." $finished_snatched $leechTimeNoSeeder WHERE id = " . (int) $snatchInfo['id']);
            do_action('snatched_saved', $torrent, $snatchInfo);
        }
	}
}
else
{
    if ($event != 'stopped') {
        $stmt = \Nexus\Database\NexusDB::getInstance()->prepare("select id from peers where $selfwhere limit 1");
        $stmt->execute(['peer_id' => bin2hex($peer_id)]);
        $isPeerExistResultSet = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (empty($isPeerExistResultSet)) {
            $connectable = "yes";
            $insertPeerSql = "INSERT INTO peers (torrent, userid, peer_id, ip, port, connectable, uploaded, downloaded, to_go, started, last_action, seeder, agent, downloadoffset, uploadoffset, passkey, ipv4, ipv6, is_seed_box) VALUES (" . (int) $torrentid . ", " . (int) $userid . ", ".sqlesc($peer_id).", ".sqlesc($ip).", $port, '$connectable', $uploaded, $downloaded, $left, $dt, $dt, '$seeder', ".sqlesc($agent).", $downloaded, $uploaded, ".sqlesc($passkey).", ".sqlesc($ipv4).", ".sqlesc($ipv6).", ".intval($isIPSeedBox).")";
            do_log("[INSERT PEER] peer not exists for $selfwhere, do insert with $insertPeerSql");

            try {
                \Nexus\Database\NexusDB::statement($insertPeerSql);
                if (\Nexus\Database\NexusDB::getInstance()->affectedRows())
                {
                    $updateset[] = ($seeder == "yes" ? "seeders = seeders + 1" : "leechers = leechers + 1");
                    $hasChangeSeederLeecher = true;
                    $checkSnatchedRows = \Nexus\Database\NexusDB::select("SELECT id FROM snatched WHERE torrentid = " . (int) $torrentid . " AND userid = " . (int) $userid . " limit 1");
                    $checkSnatchedRes = $checkSnatchedRows[0] ?? null;
                    if (empty($checkSnatchedRes['id']))
                        \Nexus\Database\NexusDB::statement("INSERT INTO snatched (torrentid, userid, ip, port, uploaded, downloaded, to_go, startdat, last_action) VALUES (" . (int) $torrentid . ", " . (int) $userid . ", ".sqlesc($ip).", $port, $uploaded, $downloaded, $left, $dt, $dt)");
                    else
                        \Nexus\Database\NexusDB::statement("UPDATE snatched SET to_go = $left, last_action = ".$dt ." WHERE id = " . (int) $checkSnatchedRes['id']);
                }
            } catch (\Exception $exception) {
                do_log("[INSERT PEER] error: " . $exception->getMessage());
            }
        } else {
            do_log("[INSERT PEER] peer already exists for $selfwhere.");
        }
    } else {
        do_log("[INSERT PEER] event = 'stopped', ignore.");
    }

}

//handle hr
if (($left > 0 || $event == "completed") && $az['class'] < \App\Models\HitAndRun::MINIMUM_IGNORE_USER_CLASS && !$isDonor && isset($torrent['mode'])) {
    $hrMode = \App\Models\HitAndRun::getConfig('mode', $torrent['mode']);
    $hrLog = sprintf("[HR_LOG] user: %d, torrent: %d, hrMode: %s", $userid, $torrentid, $hrMode);
    if ($hrMode == \App\Models\HitAndRun::MODE_GLOBAL || ($hrMode == \App\Models\HitAndRun::MODE_MANUAL && $torrent['hr'] == \App\Models\Torrent::HR_YES)) {
        //change key to expire cache, so ttl don't set too long
        $hrCacheKey = \App\Models\HitAndRun::getCacheKey( $userid, $torrentid);
        $hrExists = \Nexus\Database\NexusDB::remember($hrCacheKey, mt_rand(86400, 86400*3), function () use ($torrentid, $userid) {
            $record = \App\Models\HitAndRun::query()->where("uid", $userid)->where("torrent_id", $torrentid)->first();
            return $record ? $record->toJson() : null;
        });
        $hrLog .= ", hrExists: $hrExists";
        if (!$hrExists) {
            //last check include rate
            $includeRate = (float)\App\Models\HitAndRun::getConfig('include_rate', $torrent['mode']);
//            if ($includeRate === "" || $includeRate === null) {
//                //not set yet
//                $includeRate = 1;
//            }
            $hrLog .= ", includeRate: $includeRate";
            //get newest snatch info
            if (!isset($snatchInfo)) {
                $snatchInfo = get_snatch_info($torrentid, $userid);
            }
            $requiredDownloaded = $torrent['size'] * $includeRate;
            if ($snatchInfo['downloaded'] >= $requiredDownloaded) {
                $nowStr = date('Y-m-d H:i:s');
                $sql = sprintf(
                    "insert into hit_and_runs (uid, torrent_id, snatched_id, created_at, updated_at) values (%d, %d, %d, '%s', '%s') %s",
                    $userid, $torrentid, $snatchInfo['id'], $nowStr, $nowStr, \Nexus\Database\NexusDB::upsertField(['uid', 'torrent_id'], ['updated_at'])
                );
                \Nexus\Database\NexusDB::statement($sql);
                $affectedRows = \Nexus\Database\NexusDB::getInstance()->affectedRows();
                $hitAndRunId = \Nexus\Database\NexusDB::getInstance()->lastInsertId();
                do_log("$hrLog, total downloaded: {$snatchInfo['downloaded']} >= required: $requiredDownloaded, [INSERT_H&R], sql: $sql, affectedRows: $affectedRows, hitAndRunId: $hitAndRunId");
                if ($hitAndRunId > 0) {
                    \Nexus\Database\NexusDB::statement("update snatched set hit_and_run_id = " . (int) $hitAndRunId . " where id = " . (int) $snatchInfo['id']);
                    $hitAndRunRecord = \App\Models\HitAndRun::query()->where("uid", $userid)->where("torrent_id", $torrentid)->first();
                    fire_event(\App\Enums\ModelEventEnum::HIT_AND_RUN_CREATED, $hitAndRunRecord);
                }
            } else {
                do_log("$hrLog, total downloaded: {$snatchInfo['downloaded']} < required: $requiredDownloaded", "debug");
            }
        } else {
            do_log("$hrLog, already exists", 'debug');
        }
    } else {
        do_log("$hrLog, not match", "debug");
    }
}

// revert to only increment/decrement
//if (isset($event) && !empty($event)) {
//    $updateset[] = 'seeders = ' . get_row_count("peers", "where torrent = $torrentid and to_go = 0");
//    $updateset[] = 'leechers = ' . get_row_count("peers", "where torrent = $torrentid and to_go > 0");
//}

if (count($updateset) || $hasChangeSeederLeecher) // Update only when there is change in peer counts
{
	$updateset[] = "visible = 'yes'";
	$updateset[] = "last_action = $dt";
	$sql = "UPDATE torrents SET " . join(",", $updateset) . " WHERE id = " . (int) $torrentid;
	\Nexus\Database\NexusDB::statement($sql);
	do_log("[ANNOUNCE_UPDATE_TORRENT], $sql");
}

if($client_familyid != 0 && $client_familyid != $az['clientselect']) {
    $USERUPDATESET[] = "clientselect = ".sqlesc($client_familyid);
}
$USERUPDATESET[] = "last_announce_at = $dt";
/**
 * VIP do not calculate downloaded
 * @since 1.7.13
 */
if ($az['class'] == UC_VIP) {
    foreach ($USERUPDATESET as $key => $value) {
        if (str_contains($value, 'downloaded')) {
            unset($USERUPDATESET[$key]);
        }
    }
}
if(count($USERUPDATESET) && $userid)
{
    $sql = "UPDATE users SET " . join(",", $USERUPDATESET) . " WHERE id = " . (int) $userid;
    \Nexus\Database\NexusDB::statement($sql);
    do_log("[ANNOUNCE_UPDATE_USER], $sql");
}
$lockKey = sprintf("record_batch_lock:%s:%s", $userid, $torrentid);
if ($redis->set($lockKey, TIMENOW, ['nx', 'ex' => $autoclean_interval_one])) {
    \App\Repositories\CleanupRepository::recordBatch($redis, $userid, $torrentid);
    \App\Repositories\IpLogRepository::saveToCache($userid, null, [$ip]);
}
if (\App\Repositories\RequireSeedTorrentRepository::shouldRecordUser($redis, $userid, $torrentid)) {
    if (!isset($snatchInfo)) {
        $snatchInfo = get_snatch_info($torrentid, $userid);
    }
    \App\Repositories\RequireSeedTorrentRepository::recordUser($redis, $userid, $torrentid, $snatchInfo);
}
do_action('announced', $torrent, $az, $_REQUEST);
benc_resp($rep_dict);
?>
