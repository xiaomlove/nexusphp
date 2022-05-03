<?php
//require_once("../include/benc.php");
require_once("../include/bittorrent.php");

ini_set("upload_max_filesize",$max_torrent_size);
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("",true));
loggedinorreturn();

function bark($msg) {
	global $lang_takeupload;
	genbark($msg, $lang_takeupload['std_upload_failed']);
	die;
}


if ($CURUSER["uploadpos"] == 'no')
	die;

foreach(explode(":","descr:type:name") as $v) {
	if (!isset($_POST[$v]))
	bark($lang_takeupload['std_missing_form_data']);
}

if (!isset($_FILES["file"]))
bark($lang_takeupload['std_missing_form_data']);

$f = $_FILES["file"];
$fname = unesc($f["name"]);
if (empty($fname))
bark($lang_takeupload['std_empty_filename']);
if (get_user_class()>=$beanonymous_class && isset($_POST['uplver']) && $_POST['uplver'] == 'yes') {
	$anonymous = "yes";
	$anon = "Anonymous";
}
else {
	$anonymous = "no";
	$anon = $CURUSER["username"];
}

$url = parse_imdb_id($_POST['url'] ?? '');

$nfo = '';
if ($enablenfo_main=='yes'){
$nfofile = $_FILES['nfo'];
if ($nfofile['name'] != '') {

	if ($nfofile['size'] == 0)
	bark($lang_takeupload['std_zero_byte_nfo']);

	if ($nfofile['size'] > 65535)
	bark($lang_takeupload['std_nfo_too_big']);

	$nfofilename = $nfofile['tmp_name'];

	if (@!is_uploaded_file($nfofilename))
	bark($lang_takeupload['std_nfo_upload_failed']);
	$nfo = str_replace("\x0d\x0d\x0a", "\x0d\x0a", @file_get_contents($nfofilename));
}
}


$small_descr = unesc($_POST["small_descr"] ?? '');

$descr = unesc($_POST["descr"]);
if (!$descr)
bark($lang_takeupload['std_blank_description']);

$catid = intval($_POST["type"] ?? 0);
$sourceid = intval($_POST["source_sel"] ?? 0);
$mediumid = intval($_POST["medium_sel"] ?? 0);
$codecid = intval($_POST["codec_sel"] ?? 0);
$standardid = intval($_POST["standard_sel"] ?? 0);
$processingid = intval($_POST["processing_sel"] ?? 0);
$teamid = intval($_POST["team_sel"] ?? 0);
$audiocodecid = intval($_POST["audiocodec_sel"] ?? 0);

if (!is_valid_id($catid))
bark($lang_takeupload['std_category_unselected']);

if (!validfilename($fname))
bark($lang_takeupload['std_invalid_filename']);
if (!preg_match('/^(.+)\.torrent$/si', $fname, $matches))
bark($lang_takeupload['std_filename_not_torrent']);
$shortfname = $torrent = $matches[1];
if (!empty($_POST["name"]))
$torrent = unesc($_POST["name"]);
if ($f['size'] > $max_torrent_size)
bark($lang_takeupload['std_torrent_file_too_big'].number_format($max_torrent_size).$lang_takeupload['std_remake_torrent_note']);
$tmpname = $f["tmp_name"];
if (!is_uploaded_file($tmpname))
bark("eek");
if (!filesize($tmpname))
bark($lang_takeupload['std_empty_file']);

try {
    $dict = \Rhilip\Bencode\Bencode::load($tmpname);
} catch (\Rhilip\Bencode\ParseErrorException $e) {
    bark($lang_takeupload['std_not_bencoded_file']);
}

function checkTorrentDict($dict, $key, $type = null)
{
    global $lang_takeupload;

    if (!is_array($dict)) bark($lang_takeupload['std_not_a_dictionary']);
    $value = $dict[$key];
    if (!isset($value)) bark($lang_takeupload['std_dictionary_is_missing_key']);
    if (!is_null($type)) {
        $isFunction = 'is_' . $type;
        if (function_exists($isFunction) && !$isFunction($value)) {
            bark($lang_takeupload['std_invalid_entry_in_dictionary']);
        }
    }
    return $value;
}

$info = checkTorrentDict($dict, 'info');
if (isset($dict['piece layers']) || isset($info['files tree']) || (isset($info['meta version']) && $info['meta version'] == 2)) {
    bark('Torrent files created with Bittorrent Protocol v2, or hybrid torrents are not supported.');
}
$plen = checkTorrentDict($info, 'piece length', 'integer');  // Only Check without use
$dname = checkTorrentDict($info, 'name', 'string');
$pieces = checkTorrentDict($info, 'pieces', 'string');

if (strlen($pieces) % 20 != 0)
bark($lang_takeupload['std_invalid_pieces']);

$filelist = array();
$totallen = $info['length'];
if (isset($totallen)) {
	$filelist[] = array($dname, $totallen);
	$type = "single";
}
else {
    $flist = checkTorrentDict($info, 'files', 'array');

    if (!isset($flist)) bark($lang_takeupload['std_missing_length_and_files']);
    if (!count($flist)) bark("no files");

    $totallen = 0;
    foreach ($flist as $fn) {
        $ll = checkTorrentDict($fn, 'length', 'integer');
        $path_key = isset($fn['path.utf-8']) ? 'path.utf-8' : 'path';
        $ff = checkTorrentDict($fn, $path_key, 'list');

        $totallen += $ll;
        $ffa = array();
        foreach ($ff as $ffe) {
            if (!is_string($ffe)) bark($lang_takeupload['std_filename_errors']);
            $ffa[] = $ffe;
        }

        if (!count($ffa)) bark($lang_takeupload['std_filename_errors']);
        $ffe = implode("/", $ffa);
        $filelist[] = array($ffe, $ll);
    }
    $type = "multi";
}

$dict['announce'] = get_protocol_prefix() . $announce_urls[0];  // change announce url to local
$dict['info']['private'] = 1;
//The following line requires uploader to re-download torrents after uploading
//even the torrent is set as private and with uploader's passkey in it.
$dict['info']['source'] = "[$BASEURL] $SITENAME";
unset ($dict['announce-list']); // remove multi-tracker capability
unset ($dict['nodes']); // remove cached peers (Bitcomet & Azareus)

$infohash = pack("H*", sha1(\Rhilip\Bencode\Bencode::encode($dict['info']))); // double up on the becoding solves the occassional misgenerated infohash

if (\App\Models\Torrent::query()->where('info_hash', $infohash)->exists()) {
    bark($lang_takeupload['std_torrent_existed']);
}

// ------------- start: check upload authority ------------------//
$allowtorrents = user_can_upload("torrents");
$allowspecial = user_can_upload("music");

$catmod = get_single_value("categories","mode","WHERE id=".sqlesc($catid));
$offerid = intval($_POST['offer'] ?? 0);
$is_offer=false;
if ($browsecatmode != $specialcatmode && $catmod == $specialcatmode){//upload to special section
	if (!$allowspecial)
		bark($lang_takeupload['std_unauthorized_upload_freely']);
}
elseif($catmod == $browsecatmode){//upload to torrents section
 	if ($offerid){//it is a offer
		$allowed_offer_count = get_row_count("offers","WHERE allowed='allowed' AND userid=".sqlesc($CURUSER["id"]));
		if ($allowed_offer_count && $enableoffer == 'yes'){
				$allowed_offer = get_row_count("offers","WHERE id=".sqlesc($offerid)." AND allowed='allowed' AND userid=".sqlesc($CURUSER["id"]));
				if ($allowed_offer != 1)//user uploaded torrent that is not an allowed offer
					bark($lang_takeupload['std_uploaded_not_offered']);
				else $is_offer = true;
		}
		else bark($lang_takeupload['std_uploaded_not_offered']);
	}
	elseif (!$allowtorrents)
		bark($lang_takeupload['std_unauthorized_upload_freely']);
}
else //upload to unknown section
	die("Upload to unknown section.");
// ------------- end: check upload authority ------------------//

// Replace punctuation characters with spaces

//$torrent = str_replace("_", " ", $torrent);

if ($largesize_torrent && $totallen > ($largesize_torrent * 1073741824)) //Large Torrent Promotion
{
	switch($largepro_torrent)
	{
		case 2: //Free
		{
			$sp_state = 2;
			break;
		}
		case 3: //2X
		{
			$sp_state = 3;
			break;
		}
		case 4: //2X Free
		{
			$sp_state = 4;
			break;
		}
		case 5: //Half Leech
		{
			$sp_state = 5;
			break;
		}
		case 6: //2X Half Leech
		{
			$sp_state = 6;
			break;
		}
		case 7: //30% Leech
		{
			$sp_state = 7;
			break;
		}
		default: //normal
		{
			$sp_state = 1;
			break;
		}
	}
}
else{ //ramdom torrent promotion
	$sp_id = mt_rand(1,100);
	if($sp_id <= ($probability = $randomtwoupfree_torrent)) //2X Free
		$sp_state = 4;
	elseif($sp_id <= ($probability += $randomtwoup_torrent)) //2X
		$sp_state = 3;
	elseif($sp_id <= ($probability += $randomfree_torrent)) //Free
		$sp_state = 2;
	elseif($sp_id <= ($probability += $randomhalfleech_torrent)) //Half Leech
		$sp_state = 5;
	elseif($sp_id <= ($probability += $randomtwouphalfdown_torrent)) //2X Half Leech
		$sp_state = 6;
	elseif($sp_id <= ($probability += $randomthirtypercentdown_torrent)) //30% Leech
		$sp_state = 7;
	else
		$sp_state = 1; //normal
}

if ($altname_main == 'yes'){
$cnname_part = unesc(trim($_POST["cnname"]));
$size_part = str_replace(" ", "", mksize($totallen));
$date_part = date("m.d.y");
$category_part = get_single_value("categories","name","WHERE id = ".sqlesc($catid));
$torrent = "【".$date_part."】".($_POST["name"] ? "[".$_POST["name"]."]" : "").($cnname_part ? "[".$cnname_part."]" : "");
}

// some ugly code of automatically promoting torrents based on some rules
if ($prorules_torrent == 'yes'){
foreach ($promotionrules_torrent as $rule)
{
	if (!array_key_exists('catid', $rule) || in_array($catid, $rule['catid']))
		if (!array_key_exists('sourceid', $rule) || in_array($sourceid, $rule['sourceid']))
			if (!array_key_exists('mediumid', $rule) || in_array($mediumid, $rule['mediumid']))
				if (!array_key_exists('codecid', $rule) || in_array($codecid, $rule['codecid']))
					if (!array_key_exists('standardid', $rule) || in_array($standardid, $rule['standardid']))
						if (!array_key_exists('processingid', $rule) || in_array($processingid, $rule['processingid']))
							if (!array_key_exists('teamid', $rule) || in_array($teamid, $rule['teamid']))
								if (!array_key_exists('audiocodecid', $rule) || in_array($audiocodecid, $rule['audiocodecid']))
									if (!array_key_exists('pattern', $rule) || preg_match($rule['pattern'], $torrent))
										if (is_numeric($rule['promotion'])){
											$sp_state = $rule['promotion'];
											break;
										}
}
}
$dateTimeStringNow = \Carbon\Carbon::now()->toDateTimeString();

$torrentSavePath = getFullDirectory($torrent_dir);
if (!is_dir($torrentSavePath)) {
    bark("torrent save path: $torrentSavePath not exists.");
}
if (!is_writable($torrentSavePath)) {
    bark("torrent save path: $torrentSavePath not writeable.");
}

/**
 * get cover
 * @since 1.7.8
 */
$descriptionArr = format_description($descr);
$cover = get_image_from_description($descriptionArr, true, false);
$insert = [
    'filename' => $fname,
    'owner' => $CURUSER['id'],
    'visible' => 'yes',
    'anonymous' => $anonymous,
    'name' => $torrent,
    'size' => $totallen,
    'numfiles' => count($filelist),
    'type' => $type,
    'url' => $url,
    'small_descr' => $small_descr,
    'descr' => $descr,
    'ori_descr' => $descr,
    'category' => $catid,
    'source' => $sourceid,
    'medium' => $mediumid,
    'codec' => $codecid,
    'audiocodec' => $audiocodecid,
    'standard' => $standardid,
    'processing' => $processingid,
    'team' => $teamid,
    'save_as' => $dname,
    'sp_state' => $sp_state,
    'added' => $dateTimeStringNow,
    'last_action' => $dateTimeStringNow,
    'nfo' => $nfo,
    'info_hash' => $infohash,
    'pt_gen' => $_POST['pt_gen'] ?? '',
    'technical_info' => $_POST['technical_info'] ?? '',
    'cover' => $cover,
];
$id = \Nexus\Database\NexusDB::insert('torrents', $insert);

//$ret = sql_query("INSERT INTO torrents (filename, owner, visible, anonymous, name, size, numfiles, type, url, small_descr, descr, ori_descr, category, source, medium, codec, audiocodec, standard, processing, team, save_as, sp_state, added, last_action, nfo, info_hash, pt_gen, technical_info) VALUES (".sqlesc($fname).", ".sqlesc($CURUSER["id"]).", 'yes', ".sqlesc($anonymous).", ".sqlesc($torrent).", ".sqlesc($totallen).", ".count($filelist).", ".sqlesc($type).", ".sqlesc($url).", ".sqlesc($small_descr).", ".sqlesc($descr).", ".sqlesc($descr).", ".sqlesc($catid).", ".sqlesc($sourceid).", ".sqlesc($mediumid).", ".sqlesc($codecid).", ".sqlesc($audiocodecid).", ".sqlesc($standardid).", ".sqlesc($processingid).", ".sqlesc($teamid).", ".sqlesc($dname).", ".sqlesc($sp_state) .
//", " . sqlesc(date("Y-m-d H:i:s")) . ", " . sqlesc(date("Y-m-d H:i:s")) . ", ".sqlesc($nfo).", " . sqlesc($infohash). ", " . sqlesc($_POST['pt_gen']) . ", " . sqlesc($_POST['technical_info'] ?? '') . ")");
//if (!$ret) {
//	if (mysql_errno() == 1062)
//	bark($lang_takeupload['std_torrent_existed']);
//	bark("mysql puked: ".mysql_error());
//	//bark("mysql puked: ".preg_replace_callback('/./s', "hex_esc2", mysql_error()));
//}
//$id = mysql_insert_id();

$torrentFilePath = "$torrentSavePath/$id.torrent";
$saveResult = \Rhilip\Bencode\Bencode::dump($torrentFilePath, $dict);
if ($saveResult === false) {
    sql_query("delete from torrents where id = $id limit 1");
    bark("save torrent to $torrentFilePath fail.");
}

/**
 * add custom fields
 * @since v1.6
 */
if (!empty($_POST['custom_fields'])) {
	$now = date('Y-m-d H:i:s');
	foreach ($_POST['custom_fields'] as $customField => $customValue) {
		foreach ((array)$customValue as $value) {
			$customData = [
				'torrent_id' => $id,
				'custom_field_id' => $customField,
				'custom_field_value' => $value,
				'created_at' => $now,
				'updated_at' => $now,
			];
			\Nexus\Database\NexusDB::insert('torrents_custom_field_values', $customData);
		}
	}
}

/**
 * handle tags
 *
 * @since v1.6
 */
$tagIdArr = array_filter($_POST['tags'] ?? []);
if (!empty($tagIdArr)) {
    insert_torrent_tags($id, $tagIdArr);
}

@sql_query("DELETE FROM files WHERE torrent = $id");
foreach ($filelist as $file) {
	@sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, ".sqlesc($file[0]).",".$file[1].")");
}

//===add karma
KPS("+",$uploadtorrent_bonus,$CURUSER["id"]);
//===end


write_log("Torrent $id ($torrent) was uploaded by $anon");

$searchRep = new \App\Repositories\SearchRepository();
$searchRep->addTorrent($id);

//===notify people who voted on offer thanks CoLdFuSiOn :)
if ($is_offer)
{
	$res = sql_query("SELECT `userid` FROM `offervotes` WHERE `userid` != " . $CURUSER["id"] . " AND `offerid` = ". sqlesc($offerid)." AND `vote` = 'yeah'") or sqlerr(__FILE__, __LINE__);

	while($row = mysql_fetch_assoc($res))
	{
		$pn_msg = $lang_takeupload_target[get_user_lang($row["userid"])]['msg_offer_you_voted'].$torrent.$lang_takeupload_target[get_user_lang($row["userid"])]['msg_was_uploaded_by']. $CURUSER["username"] .$lang_takeupload_target[get_user_lang($row["userid"])]['msg_you_can_download'] ."[url=" . get_protocol_prefix() . "$BASEURL/details.php?id=$id&hit=1]".$lang_takeupload_target[get_user_lang($row["userid"])]['msg_here']."[/url]";

		//=== use this if you DO have subject in your PMs
		$subject = $lang_takeupload_target[get_user_lang($row["userid"])]['msg_offer'].$torrent.$lang_takeupload_target[get_user_lang($row["userid"])]['msg_was_just_uploaded'];
		//=== use this if you DO NOT have subject in your PMs
		//$some_variable .= "(0, $row[userid], '" . date("Y-m-d H:i:s") . "', " . sqlesc($pn_msg) . ")";

		//=== use this if you DO have subject in your PMs
		sql_query("INSERT INTO messages (sender, subject, receiver, added, msg) VALUES (0, ".sqlesc($subject).", $row[userid], ".sqlesc(date("Y-m-d H:i:s")).", " . sqlesc($pn_msg) . ")") or sqlerr(__FILE__, __LINE__);
		//=== use this if you do NOT have subject in your PMs
		//sql_query("INSERT INTO messages (sender, receiver, added, msg) VALUES ".$some_variable."") or sqlerr(__FILE__, __LINE__);
		//===end
	}
	//=== delete all offer stuff
	sql_query("DELETE FROM offers WHERE id = ". $offerid);
	sql_query("DELETE FROM offervotes WHERE offerid = ". $offerid);
	sql_query("DELETE FROM comments WHERE offer = ". $offerid);
}
//=== end notify people who voted on offer

/* Email notifs */
if ($emailnotify_smtp=='yes' && $smtptype != 'none')
{
$cat = get_single_value("categories","name","WHERE id=".sqlesc($catid));
$res = sql_query("SELECT id, email, lang FROM users WHERE enabled='yes' AND parked='no' AND status='confirmed' AND notifs LIKE '%[cat$catid]%' AND notifs LIKE '%[email]%' ORDER BY lang ASC") or sqlerr(__FILE__, __LINE__);

$uploader = $anon;

$size = mksize($totallen);

$description = format_comment($descr);

//dirty code, change later

$langfolder_array = array("en", "chs", "cht", "ko", "ja");
$body_arr = array("en" => "", "chs" => "", "cht" => "", "ko" => "", "ja" => "");
$i = 0;
foreach($body_arr as $body)
{
$body_arr[$langfolder_array[$i]] = <<<EOD
{$lang_takeupload_target[$langfolder_array[$i]]['mail_hi']}

{$lang_takeupload_target[$langfolder_array[$i]]['mail_new_torrent']}

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_name']}$torrent
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_size']}$size
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_category']}$cat
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_uppedby']}$uploader

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_description']}
-------------------------------------------------------------------------------------------------------------------------
$description
-------------------------------------------------------------------------------------------------------------------------

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent']}<b><a href="javascript:void(null)" onclick="window.open('http://$BASEURL/details.php?id=$id&hit=1')">{$lang_takeupload_target[$langfolder_array[$i]]['mail_here']}</a></b><br />
http://$BASEURL/details.php?id=$id&hit=1

------{$lang_takeupload_target[$langfolder_array[$i]]['mail_yours']}
{$lang_takeupload_target[$langfolder_array[$i]]['mail_team']}
EOD;

$body_arr[$langfolder_array[$i]] = str_replace("<br />","<br />",nl2br($body_arr[$langfolder_array[$i]]));
	$i++;
}

while($arr = mysql_fetch_array($res))
{
		$current_lang = $arr["lang"];
		$to = $arr["email"];

		sent_mail($to,$SITENAME,$SITEEMAIL,change_email_encode(validlang($current_lang),$lang_takeupload_target[validlang($current_lang)]['mail_title'].$torrent),change_email_encode(validlang($current_lang),$body_arr[validlang($current_lang)]),"torrent upload",false,false,'',get_email_encode(validlang($current_lang)), "eYou");
}
}

header("Location: " . get_protocol_prefix() . "$BASEURL/details.php?id=".htmlspecialchars($id)."&uploaded=1");
?>
