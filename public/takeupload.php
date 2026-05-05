<?php

// require_once("../include/benc.php");
use App\Enums\ModelEventEnum;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Rhilip\Bencode\ParseException;
use Rhilip\Bencode\TorrentFile;

require_once '../include/bittorrent.php';

ini_set('upload_max_filesize', $max_torrent_size);
dbconn();
require_once get_langfile_path();
// require(get_langfile_path("",true));
loggedinorreturn();

function bark($msg)
{
    global $lang_takeupload;
    genbark($msg, $lang_takeupload['std_upload_failed']);
    exit;
}

if ($CURUSER['uploadpos'] == 'no') {
    exit;
}

foreach (explode(':', 'descr:type:name') as $v) {
    if (! isset($_POST[$v])) {
        bark($lang_takeupload['std_missing_form_data']);
    }
}

if (! isset($_FILES['file'])) {
    bark($lang_takeupload['std_missing_form_data']);
}

$f = $_FILES['file'];
$fname = unesc($f['name']);
if (empty($fname)) {
    bark($lang_takeupload['std_empty_filename']);
}
if (user_can('beanonymous') && isset($_POST['uplver']) && $_POST['uplver'] == 'yes') {
    $anonymous = 'yes';
    $anon = 'Anonymous';
} else {
    $anonymous = 'no';
    $anon = $CURUSER['username'];
}

$url = parse_imdb_id($_POST['url'] ?? '');

$nfo = '';
if ($enablenfo_main == 'yes') {
    $nfofile = $_FILES['nfo'] ?? [];
    if (! empty($nfofile['name'])) {

        if ($nfofile['size'] == 0) {
            bark($lang_takeupload['std_zero_byte_nfo']);
        }

        if ($nfofile['size'] > 65535) {
            bark($lang_takeupload['std_nfo_too_big']);
        }

        $nfofilename = $nfofile['tmp_name'];

        if (@! is_uploaded_file($nfofilename)) {
            bark($lang_takeupload['std_nfo_upload_failed']);
        }
        $nfo = str_replace("\x0d\x0d\x0a", "\x0d\x0a", @file_get_contents($nfofilename));
    }
}

$small_descr = unesc($_POST['small_descr'] ?? '');

$descr = unesc($_POST['descr']);
if (! $descr) {
    bark($lang_takeupload['std_blank_description']);
}

$catid = intval($_POST['type'] ?? 0);
$catmod = NexusDB::table('categories')->where('id', (int) $catid)->value('mode');
if (! $catmod) {
    bark('Invalid category');
}
$sourceid = intval($_POST['source_sel'][$catmod] ?? 0);
$mediumid = intval($_POST['medium_sel'][$catmod] ?? 0);
$codecid = intval($_POST['codec_sel'][$catmod] ?? 0);
$standardid = intval($_POST['standard_sel'][$catmod] ?? 0);
$processingid = intval($_POST['processing_sel'][$catmod] ?? 0);
$teamid = intval($_POST['team_sel'][$catmod] ?? 0);
$audiocodecid = intval($_POST['audiocodec_sel'][$catmod] ?? 0);

if (! is_valid_id($catid)) {
    bark($lang_takeupload['std_category_unselected']);
}

if (! preg_match('/^(.+)\.torrent$/si', $fname, $matches)) {
    bark($lang_takeupload['std_filename_not_torrent']);
}
$shortfname = $torrent = $matches[1];
if (! empty($_POST['name'])) {
    $torrent = trim(unesc($_POST['name']));
}
if ($f['size'] > $max_torrent_size) {
    bark($lang_takeupload['std_torrent_file_too_big'].number_format($max_torrent_size).$lang_takeupload['std_remake_torrent_note']);
}
$tmpname = $f['tmp_name'];
if (! is_uploaded_file($tmpname)) {
    do_log('eek, FILE: '.nexus_json_encode($f), 'error');
    bark('eek');
}
if (! filesize($tmpname)) {
    bark($lang_takeupload['std_empty_file']);
}

// check max price
$maxPrice = get_setting('torrent.max_price');
$paidTorrentEnabled = get_setting('torrent.paid_torrent_enabled') == 'yes';
if ($maxPrice > 0 && isset($_POST['price']) && $_POST['price'] > $maxPrice && $paidTorrentEnabled) {
    bark('price too much');
}

try {
    $dict = TorrentFile::load($tmpname);
    $dict = $dict->unhybridizedTo();
    $dict->parse();
} catch (ParseException $e) {
    bark($e->getMessage());
}

// The following line requires uploader to re-download torrents after uploading
// even the torrent is set as private and with uploader's passkey in it.
$dict->cleanRootFields()
    ->setComment(getSchemeAndHttpHost())
    ->setCreationDate(time())
    ->setCreatedBy($SITENAME)
    ->setAnnounce(get_protocol_prefix().$announce_urls[0])  // change announce url to local
    ->setPrivate(true)
    ->setSource("[$BASEURL] $SITENAME");

$filelist = $dict->getFileList();
$dname = $dict->getName();
$type = $dict->getFileMode();
$totallen = $dict->getSize();
$pieces = $dict->getInfoField('pieces');
$piecesCount = strlen($pieces) / 20;
$maxPieceCount = 24576;
$idealPiecesCount = $totallen / (8 * 1024 ** 2);
if ($piecesCount > $maxPieceCount && $idealPiecesCount < $maxPieceCount) {
    bark('Too many pieces');
}
$infohash = $dict->getInfoHashV1ForAnnounce();
$exists = Torrent::query()->whereInfoHash($infohash)->first(['id']);
if ($exists) {
    //    bark($lang_takeupload['std_torrent_existed']);
    nexus_redirect(sprintf('details.php?id=%d&existed=1', $exists['id']));
}

// ------------- start: check upload authority ------------------//
$allowtorrents = user_can_upload('torrents');
$allowspecial = user_can_upload('music');

$offerid = intval($_POST['offer'] ?? 0);
$is_offer = false;
if ($browsecatmode != $specialcatmode && $catmod == $specialcatmode) {// upload to special section
    if (! $allowspecial) {
        bark($lang_takeupload['std_unauthorized_upload_freely']);
    }
} elseif ($catmod == $browsecatmode) {// upload to torrents section
    if ($offerid) {// it is a offer
        $allowed_offer_count = NexusDB::table('offers')
            ->where('allowed', 'allowed')
            ->where('userid', (int) $CURUSER['id'])
            ->count();
        if ($allowed_offer_count && $enableoffer == 'yes') {
            $allowed_offer = NexusDB::table('offers')
                ->where('id', (int) $offerid)
                ->where('allowed', 'allowed')
                ->where('userid', (int) $CURUSER['id'])
                ->count();
            if ($allowed_offer != 1) {// user uploaded torrent that is not an allowed offer
                bark($lang_takeupload['std_uploaded_not_offered']);
            } else {
                $is_offer = true;
            }
        } else {
            bark($lang_takeupload['std_uploaded_not_offered']);
        }
    } elseif (! $allowtorrents) {
        bark($lang_takeupload['std_unauthorized_upload_freely']);
    }
} else { // upload to unknown section
    exit('Upload to unknown section.');
}
// ------------- end: check upload authority ------------------//

// Replace punctuation characters with spaces

// $torrent = str_replace("_", " ", $torrent);

if ($largesize_torrent && $totallen > ($largesize_torrent * 1073741824)) { // Large Torrent Promotion
    switch ($largepro_torrent) {
        case 2: // Free

            $sp_state = 2;
            break;

        case 3: // 2X

            $sp_state = 3;
            break;

        case 4: // 2X Free

            $sp_state = 4;
            break;

        case 5: // Half Leech

            $sp_state = 5;
            break;

        case 6: // 2X Half Leech

            $sp_state = 6;
            break;

        case 7: // 30% Leech

            $sp_state = 7;
            break;

        default: // normal

            $sp_state = 1;
            break;

    }
} else { // ramdom torrent promotion
    $sp_id = mt_rand(1, 100);
    if ($sp_id <= ($probability = $randomtwoupfree_torrent)) { // 2X Free
        $sp_state = 4;
    } elseif ($sp_id <= ($probability += $randomtwoup_torrent)) { // 2X
        $sp_state = 3;
    } elseif ($sp_id <= ($probability += $randomfree_torrent)) { // Free
        $sp_state = 2;
    } elseif ($sp_id <= ($probability += $randomhalfleech_torrent)) { // Half Leech
        $sp_state = 5;
    } elseif ($sp_id <= ($probability += $randomtwouphalfdown_torrent)) { // 2X Half Leech
        $sp_state = 6;
    } elseif ($sp_id <= ($probability += $randomthirtypercentdown_torrent)) { // 30% Leech
        $sp_state = 7;
    } else {
        $sp_state = 1;
    } // normal
}

// some ugly code of automatically promoting torrents based on some rules
// if ($prorules_torrent == 'yes'){
// foreach ($promotionrules_torrent as $rule)
// {
//	if (!array_key_exists('catid', $rule) || in_array($catid, $rule['catid']))
//		if (!array_key_exists('sourceid', $rule) || in_array($sourceid, $rule['sourceid']))
//			if (!array_key_exists('mediumid', $rule) || in_array($mediumid, $rule['mediumid']))
//				if (!array_key_exists('codecid', $rule) || in_array($codecid, $rule['codecid']))
//					if (!array_key_exists('standardid', $rule) || in_array($standardid, $rule['standardid']))
//						if (!array_key_exists('processingid', $rule) || in_array($processingid, $rule['processingid']))
//							if (!array_key_exists('teamid', $rule) || in_array($teamid, $rule['teamid']))
//								if (!array_key_exists('audiocodecid', $rule) || in_array($audiocodecid, $rule['audiocodecid']))
//									if (!array_key_exists('pattern', $rule) || preg_match($rule['pattern'], $torrent))
//										if (is_numeric($rule['promotion'])){
//											$sp_state = $rule['promotion'];
//											break;
//										}
// }
// }
$dateTimeStringNow = Carbon::now()->toDateTimeString();

$torrentSavePath = getFullDirectory($torrent_dir);
if (! is_dir($torrentSavePath)) {
    bark("torrent save path: $torrentSavePath not exists.");
}
if (! is_writable($torrentSavePath)) {
    bark("torrent save path: $torrentSavePath not writeable.");
}

/**
 * get cover
 *
 * @since 1.7.8
 */
$descriptionArr = format_description($descr);
$cover = get_image_from_description($descriptionArr, true, false);
if (NexusDB::isPgsql()) {
    $infoHashInsert = NexusDB::raw("decode('".bin2hex($infohash)."', 'hex')");
} elseif (NexusDB::isMysql()) {
    $infoHashInsert = $infohash;
} else {
    throw new RuntimeException('Not supported database');
}
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
    //    'descr' => $descr,
    //    'ori_descr' => $descr,
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
    //    'nfo' => $nfo,
    'info_hash' => $infoHashInsert,
    //    'pt_gen' => $_POST['pt_gen'] ?? '',
    //    'technical_info' => $_POST['technical_info'] ?? '',
    'cover' => $cover,
    'pieces_hash' => sha1($pieces),
    'cache_stamp' => time(),
];
/**
 * migrate to extra table
 *
 * @since 1.9
 */
$extra = [
    'descr' => $descr,
    'media_info' => $_POST['technical_info'] ?? '',
    'nfo' => $nfo,
    'pt_gen' => $_POST['pt_gen'] ?? '',
];
if (isset($_POST['hr'][$catmod]) && isset(Torrent::$hrStatus[$_POST['hr'][$catmod]]) && user_can('torrent_hr')) {
    $insert['hr'] = $_POST['hr'][$catmod];
}
if (user_can('torrentsticky')) {
    if (isset($_POST['pos_state']) && isset(Torrent::$posStates[$_POST['pos_state']])) {
        $posStateUntil = $_POST['pos_state_until'] ?: null;
        $posState = $_POST['pos_state'];
        if ($posState == Torrent::POS_STATE_STICKY_NONE) {
            $posStateUntil = null;
        }
        if ($posStateUntil && Carbon::parse($posStateUntil)->lte(now())) {
            $posState = Torrent::POS_STATE_STICKY_NONE;
            $posStateUntil = null;
        }
        $insert['pos_state'] = $posState;
        $insert['pos_state_until'] = $posStateUntil;
    }
}
if (user_can('torrentmanage') && ($CURUSER['picker'] == 'yes' || get_user_class() >= User::CLASS_SYSOP)) {
    if (isset($_POST['picktype']) && isset(Torrent::$pickTypes[$_POST['picktype']])) {
        $insert['picktype'] = $_POST['picktype'];
        if ($insert['picktype'] == Torrent::PICK_NORMAL) {
            $insert['picktime'] = null;
        } else {
            $insert['picktime'] = now()->toDateTimeString();
        }
    }
}
if (user_can('torrent-approval-allow-automatic')) {
    $insert['approval_status'] = Torrent::APPROVAL_STATUS_ALLOW;
}
if (user_can('torrent-set-price') && $paidTorrentEnabled) {
    $insert['price'] = intval($_POST['price'] ?? 0);
}
do_log('[INSERT_TORRENT]: '.nexus_json_encode($insert));
$id = Torrent::query()->insertGetId($insert);


$torrentFilePath = "$torrentSavePath/$id.torrent";
$saveResult = $dict->dump($torrentFilePath);
if ($saveResult === false) {
    NexusDB::table('torrents')->where('id', (int) $id)->limit(1)->delete();
    bark("save torrent to $torrentFilePath fail.");
}
// remove announce info_hash not exists cache
// @see announce.php
NexusDB::cache_del("torrent_not_exists:$infohash");

/**
 * add custom fields
 *
 * @since v1.6
 */
if (! empty($_POST['custom_fields'][$catmod])) {
    $customField = new Field;
    $customField->saveFieldValues($catmod, $id, $_POST['custom_fields'][$catmod]);
}

/**
 * handle tags
 *
 * @since v1.6
 */
$tagIdArr = array_filter($_POST['tags'][$catmod] ?? []);
if (! empty($tagIdArr)) {
    insert_torrent_tags($id, $tagIdArr);
}

NexusDB::table('files')->where('torrent', (int) $id)->delete();
foreach ($filelist as $file) {
    NexusDB::insert('files', [
        'torrent' => (int) $id,
        'filename' => (string) $file['path'],
        'size' => (int) $file['size'],
    ]);
}
$extra['torrent_id'] = $id;
TorrentExtra::query()->create($extra);

// ===add karma
KPS('+', $uploadtorrent_bonus, $CURUSER['id']);
// ===end

$torrentRep = new TorrentRepository;
$torrentRep->addPiecesHashCache($id, $insert['pieces_hash']);

write_log("Torrent $id ($torrent) was uploaded by $anon");
// move to event listener
// $searchRep = new \App\Repositories\SearchRepository();
// $searchRep->addTorrent($id);
//
// $meiliSearch = new \App\Repositories\MeiliSearchRepository();
// $meiliSearch->doImportFromDatabase($id);

// trigger event
fire_event(ModelEventEnum::TORRENT_CREATED, Torrent::query()->find($id));

// ===notify people who voted on offer thanks CoLdFuSiOn :)
if ($is_offer) {
    $voteRows = NexusDB::table('offervotes')
        ->where('userid', '!=', (int) $CURUSER['id'])
        ->where('offerid', (int) $offerid)
        ->where('vote', 'yeah')
        ->select(['userid'])
        ->get();

    foreach ($voteRows as $row) {
        $row = (array) $row;
        $locale = get_user_locale($row['userid']);
        $pn_msg = nexus_trans('torrent.msg_offer_you_voted', [], $locale).$torrent.nexus_trans('torrent.msg_was_uploaded_by', [], $locale).$CURUSER['username'].nexus_trans('torrent.msg_you_can_download', [], $locale).'[url='.get_protocol_prefix()."$BASEURL/details.php?id=$id&hit=1]".nexus_trans('torrent.msg_here', [], $locale).'[/url]';

        // === use this if you DO have subject in your PMs
        $subject = nexus_trans('torrent.msg_offer', [], $locale).$torrent.nexus_trans('torrent.msg_was_just_uploaded', [], $locale);

        // === use this if you DO have subject in your PMs
        Message::add([
            'sender' => 0,
            'subject' => $subject,
            'receiver' => $row['userid'],
            'added' => now(),
            'msg' => $pn_msg,
        ]);
    }
    // === delete all offer stuff
    NexusDB::table('offers')->where('id', (int) $offerid)->delete();
    NexusDB::table('offervotes')->where('offerid', (int) $offerid)->delete();
    NexusDB::table('comments')->where('offer', (int) $offerid)->delete();
    // increment user offer_allowed_count
    NexusDB::table('users')
        ->where('id', (int) $CURUSER['id'])
        ->update(['offer_allowed_count' => NexusDB::raw('offer_allowed_count + 1')]);
}
// === end notify people who voted on offer

/* Email notifs */

header('Location: '.get_protocol_prefix()."$BASEURL/details.php?id=".htmlspecialchars($id).'&uploaded=1');
