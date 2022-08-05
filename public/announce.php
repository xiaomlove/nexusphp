<?php
require '../include/bittorrent_announce.php';
$apiLocalHost = nexus_env('TRACKER_API_LOCAL_HOST');
if ($apiLocalHost) {
    do_log("[TRACKER_API_LOCAL_HOST] $apiLocalHost");
    $response = request_local_api(trim($apiLocalHost, '/') . '/api/announce');
    if (empty($response)) {
        err("error from TRACKER_API_LOCAL_HOST");
    } else {
        exit(benc_resp_raw($response));
    }
}
//continue the normal process
require ROOT_PATH . 'include/core.php';
dbconn_announce();
do_log(nexus_json_encode($_SERVER));
//1. BLOCK ACCESS WITH WEB BROWSERS AND CHEATS!
$agent = $_SERVER["HTTP_USER_AGENT"];
block_browser();

//check authkey
if (!empty($_REQUEST['authkey'])) {
    $arr = explode('|', $_REQUEST['authkey']);
    if (count($arr) != 3) {
        err('Invalid authkey');
    }
    $torrentId = $arr[0];
    $uid = $arr[1];
    $torrentRep = new \App\Repositories\TorrentRepository();
    try {
        $decrypted = $torrentRep->checkTrackerReportAuthKey($_REQUEST['authkey']);
    } catch (\Exception $exception) {
        err($exception->getMessage());
    }
    if (empty($decrypted)) {
        err('Invalid authkey');
    }
    $userInfo = \Nexus\Database\NexusDB::remember("announce_user_passkey_$uid", 600, function () use ($uid) {
        return \App\Models\User::query()->where('id', $uid)->first(['id', 'passkey']);
    });
    if (!$userInfo) {
        err('Invalid authkey');
    }
    $_GET['passkey'] = $userInfo->passkey;
}


//2. GET ANNOUNCE VARIABLES
// get string type passkey, info_hash, peer_id, event, ip from client
foreach (array("passkey","info_hash","peer_id","event") as $x)
{
	if(isset($_GET[$x]))
	$GLOBALS[$x] = $_GET[$x];
}
// get integer type port, downloaded, uploaded, left from client
foreach (array("port","downloaded","uploaded","left","compact","no_peer_id") as $x)
{
	$GLOBALS[$x] = intval($_GET[$x] ?? 0);
}
//check info_hash, peer_id and passkey
foreach (array("passkey","info_hash","peer_id","port","downloaded","uploaded","left") as $x)
	if (!isset($x)) err("Missing key: $x");
foreach (array("info_hash","peer_id") as $x)
	if (strlen($GLOBALS[$x]) != 20) err("Invalid $x (" . strlen($GLOBALS[$x]) . " - " . rawurlencode($GLOBALS[$x]) . ")");
if (strlen($passkey) != 32) err("Invalid passkey (" . strlen($passkey) . " - $passkey)");

//4. GET IP AND CHECK PORT
$ip = getip();	// avoid to get the spoof ip from some agent
$_GET['ip'] = $ip;
if (!$port || $port > 0xffff)
	err("invalid port");
if (!ip2long($ip)) //Disable compact announce with IPv6
	$compact = 0;

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
	err("Port $port is blacklisted.");

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
	$res = sql_query("SELECT id, username, downloadpos, enabled, uploaded, downloaded, class, parked, clientselect, showclienterror, passkey, donor, donoruntil FROM users WHERE passkey=". sqlesc($passkey)." LIMIT 1");
	$az = mysql_fetch_array($res);
	do_log("[check passkey], currentUser: " . nexus_json_encode($az));
	$Cache->cache_value('user_passkey_'.$passkey.'_content', $az, 950);
}
if (!$az) err("Invalid passkey! Re-download the .torrent from $BASEURL");
$userid = intval($az['id'] ?? 0);
unset($GLOBALS['CURUSER']);
$CURUSER = $GLOBALS["CURUSER"] = $az;
$isDonor = $az['donor'] == 'yes' && ($az['donoruntil'] === null || $az['donoruntil'] == '0000-00-00 00:00:00' || $az['donoruntil'] > date('Y-m-d H:i:s'));
$az['__is_donor'] = $isDonor;
$log = "user: $userid, isDonor: $isDonor, seeder: $seeder, ip: $ip, ipv4: $ipv4, ipv6: $ipv6";

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
//		sql_query("UPDATE users SET showclienterror = 'yes' WHERE id = ".sqlesc($userid));
        $USERUPDATESET[] = "showclienterror = 'yes'";
		$Cache->delete_value('user_passkey_'.$passkey.'_content');
	}
	err($clicheck_res);
}
elseif ($az['showclienterror'] == 'yes'){
	$USERUPDATESET[] = "showclienterror = 'no'";
	$Cache->delete_value('user_passkey_'.$passkey.'_content');
}

// check torrent based on info_hash
$checkTorrentSql = "SELECT id, size, owner, sp_state, seeders, leechers, UNIX_TIMESTAMP(added) AS ts, banned, hr, approval_status FROM torrents WHERE " . hash_where("info_hash", $info_hash);
if (!$torrent = $Cache->get_value('torrent_hash_'.$info_hash.'_content')){
	$res = sql_query($checkTorrentSql);
	$torrent = mysql_fetch_array($res);
	$Cache->cache_value('torrent_hash_'.$info_hash.'_content', $torrent, 350);
}
if (!$torrent) {
    $firstNeedle = "info_hash=";
    $queryString = $_SERVER['QUERY_STRING'];
    $start = strpos($queryString, $firstNeedle) + strlen($firstNeedle);
    $end = strpos($queryString, "&", $start);
    $infoHashUrlEncode = substr($queryString, $start, $end - $start);
    do_log("[TORRENT NOT EXISTS] $checkTorrentSql, params: $queryString", 'error');
    do_log("[TORRENT NOT EXISTS] infoHashUrlEncode: $infoHashUrlEncode", 'error');

    err("torrent not registered with this tracker");
} elseif ($az['class'] < $seebanned_class) {
    if ($torrent['banned'] == 'yes') {
        err("torrent banned");
    } elseif ($torrent['approval_status'] != \App\Models\Torrent::APPROVAL_STATUS_ALLOW && get_setting('torrent.approval_status_none_visible') == 'no') {
        err("torrent review not approved");
    }
}
// select peers info from peers table for this torrent
$torrentid = $torrent["id"];
$numpeers = $torrent["seeders"]+$torrent["leechers"];

$promotionInfo = apply_filter('torrent_promotion', $torrent);
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

$fields = "seeder, peer_id, ip, ipv4, ipv6, port, uploaded, downloaded, (".TIMENOW." - UNIX_TIMESTAMP(last_action)) AS announcetime, UNIX_TIMESTAMP(prev_action) AS prevts";
//$peerlistsql = "SELECT ".$fields." FROM peers WHERE torrent = ".$torrentid." AND connectable = 'yes' ".$only_leech_query.$limit;
/**
 * return all peers,include connectable no
 * @since 1.6.0-beta12
 */
$peerlistsql = "SELECT ".$fields." FROM peers WHERE torrent = " . $torrentid . $only_leech_query . $limit;

$real_annnounce_interval = $announce_interval;
if ($anninterthreeage && ($anninterthree > $announce_wait) && (TIMENOW - $torrent['ts']) >= ($anninterthreeage * 86400))
$real_annnounce_interval = $anninterthree;
elseif ($annintertwoage && ($annintertwo > $announce_wait) && (TIMENOW - $torrent['ts']) >= ($annintertwoage * 86400))
$real_annnounce_interval = $annintertwo;

//$resp = "d" . benc_str("interval") . "i" . $real_annnounce_interval . "e" . benc_str("min interval") . "i" . $announce_wait . "e". benc_str("complete") . "i" . $torrent["seeders"] . "e" . benc_str("incomplete") . "i" . $torrent["leechers"] . "e" . benc_str("peers");
$rep_dict = [
    "interval" => (int)$real_annnounce_interval,
    "min interval" => (int)$announce_wait,
    "complete" => (int)$torrent["seeders"],
    "incomplete" => (int)$torrent["leechers"],
    "peers" => [],  // By default it is a array object, only when `&compact=1` then it should be a string
    "peers6" => [],
];

if ($compact == 1) {
    $rep_dict['peers'] = '';  // Change `peers` from array to string
    $rep_dict['peers6'] = '';   // If peer use IPv6 address , we should add packed string in `peers6`
}

//check ReAnnounce
$params = $_GET;
unset($params['key'], $params['ip'], $params['ipv4'], $params['ipv6']);
$reAnnounceQuery = http_build_query($params);
$lockKey = md5($reAnnounceQuery);
$log .= ", [CHECK_RE_ANNOUNCE], reAnnounceQuery: $reAnnounceQuery, lockKey: $lockKey";
$redis = $Cache->getRedis();
if (!$redis->set($lockKey, TIMENOW, ['nx', 'ex' => 5])) {
    do_log("$log, [YES_RE_ANNOUNCE]");
    benc_resp($rep_dict);
    exit();
}
$log .= ", [NO_RE_ANNOUNCE]";
unset($self);
$res = sql_query($peerlistsql);
if (isset($event) && $event == "stopped") {
    // Don't fetch peers for stopped event
} else {
    // bencoding the peers info get for this announce
    while ($row = mysql_fetch_assoc($res)) {
        $row["peer_id"] = hash_pad($row["peer_id"]);

        // $peer_id is the announcer's peer_id while $row["peer_id"] is randomly selected from the peers table
        if ($row["peer_id"] === $peer_id) {
            $self = $row;
            continue;
        }

        if ($compact == 1) {
//            $peerField = filter_var($row['ip'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV6) ? 'peers6' : 'peers';
//            $rep_dict[$peerField] .= inet_pton($row["ip"]) . pack("n", $row["port"]);
            if (!empty($row['ipv4'])) {
                $rep_dict['peers'] .= inet_pton($row["ipv4"]) . pack("n", $row["port"]);
            }
            if (!empty($row['ipv6'])) {
                $rep_dict['peers6'] .= inet_pton($row["ipv6"]) . pack("n", $row["port"]);
            }
        } else {
//            $peer = [
//                'ip' => $row["ip"],
//                'port' => (int) $row["port"]
//            ];
//
//            if ($no_peer_id == 1) {
//                $peer['peer id'] = $row["peer_id"];
//            }
//            $rep_dict['peers'][] = $peer;
            if (!empty($row['ipv4'])) {
                $peer = [
                    'peer_id' => $row['peer_id'],
                    'ip' => $row['ipv4'],
                    'port' => (int)$row['port'],
                ];
                if ($no_peer_id) unset($peer['peer_id']);
                $rep_dict['peers'][] = $peer;
            }
            if (!empty($row['ipv6'])) {
                $peer = [
                    'peer_id' => $row['peer_id'],
                    'ip' => $row['ipv6'],
                    'port' => (int)$row['port'],
                ];
                if ($no_peer_id) unset($peer['peer_id']);
                $rep_dict['peers6'][] = $peer;
            }
        }
    }
}
$selfwhere = "torrent = $torrentid AND " . hash_where("peer_id", $peer_id);
//no found in the above random selection
if (!isset($self))
{
	$res = sql_query("SELECT $fields FROM peers WHERE $selfwhere LIMIT 1");
	$row = mysql_fetch_assoc($res);
	if ($row)
	{
		$self = $row;
	}
}

// min announce time
if(isset($self) && empty($_GET['event']) && $self['prevts'] > (TIMENOW - $announce_wait)) {
    do_log(sprintf(
        'timezone: %s, self prevts(%s, %s) > now(%s, %s) - announce_wait(%s)',
        ini_get('date.timezone'), $self['prevts'], date('Y-m-d H:i:s', $self['prevts']), TIMENOW, date('Y-m-d H:i:s', TIMENOW), $announce_wait
    ));
    err('There is a minimum announce time of ' . $announce_wait . ' seconds');
}

$isSeedBoxRuleEnabled = get_setting('seed_box.enabled') == 'yes';
$isIPSeedBox = false;
if ($isSeedBoxRuleEnabled && !($az['class'] >= \App\Models\User::CLASS_VIP || $isDonor)) {
    $isIPSeedBox = isIPSeedBox($ip, $userid);
}
$log .= ", isSeedBoxRuleEnabled: $isSeedBoxRuleEnabled, isIPSeedBox: $isIPSeedBox";

do_log($log);

// current peer_id, or you could say session with tracker not found in table peers
if (!isset($self))
{
	$valid = @mysql_fetch_row(@sql_query("SELECT COUNT(*) FROM peers WHERE torrent=$torrentid AND userid=" . sqlesc($userid)));
	if ($valid[0] >= 1 && $seeder == 'no') err("You already are downloading the same torrent. You may only leech from one location at a time.");
	if ($valid[0] >= 3 && $seeder == 'yes') err("You cannot seed the same torrent from more than 3 locations.");

	if ($az["enabled"] == "no")
	err("Your account is disabled!");
	elseif ($az["parked"] == "yes")
	err("Your account is parked! (Read the FAQ)");
	elseif ($az["downloadpos"] == "no")
	err("Your downloading privileges have been disabled! (Read the rules)");

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
				err("Your ratio is too low! You need to wait " . mkprettytime($wait * 3600 - $elapsed) . " to start, please read $BASEURL/faq.php#id46 for details");
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
				$res = sql_query("SELECT COUNT(*) AS num FROM peers WHERE userid='$userid' AND seeder='no'") or err("Tracker error 5");
				$row = mysql_fetch_assoc($res);
				if ($row['num'] >= $max) err("Your slot limit is reached! You may at most download $max torrents at the same time, please read $BASEURL/faq.php#id66 for details");
			}
		}
	}
}
else // continue an existing session
{
    $snatchInfo = mysql_fetch_assoc(sql_query(sprintf('select * from snatched where torrentid = %s and userid = %s order by id desc limit 1', $torrentid, $userid)));
	$upthis = $trueupthis = max(0, $uploaded - $self["uploaded"]);
	$downthis = $truedownthis = max(0, $downloaded - $self["downloaded"]);
	$announcetime = ($self["seeder"] == "yes" ? "seedtime = seedtime + {$self['announcetime']}" : "leechtime = leechtime + {$self['announcetime']}");
	$is_cheater = false;
    if ($self['announcetime'] > 0 && $isSeedBoxRuleEnabled && !($az['class'] >= \App\Models\User::CLASS_VIP || $isDonor) && !$isIPSeedBox) {
        $notSeedBoxMaxSpeedMbps = get_setting('seed_box.not_seed_box_max_speed');
        $upSpeedMbps = number_format(($trueupthis / $self['announcetime'] / 1024 / 1024) * 8);
        do_log("notSeedBoxMaxSpeedMbps: $notSeedBoxMaxSpeedMbps, upSpeedMbps: $upSpeedMbps");
        if ($upSpeedMbps > $notSeedBoxMaxSpeedMbps) {
            (new \App\Repositories\UserRepository())->updateDownloadPrivileges(null, $userid, 'no');
            do_log("user: $userid downloading privileges have been disabled! (over speed), notSeedBoxMaxSpeedMbps: $notSeedBoxMaxSpeedMbps > upSpeedMbps: $upSpeedMbps", 'error');
            err("Your downloading privileges have been disabled! (over speed)");
        }
    }

	if ($cheaterdet_security){
		if ($az['class'] < $nodetect_security && $self['announcetime'] > 10)
		{
			$is_cheater = check_cheater($userid, $torrent['id'], $upthis, $downthis, $self['announcetime'], $torrent['seeders'], $torrent['leechers']);
		}
	}

	do_log("upthis: $upthis, downthis: $downthis, announcetime: $announcetime, is_cheater: $is_cheater");

	if (!$is_cheater && ($trueupthis > 0 || $truedownthis > 0))
	{
        $dataTraffic = getDataTraffic($torrent, $_GET, $az, $self, $snatchInfo, $promotionInfo);
        $USERUPDATESET[] = "uploaded = uploaded + " . $dataTraffic['uploaded_increment_for_user'];
        $USERUPDATESET[] = "downloaded = downloaded + " . $dataTraffic['downloaded_increment_for_user'];

//        $global_promotion_state = get_global_sp_state();
//        if (isset($torrent['__ignore_global_sp_state']) && $torrent['__ignore_global_sp_state']) {
//            do_log("[IGNORE_GLOBAL_SP_STATE], sp_state: {$torrent['sp_state']}");
//            $global_promotion_state = 1;
//        }
//        if($global_promotion_state == 1)// Normal, see individual torrent
//        {
//            if($torrent['sp_state']==3) //2X
//            {
//                $USERUPDATESET[] = "uploaded = uploaded + 2*$trueupthis";
//                $USERUPDATESET[] = "downloaded = downloaded + $truedownthis";
//            }
//            elseif($torrent['sp_state']==4) //2X Free
//            {
//                $USERUPDATESET[] = "uploaded = uploaded + 2*$trueupthis";
//            }
//            elseif($torrent['sp_state']==6) //2X 50%
//            {
//                $USERUPDATESET[] = "uploaded = uploaded + 2*$trueupthis";
//                $USERUPDATESET[] = "downloaded = downloaded + $truedownthis/2";
//            }
//            else{
//                if ($torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                    $upthis = $trueupthis * $uploaderdouble_torrent;
//
//                if($torrent['sp_state']==2) //Free
//                {
//                    $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//                }
//                elseif($torrent['sp_state']==5) //50%
//                {
//                    $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//                    $USERUPDATESET[] = "downloaded = downloaded + $truedownthis/2";
//                }
//                elseif($torrent['sp_state']==7) //30%
//                {
//                    $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//                    $USERUPDATESET[] = "downloaded = downloaded + $truedownthis*3/10";
//                }
//                elseif($torrent['sp_state']==1) //Normal
//                {
//                    $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//                    $USERUPDATESET[] = "downloaded = downloaded + $truedownthis";
//                }
//            }
//        }
//        elseif($global_promotion_state == 2) //Free
//        {
//            if ($torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//        }
//        elseif($global_promotion_state == 3) //2X
//        {
//            if ($uploaderdouble_torrent > 2 && $torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            else $upthis = 2*$trueupthis;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//            $USERUPDATESET[] = "downloaded = downloaded + $truedownthis";
//        }
//        elseif($global_promotion_state == 4) //2X Free
//        {
//            if ($uploaderdouble_torrent > 2 && $torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            else $upthis = 2*$trueupthis;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//        }
//        elseif($global_promotion_state == 5){ // 50%
//            if ($torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//            $USERUPDATESET[] = "downloaded = downloaded + $truedownthis/2";
//        }
//        elseif($global_promotion_state == 6){ //2X 50%
//            if ($uploaderdouble_torrent > 2 && $torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            else $upthis = 2*$trueupthis;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//            $USERUPDATESET[] = "downloaded = downloaded + $truedownthis/2";
//        }
//        elseif($global_promotion_state == 7){ //30%
//            if ($torrent['owner'] == $userid && $uploaderdouble_torrent > 0)
//                $upthis = $trueupthis * $uploaderdouble_torrent;
//            $USERUPDATESET[] = "uploaded = uploaded + $upthis";
//            $USERUPDATESET[] = "downloaded = downloaded + $truedownthis*3/10";
//        }
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
	sql_query("DELETE FROM peers WHERE $selfwhere") or err("D Err");
	if (mysql_affected_rows())
	{
//		$updateset[] = ($self["seeder"] == "yes" ? "seeders = seeders - 1" : "leechers = leechers - 1");
        $hasChangeSeederLeecher = true;
		sql_query("UPDATE snatched SET uploaded = uploaded + $trueupthis, downloaded = downloaded + $truedownthis, to_go = $left, $announcetime, last_action = ".$dt." WHERE torrentid = $torrentid AND userid = $userid") or err("SL Err 1");
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

	sql_query("UPDATE peers SET ip = ".sqlesc($ip).", port = $port, uploaded = $uploaded, downloaded = $downloaded, to_go = $left, prev_action = last_action, last_action = $dt, seeder = '$seeder', agent = ".sqlesc($agent)." $finished $peerIPV46 WHERE $selfwhere") or err("PL Err 1");

	if (mysql_affected_rows())
	{
		if ($seeder <> $self["seeder"]) {
//            $updateset[] = ($seeder == "yes" ? "seeders = seeders + 1, leechers = leechers - 1" : "seeders = seeders - 1, leechers = leechers + 1");
            $hasChangeSeederLeecher = true;
        }
		if (!empty($snatchInfo)) {
            sql_query("UPDATE snatched SET uploaded = uploaded + $trueupthis, downloaded = downloaded + $truedownthis, to_go = $left, $announcetime, last_action = ".$dt." $finished_snatched WHERE torrentid = $torrentid AND userid = $userid") or err("SL Err 2");
            do_action('snatched_saved', $torrent, $snatchInfo);
            if ($event == 'completed' && $az['class'] < \App\Models\HitAndRun::MINIMUM_IGNORE_USER_CLASS && !$isDonor) {
                //think about H&R
                $hrMode = get_setting('hr.mode');
                if ($hrMode == \App\Models\HitAndRun::MODE_GLOBAL || ($hrMode == \App\Models\HitAndRun::MODE_MANUAL && $torrent['hr'] == \App\Models\Torrent::HR_YES)) {
                    $sql = "insert into hit_and_runs (uid, torrent_id, snatched_id) values ($userid, $torrentid, {$snatchInfo['id']}) on duplicate key update updated_at = " . sqlesc(date('Y-m-d H:i:s'));
                    $affectedRows = sql_query($sql);
                    do_log("[INSERT_H&R], $sql, affectedRows: $affectedRows");
                }
            }
        }
	}
}
else
{
    if ($event != 'stopped') {
        $isPeerExistResultSet = sql_query("select id from peers where $selfwhere limit 1");
        if (mysql_num_rows($isPeerExistResultSet) == 0) {
            $cacheKey = 'peers:connectable:'.$ip.'-'.$port.'-'.$agent;
            $connectable = \Nexus\Database\NexusDB::remember($cacheKey, 3600, function () use ($ip, $port) {
                if (isIPV6($ip)) {
                    $sockres = @fsockopen("tcp://[".$ip."]",$port,$errno,$errstr,1);
                } else {
                    $sockres = @fsockopen($ip, $port, $errno, $errstr, 1);
                }
                if (is_resource($sockres)) {
                    fclose($sockres);
                    return 'yes';
                }
                return 'no';
            });
            $insertPeerSql = "INSERT INTO peers (torrent, userid, peer_id, ip, port, connectable, uploaded, downloaded, to_go, started, last_action, seeder, agent, downloadoffset, uploadoffset, passkey, ipv4, ipv6) VALUES ($torrentid, $userid, ".sqlesc($peer_id).", ".sqlesc($ip).", $port, '$connectable', $uploaded, $downloaded, $left, $dt, $dt, '$seeder', ".sqlesc($agent).", $downloaded, $uploaded, ".sqlesc($passkey).",".sqlesc($ipv4).",".sqlesc($ipv6).")";
            do_log("[INSERT PEER] peer not exists for $selfwhere, do insert with $insertPeerSql");

            try {
                sql_query($insertPeerSql) or err("PL Err 2");
                if (mysql_affected_rows())
                {
//                    $updateset[] = ($seeder == "yes" ? "seeders = seeders + 1" : "leechers = leechers + 1");
                    $hasChangeSeederLeecher = true;
                    $check = @mysql_fetch_row(@sql_query("SELECT COUNT(*) FROM snatched WHERE torrentid = $torrentid AND userid = $userid"));
                    if (!$check['0'])
                        sql_query("INSERT INTO snatched (torrentid, userid, ip, port, uploaded, downloaded, to_go, startdat, last_action) VALUES ($torrentid, $userid, ".sqlesc($ip).", $port, $uploaded, $downloaded, $left, $dt, $dt)") or err("SL Err 4");
                    else
                        sql_query("UPDATE snatched SET to_go = $left, last_action = ".$dt ." WHERE torrentid = $torrentid AND userid = $userid") or err("SL Err 3.1");
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

if (isset($event) && !empty($event)) {
    $updateset[] = 'seeders = ' . get_row_count("peers", "where torrent = $torrentid and to_go = 0");
    $updateset[] = 'leechers = ' . get_row_count("peers", "where torrent = $torrentid and to_go > 0");
}

if (count($updateset) || $hasChangeSeederLeecher) // Update only when there is change in peer counts
{
	$updateset[] = "visible = 'yes'";
	$updateset[] = "last_action = $dt";
	$sql = "UPDATE torrents SET " . join(",", $updateset) . " WHERE id = $torrentid";
	sql_query($sql);
	do_log("[ANNOUNCE_UPDATE_TORRENT], $sql");
}

if($client_familyid != 0 && $client_familyid != $az['clientselect']) {
    $USERUPDATESET[] = "clientselect = ".sqlesc($client_familyid);
}
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
    $sql = "UPDATE users SET " . join(",", $USERUPDATESET) . " WHERE id = ".$userid;
    sql_query($sql);
    do_log("[ANNOUNCE_UPDATE_USER], $sql");
}
do_action('announced', $torrent, $az, $_REQUEST);
benc_resp($rep_dict);
?>
