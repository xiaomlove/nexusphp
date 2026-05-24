<?php

use Rhilip\Bencode\TorrentFile;

require_once("../include/bittorrent.php");
dbconn();
require_once ROOT_PATH . get_langfile_path("functions.php");
require_once(get_langfile_path());
function denyDownload()
{
    permissiondenied();
}
$torrentRep = new \App\Repositories\TorrentRepository();
if (!empty($_REQUEST['downhash'])) {
    $params = explode('.', $_REQUEST['downhash'], 2);
    if (empty($params[0]) || empty($params[1])) {
        die("invalid downhash, format error");
    }
    $uid = $params[0];
    $hash = $params[1];
    $userObj = \Nexus\Database\NexusDB::table('users')->where('id', (int) $uid)->first();
    $user = $userObj ? (array) $userObj : null;
    if (!$user)
        die("invalid uid");
    elseif ($user['enabled'] == 'no' || $user['parked'] == 'yes')
        die("account disabed or parked");
    $oldip = $user['ip'];
    $user['ip'] = getip();
    $CURUSER = $user;
    $decrypted = $torrentRep->decryptDownHash($hash, $user);
    if (empty($decrypted)) {
        do_log("downhash invalid: " . nexus_json_encode($_REQUEST));
        die("invalid downhash, decrpyt fail");
    }
    $id = $decrypted[0];
} elseif (get_setting('torrent.download_support_passkey') == 'yes' && !empty($_REQUEST['passkey']) && !empty($_REQUEST['id'])) {
    $userObj = \Nexus\Database\NexusDB::table('users')->where('passkey', $_REQUEST['passkey'])->first();
    $user = $userObj ? (array) $userObj : null;
    if (!$user)
        die("invalid passkey");
    elseif ($user['enabled'] == 'no' || $user['parked'] == 'yes')
        die("account disabed or parked");
    $oldip = $user['ip'];
    $user['ip'] = getip();
    $CURUSER = $user;
    $id = $_REQUEST['id'];
} else {
    $id = (int)$_GET["id"];
    if (!$id)
        httperr();
	loggedinorreturn();
	parked();
	$letdown = intval($_GET['letdown'] ?? 0);
	if (!$letdown && $CURUSER['showdlnotice'] == 1)
	{
		nexus_redirect(getSchemeAndHttpHost() . "/downloadnotice.php?torrentid=".$id."&type=firsttime");
	}
	elseif (!$letdown && $CURUSER['showclienterror'] == 'yes')
	{
        nexus_redirect(getSchemeAndHttpHost() . "/downloadnotice.php?torrentid=".$id."&type=client");
	}
	elseif (!$letdown && $CURUSER['leechwarn'] == 'yes')
	{
        nexus_redirect(getSchemeAndHttpHost() . "/downloadnotice.php?torrentid=".$id."&type=ratio");
	}
}
\App\Repositories\IpLogRepository::saveToCache($CURUSER['id']);
//User may choose to download torrent from RSS. So update his last_access and ip when downloading torrents.
\Nexus\Database\NexusDB::table('users')->where('id', (int) $CURUSER['id'])->update([
	'last_access' => \Nexus\Database\NexusDB::raw('NOW()'),
	'ip' => (string) $CURUSER['ip'],
]);

/*
@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);

if (@ini_get('output_handler') == 'ob_gzhandler' AND @ob_get_length() !== false)
{	// if output_handler = ob_gzhandler, turn it off and remove the header sent by PHP
	@ob_end_clean();
	header('Content-Encoding:');
}
*/

if ($CURUSER['downloadpos']=="no") {
    denyDownload();
}

//$trackerSchemaAndHost = get_tracker_schema_and_host();
//$ssl_torrent = $trackerSchemaAndHost['ssl_torrent'];
//$base_announce_url = $trackerSchemaAndHost['base_announce_url'];

$rowObj = \Nexus\Database\NexusDB::table('torrents')
	->leftJoin('categories', 'torrents.category', '=', 'categories.id')
	->where('torrents.id', (int) $id)
	->select(['torrents.name', 'torrents.filename', 'torrents.save_as', 'torrents.size', 'torrents.owner', 'torrents.banned', 'torrents.approval_status', 'torrents.price', 'torrents.added', 'categories.mode AS search_box_id'])
	->first();
$row = $rowObj ? (array) $rowObj : null;
if (!$row) {
    do_log("[TORRENT_NOT_EXISTS_IN_DATABASE] $id");
    httperr();
}
$fn = getFullDirectory("$torrent_dir/$id.torrent");
if (!is_file($fn)) {
    do_log("[TORRENT_NOT_EXISTS_IN_PATH] $fn",'error');
    httperr();
}
if (!is_readable($fn)) {
    do_log("[TORRENT_NOT_READABLE] $fn",'error');
    httperr();
}
if (filesize($fn) == 0) {
    do_log("[TORRENT_NOT_VALID_SIZE_ZERO] $fn",'error');
    httperr();
}

$approvalNotAllowed = $row['approval_status'] != \App\Models\Torrent::APPROVAL_STATUS_ALLOW && get_setting('torrent.approval_status_none_visible') == 'no';
$allowOwnerDownload = $row['owner'] == $CURUSER['id'];
$canSeedBanned = user_can('seebanned');
$canAccessTorrent = can_access_torrent($row, $CURUSER['id']);
if ((($row['banned'] == 'yes' || ($approvalNotAllowed && !$allowOwnerDownload)) && !$canSeedBanned) || !$canAccessTorrent) {
    do_log("[DENY_DOWNLOAD], user: {$CURUSER['id']}, approvalNotAllowed: $approvalNotAllowed, allowOwnerDownload: $allowOwnerDownload, canSeedBanned: $canSeedBanned, canAccessTorrent: $canAccessTorrent", 'error');
    denyDownload();
}

/**
 * Migrate to announce.php, due to IYUU will download torrent automatically
 */
//if ($row['price'] > 0 && $CURUSER['id'] != $row['owner']) {
//    $hasBuy = \App\Models\TorrentBuyLog::query()->where('uid', $CURUSER['id'])->where('torrent_id', $id)->exists();
//    if (!$hasBuy) {
//        if ($CURUSER['seedbonus'] < $row['price']) {
//            stderr('Error', nexus_trans('bonus.not_enough', ['require_bonus' => number_format($row['price']), 'now_bonus' => number_format($CURUSER['seedbonus'])]));
//        }
//        $bonusRep = new \App\Repositories\BonusRepository();
//        $bonusRep->consumeToBuyTorrent($CURUSER['id'], $id, 'Web');
//    }
//}

\Nexus\Database\NexusDB::table('torrents')->where('id', (int) $id)->increment('hits');

//require_once "include/benc.php";

if (strlen($CURUSER['passkey']) != 32) {
	$CURUSER['passkey'] = md5($CURUSER['username'].date("Y-m-d H:i:s").$CURUSER['passhash']);
	\Nexus\Database\NexusDB::table('users')->where('id', (int) $CURUSER['id'])->update(['passkey' => (string) $CURUSER['passkey']]);
}
$dict = TorrentFile::load($fn);
$dict->cleanRootFields();
$dict->setAnnounce(get_tracker_schema_and_host($CURUSER['tracker_url_id'], true) . "?passkey=" . $CURUSER['passkey']);
$dict->setComment(getSchemeAndHttpHost(true) . "/details.php?id=" . $id);
$dict->setCreatedBy($SITENAME);
$dict->setCreationDate(strtotime($row['added']));
do_log(sprintf("[ANNOUNCE_URL], user: %s, torrent: %s, url: %s", $CURUSER['id'] ?? '', $id, $dict->getAnnounce()));
/**
 * does not support multi-tracker
 *
 * @see https://github.com/xiaomlove/nexusphp/issues/26
 */
//if (count($announce_urls) > 1) {
//    foreach ($announce_urls as $announce_url) {
//        /** d['announce-list'] = [[ tracker1, tracker2, tracker3 ]] */
//        $dict['announce-list'][0][] = $ssl_torrent . $announce_url . "?authkey=$trackerReportAuthKey";
//        /** d['announce-list'] = [ [tracker1], [backup1], [backup2] ] */
//        //$dict['announce-list'][] = [$ssl_torrent . $announce_url . "?passkey=" . $CURUSER['passkey']];
//    }
//}

//$dict = bdec_file($fn, $max_torrent_size);
//$dict['value']['announce']['value'] = $ssl_torrent . $base_announce_url . "?passkey=$CURUSER[passkey]";
//$dict['value']['announce']['value'] = $ssl_torrent . $base_announce_url . "?authkey=$trackerReportAuthKey";
//$dict['value']['announce']['value'] = getSchemeAndHttpHost() . "/announce.php?authkey=$trackerReportAuthKey";
//$dict['value']['announce']['string'] = strlen($dict['value']['announce']['value']).":".$dict['value']['announce']['value'];
//$dict['value']['announce']['strlen'] = strlen($dict['value']['announce']['string']);
/*if ($announce_urls[1] != "") // add multi-tracker
{
	$dict['value']['announce-list']['type'] = "list";
	$dict['value']['announce-list']['value'][0]['type'] = "list";
	$dict['value']['announce-list']['value'][0]['value'][0]["type"] = "string";
	$dict['value']['announce-list']['value'][0]['value'][0]["value"] = $ssl_torrent . $announce_urls[0] . "?passkey=$CURUSER[passkey]";
	$dict['value']['announce-list']['value'][0]['value'][0]["string"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["value"]).":".$dict['value']['announce-list']['value'][0]['value'][0]["value"];
	$dict['value']['announce-list']['value'][0]['value'][0]["strlen"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["string"]);
	$dict['value']['announce-list']['value'][0]['string'] = "l".$dict['value']['announce-list']['value'][0]['value'][0]["string"]."e";
	$dict['value']['announce-list']['value'][0]['strlen'] = strlen($dict['value']['announce-list']['value'][0]['string']);
	$dict['value']['announce-list']['value'][1]['type'] = "list";
	$dict['value']['announce-list']['value'][1]['value'][0]["type"] = "string";
	$dict['value']['announce-list']['value'][1]['value'][0]["value"] = $ssl_torrent . $announce_urls[1] . "?passkey=$CURUSER[passkey]";
	$dict['value']['announce-list']['value'][1]['value'][0]["string"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["value"]).":".$dict['value']['announce-list']['value'][0]['value'][0]["value"];
	$dict['value']['announce-list']['value'][1]['value'][0]["strlen"] = strlen($dict['value']['announce-list']['value'][0]['value'][0]["string"]);
	$dict['value']['announce-list']['value'][1]['string'] = "l".$dict['value']['announce-list']['value'][0]['value'][0]["string"]."e";
	$dict['value']['announce-list']['value'][1]['strlen'] = strlen($dict['value']['announce-list']['value'][0]['string']);
	$dict['value']['announce-list']['string'] = "l".$dict['value']['announce-list']['value'][0]['string'].$dict['value']['announce-list']['value'][1]['string']."e";
	$dict['value']['announce-list']['strlen'] = strlen($dict['value']['announce-list']['string']);
}*/
/*
header ("Expires: Tue, 1 Jan 1980 00:00:00 GMT");
header ("Last-Modified: ".date("D, d M Y H:i:s"));
header ("Cache-Control: no-store, no-cache, must-revalidate");
header ("Cache-Control: post-check=0, pre-check=0", false);
header ("Pragma: no-cache");
header ("X-Powered-By: ".VERSION." (c) ".date("Y")." ".$SITENAME."");
header ("Accept-Ranges: bytes");
header ("Connection: close");
header ("Content-Transfer-Encoding: binary");
*/

header("Content-Type: application/x-bittorrent");
header("Content-Disposition: " . make_content_disposition($torrentnameprefix . $row["save_as"] . '.torrent'));

//header ("Content-Disposition: attachment; filename=".$row["filename"]."");
//ob_implicit_flush(true);
//print(benc($dict));
echo $dict->dumpToString();
?>
