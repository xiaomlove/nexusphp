<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\ModelEventEnum;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Rhilip\Bencode\ParseException;
use Rhilip\Bencode\TorrentFile;

/**
 * Replacement for `public/takeupload.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. POST-only torrent-upload write
 * handler. Receives a multipart/form-data POST from
 * `App\Http\Controllers\Legacy\UploadController` (the migrated
 * `/upload.php` form).
 *
 * Faithful 1:1 transcription of the legacy 490-LOC script preserving
 * every side effect:
 *   1. Validate POST fields (`name`, `descr`, `type`, `file`).
 *   2. Parse the uploaded `.torrent` file via Rhilip\Bencode and
 *      reject malformed payloads / oversize / too-many-pieces.
 *   3. Reject duplicate-info-hash uploads (302 to
 *      `details.php?id=N&existed=1`).
 *   4. Enforce upload-authority gates (offers, special section).
 *   5. Roll the random / large-file promotion state (sp_state).
 *   6. INSERT the torrent row + extras.
 *   7. Save the `.torrent` file to disk; rollback the INSERT on
 *      write failure.
 *   8. Bust the announce `torrent_not_exists:<infohash>` cache key.
 *   9. Insert custom fields, tags, file list.
 *   10. Award `KPS('+', $uploadtorrent_bonus, ...)` karma.
 *   11. Fire `ModelEventEnum::TORRENT_CREATED`.
 *   12. Send PMs to offer voters when the upload was an offered torrent.
 *   13. 302 to `details.php?id=N&uploaded=1`.
 *
 * URL preserved exactly so the legacy `<form action="takeupload.php">`
 * in the migrated `UploadController` keeps posting to the same
 * endpoint.
 *
 * POST is CSRF-exempt — the legacy form had no `@csrf` field; see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * Bark/exit transformation
 * ------------------------
 * The legacy `bark($msg)` helper called `genbark($msg, ...); exit;`.
 * In a Laravel pipeline `exit` mid-render terminates the FPM worker
 * before middleware can post-process. The controller throws an
 * internal `BarkException` instead; `__invoke` catches it and renders
 * the same error page via `genbark()` captured into a Response.
 */
class TakeUploadController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly TorrentRepository $torrentRep,
    ) {}

    public function __invoke(): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->uploadpos ?? 'yes') === 'no') {
            abort(403, 'Unauthorized to upload.');
        }

        $lang = $this->loadLang('takeupload.php');

        try {
            return $this->handle($user, $lang);
        } catch (BarkException $e) {
            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function handle(User $user, array $lang): RedirectResponse
    {
        // ─── Required form fields ────────────────────────────────────────
        foreach (['descr', 'type', 'name'] as $v) {
            if (! isset($_POST[$v])) {
                $this->bark((string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
            }
        }
        if (! isset($_FILES['file'])) {
            $this->bark((string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
        }

        $f = $_FILES['file'];
        $fname = function_exists('unesc') ? unesc($f['name']) : (string) $f['name'];
        if (empty($fname)) {
            $this->bark((string) ($lang['std_empty_filename'] ?? 'Empty filename.'));
        }

        // ─── Anonymous / username ────────────────────────────────────────
        if (user_can('beanonymous') && isset($_POST['uplver']) && $_POST['uplver'] === 'yes') {
            $anonymous = 'yes';
            $anon = 'Anonymous';
        } else {
            $anonymous = 'no';
            $anon = (string) $user->username;
        }

        $url = function_exists('parse_imdb_id') ? parse_imdb_id($_POST['url'] ?? '') : ($_POST['url'] ?? '');

        // ─── NFO upload (optional) ───────────────────────────────────────
        $nfo = '';
        if (($GLOBALS['enablenfo_main'] ?? '') === 'yes') {
            $nfofile = $_FILES['nfo'] ?? [];
            if (! empty($nfofile['name'])) {
                if ($nfofile['size'] === 0) {
                    $this->bark((string) ($lang['std_zero_byte_nfo'] ?? 'Zero-byte NFO.'));
                }
                if ($nfofile['size'] > 65535) {
                    $this->bark((string) ($lang['std_nfo_too_big'] ?? 'NFO too big.'));
                }
                $nfofilename = $nfofile['tmp_name'];
                if (@! is_uploaded_file($nfofilename)) {
                    $this->bark((string) ($lang['std_nfo_upload_failed'] ?? 'NFO upload failed.'));
                }
                $nfo = str_replace("\x0d\x0d\x0a", "\x0d\x0a", (string) @file_get_contents($nfofilename));
            }
        }

        $smallDescr = function_exists('unesc') ? unesc($_POST['small_descr'] ?? '') : ($_POST['small_descr'] ?? '');
        $descr = function_exists('unesc') ? unesc($_POST['descr']) : ($_POST['descr']);
        if ($descr === '' || $descr === null) {
            $this->bark((string) ($lang['std_blank_description'] ?? 'Blank description.'));
        }

        $catid = (int) ($_POST['type'] ?? 0);
        $catmod = NexusDB::table('categories')->where('id', $catid)->value('mode');
        if (! $catmod) {
            $this->bark('Invalid category');
        }
        $sourceid = (int) ($_POST['source_sel'][$catmod] ?? 0);
        $mediumid = (int) ($_POST['medium_sel'][$catmod] ?? 0);
        $codecid = (int) ($_POST['codec_sel'][$catmod] ?? 0);
        $standardid = (int) ($_POST['standard_sel'][$catmod] ?? 0);
        $processingid = (int) ($_POST['processing_sel'][$catmod] ?? 0);
        $teamid = (int) ($_POST['team_sel'][$catmod] ?? 0);
        $audiocodecid = (int) ($_POST['audiocodec_sel'][$catmod] ?? 0);
        if (function_exists('is_valid_id') && ! is_valid_id($catid)) {
            $this->bark((string) ($lang['std_category_unselected'] ?? 'Category unselected.'));
        }

        if (! preg_match('/^(.+)\.torrent$/si', $fname, $matches)) {
            $this->bark((string) ($lang['std_filename_not_torrent'] ?? 'Filename not .torrent.'));
        }
        $torrent = $matches[1];
        if (! empty($_POST['name'])) {
            $torrent = trim(function_exists('unesc') ? unesc($_POST['name']) : (string) $_POST['name']);
        }
        $maxTorrentSize = (int) ($GLOBALS['max_torrent_size'] ?? 0);
        if ($maxTorrentSize > 0 && $f['size'] > $maxTorrentSize) {
            $this->bark(
                (string) ($lang['std_torrent_file_too_big'] ?? 'Torrent file too big: ')
                .number_format($maxTorrentSize)
                .(string) ($lang['std_remake_torrent_note'] ?? '. Please remake.'),
            );
        }
        $tmpname = $f['tmp_name'];
        if (! is_uploaded_file($tmpname)) {
            do_log('eek, FILE: '.nexus_json_encode($f), 'error');
            $this->bark('eek');
        }
        if (! filesize($tmpname)) {
            $this->bark((string) ($lang['std_empty_file'] ?? 'Empty file.'));
        }

        // ─── Price check ────────────────────────────────────────────────
        $maxPrice = (int) get_setting('torrent.max_price');
        $paidTorrentEnabled = get_setting('torrent.paid_torrent_enabled') === 'yes';
        if ($maxPrice > 0 && isset($_POST['price']) && (int) $_POST['price'] > $maxPrice && $paidTorrentEnabled) {
            $this->bark('price too much');
        }

        // ─── Parse the torrent file ─────────────────────────────────────
        try {
            $dict = TorrentFile::load($tmpname);
            $dict = $dict->unhybridizedTo();
            $dict->parse();
        } catch (ParseException $e) {
            $this->bark($e->getMessage());
        }

        $sitename = (string) ($GLOBALS['SITENAME'] ?? '');
        $baseUrl = (string) ($GLOBALS['BASEURL'] ?? '');
        $announceUrls = (array) ($GLOBALS['announce_urls'] ?? []);
        $dict->cleanRootFields()
            ->setComment(getSchemeAndHttpHost())
            ->setCreationDate(time())
            ->setCreatedBy($sitename)
            ->setAnnounce(get_protocol_prefix().($announceUrls[0] ?? ''))
            ->setPrivate(true)
            ->setSource("[{$baseUrl}] {$sitename}");

        $filelist = $dict->getFileList();
        $dname = $dict->getName();
        $type = $dict->getFileMode();
        $totallen = $dict->getSize();
        $pieces = $dict->getInfoField('pieces');
        $piecesCount = strlen($pieces) / 20;
        $maxPieceCount = 24576;
        $idealPiecesCount = $totallen / (8 * 1024 ** 2);
        if ($piecesCount > $maxPieceCount && $idealPiecesCount < $maxPieceCount) {
            $this->bark('Too many pieces');
        }
        $infohash = $dict->getInfoHashV1ForAnnounce();
        $exists = Torrent::query()->whereInfoHash($infohash)->first(['id']);
        if ($exists) {
            return new RedirectResponse(sprintf('/details.php?id=%d&existed=1', $exists['id']));
        }

        // ─── Upload authority gates ─────────────────────────────────────
        $allowTorrents = (bool) user_can_upload('torrents');
        $allowSpecial = (bool) user_can_upload('music');
        $offerid = (int) ($_POST['offer'] ?? 0);
        $isOffer = false;
        $browseCatMode = (string) ($GLOBALS['browsecatmode'] ?? '');
        $specialCatMode = (string) ($GLOBALS['specialcatmode'] ?? '');
        $enableOffer = ($GLOBALS['enableoffer'] ?? '') === 'yes';

        if ($browseCatMode !== $specialCatMode && $catmod === $specialCatMode) {
            if (! $allowSpecial) {
                $this->bark((string) ($lang['std_unauthorized_upload_freely'] ?? 'Unauthorized upload.'));
            }
        } elseif ($catmod === $browseCatMode) {
            if ($offerid > 0) {
                $allowedOfferCount = (int) NexusDB::table('offers')
                    ->where('allowed', 'allowed')
                    ->where('userid', (int) $user->id)
                    ->count();
                if ($allowedOfferCount > 0 && $enableOffer) {
                    $allowedOffer = (int) NexusDB::table('offers')
                        ->where('id', $offerid)
                        ->where('allowed', 'allowed')
                        ->where('userid', (int) $user->id)
                        ->count();
                    if ($allowedOffer !== 1) {
                        $this->bark((string) ($lang['std_uploaded_not_offered'] ?? 'Uploaded but not offered.'));
                    }
                    $isOffer = true;
                } else {
                    $this->bark((string) ($lang['std_uploaded_not_offered'] ?? 'Uploaded but not offered.'));
                }
            } elseif (! $allowTorrents) {
                $this->bark((string) ($lang['std_unauthorized_upload_freely'] ?? 'Unauthorized upload.'));
            }
        } else {
            $this->bark('Upload to unknown section.');
        }

        // ─── Promotion state ────────────────────────────────────────────
        $largeSize = (int) ($GLOBALS['largesize_torrent'] ?? 0);
        $largePro = (int) ($GLOBALS['largepro_torrent'] ?? 0);
        if ($largeSize > 0 && $totallen > ($largeSize * 1073741824)) {
            $sp_state = match ($largePro) {
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5,
                6 => 6,
                7 => 7,
                default => 1,
            };
        } else {
            $sp_id = mt_rand(1, 100);
            $randomTwoUpFree = (int) ($GLOBALS['randomtwoupfree_torrent'] ?? 0);
            $randomTwoUp = (int) ($GLOBALS['randomtwoup_torrent'] ?? 0);
            $randomFree = (int) ($GLOBALS['randomfree_torrent'] ?? 0);
            $randomHalfLeech = (int) ($GLOBALS['randomhalfleech_torrent'] ?? 0);
            $randomTwoUpHalfDown = (int) ($GLOBALS['randomtwouphalfdown_torrent'] ?? 0);
            $randomThirtyPercent = (int) ($GLOBALS['randomthirtypercentdown_torrent'] ?? 0);

            $probability = $randomTwoUpFree;
            if ($sp_id <= $probability) {
                $sp_state = 4;
            } else {
                $probability += $randomTwoUp;
                if ($sp_id <= $probability) {
                    $sp_state = 3;
                } else {
                    $probability += $randomFree;
                    if ($sp_id <= $probability) {
                        $sp_state = 2;
                    } else {
                        $probability += $randomHalfLeech;
                        if ($sp_id <= $probability) {
                            $sp_state = 5;
                        } else {
                            $probability += $randomTwoUpHalfDown;
                            if ($sp_id <= $probability) {
                                $sp_state = 6;
                            } else {
                                $probability += $randomThirtyPercent;
                                $sp_state = $sp_id <= $probability ? 7 : 1;
                            }
                        }
                    }
                }
            }
        }

        $dateTimeStringNow = Carbon::now()->toDateTimeString();
        $torrentDir = (string) ($GLOBALS['torrent_dir'] ?? '');
        $torrentSavePath = function_exists('getFullDirectory') ? getFullDirectory($torrentDir) : $torrentDir;
        if (! is_dir($torrentSavePath)) {
            $this->bark("torrent save path: {$torrentSavePath} not exists.");
        }
        if (! is_writable($torrentSavePath)) {
            $this->bark("torrent save path: {$torrentSavePath} not writeable.");
        }

        // ─── Cover extraction ──────────────────────────────────────────
        $cover = '';
        if (function_exists('format_description')) {
            $descriptionArr = format_description($descr);
            if (function_exists('get_image_from_description')) {
                $cover = (string) get_image_from_description($descriptionArr, true, false);
            }
        }

        $infoHashInsert = NexusDB::isPgsql()
            ? NexusDB::raw("decode('".bin2hex($infohash)."', 'hex')")
            : $infohash;

        $insert = [
            'filename' => $fname,
            'owner' => (int) $user->id,
            'visible' => 'yes',
            'anonymous' => $anonymous,
            'name' => (string) $torrent,
            'size' => $totallen,
            'numfiles' => count($filelist),
            'type' => $type,
            'url' => (string) $url,
            'small_descr' => (string) $smallDescr,
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
            'info_hash' => $infoHashInsert,
            'cover' => $cover,
            'pieces_hash' => sha1($pieces),
            'cache_stamp' => time(),
        ];
        $extra = [
            'descr' => $descr,
            'media_info' => (string) ($_POST['technical_info'] ?? ''),
            'nfo' => $nfo,
            'pt_gen' => (string) ($_POST['pt_gen'] ?? ''),
        ];
        if (isset($_POST['hr'][$catmod])
            && isset(Torrent::$hrStatus[$_POST['hr'][$catmod]])
            && user_can('torrent_hr')) {
            $insert['hr'] = $_POST['hr'][$catmod];
        }
        if (user_can('torrentsticky')
            && isset($_POST['pos_state'])
            && isset(Torrent::$posStates[$_POST['pos_state']])) {
            $posStateUntil = $_POST['pos_state_until'] ?: null;
            $posState = $_POST['pos_state'];
            if ($posState === Torrent::POS_STATE_STICKY_NONE) {
                $posStateUntil = null;
            }
            if ($posStateUntil !== null && Carbon::parse($posStateUntil)->lte(now())) {
                $posState = Torrent::POS_STATE_STICKY_NONE;
                $posStateUntil = null;
            }
            $insert['pos_state'] = $posState;
            $insert['pos_state_until'] = $posStateUntil;
        }
        if (user_can('torrentmanage')
            && (($user->picker ?? 'no') === 'yes' || (int) $user->class >= User::CLASS_SYSOP)
            && isset($_POST['picktype'])
            && isset(Torrent::$pickTypes[$_POST['picktype']])) {
            $insert['picktype'] = $_POST['picktype'];
            $insert['picktime'] = $insert['picktype'] === Torrent::PICK_NORMAL ? null : now()->toDateTimeString();
        }
        if (user_can('torrent-approval-allow-automatic')) {
            $insert['approval_status'] = Torrent::APPROVAL_STATUS_ALLOW;
        }
        if (user_can('torrent-set-price') && $paidTorrentEnabled) {
            $insert['price'] = (int) ($_POST['price'] ?? 0);
        }

        do_log('[INSERT_TORRENT]: '.nexus_json_encode($insert));
        $id = (int) Torrent::query()->insertGetId($insert);

        // ─── Save .torrent file to disk ─────────────────────────────────
        $torrentFilePath = "{$torrentSavePath}/{$id}.torrent";
        $saveResult = $dict->dump($torrentFilePath);
        if ($saveResult === false) {
            NexusDB::table('torrents')->where('id', $id)->limit(1)->delete();
            $this->bark("save torrent to {$torrentFilePath} fail.");
        }
        // Bust the announce-side cache.
        NexusDB::cache_del("torrent_not_exists:{$infohash}");

        // ─── Custom fields ──────────────────────────────────────────────
        if (! empty($_POST['custom_fields'][$catmod])) {
            $customField = new Field;
            $customField->saveFieldValues($catmod, $id, $_POST['custom_fields'][$catmod]);
        }

        // ─── Tags ───────────────────────────────────────────────────────
        $tagIdArr = array_filter($_POST['tags'][$catmod] ?? []);
        if (! empty($tagIdArr) && function_exists('insert_torrent_tags')) {
            insert_torrent_tags($id, $tagIdArr);
        }

        // ─── File list ──────────────────────────────────────────────────
        NexusDB::table('files')->where('torrent', $id)->delete();
        foreach ($filelist as $file) {
            NexusDB::insert('files', [
                'torrent' => $id,
                'filename' => (string) $file['path'],
                'size' => (int) $file['size'],
            ]);
        }

        // ─── Extras ─────────────────────────────────────────────────────
        $extra['torrent_id'] = $id;
        TorrentExtra::query()->create($extra);

        // ─── Karma ──────────────────────────────────────────────────────
        $uploadBonus = (int) ($GLOBALS['uploadtorrent_bonus'] ?? 0);
        if (function_exists('KPS')) {
            KPS('+', $uploadBonus, (int) $user->id);
        }

        $this->torrentRep->addPiecesHashCache($id, $insert['pieces_hash']);

        write_log("Torrent {$id} ({$torrent}) was uploaded by {$anon}");
        if (function_exists('fire_event')) {
            fire_event(ModelEventEnum::TORRENT_CREATED, Torrent::query()->find($id));
        }

        // ─── Offer-vote PMs ─────────────────────────────────────────────
        if ($isOffer) {
            $voteRows = NexusDB::table('offervotes')
                ->where('userid', '!=', (int) $user->id)
                ->where('offerid', $offerid)
                ->where('vote', 'yeah')
                ->select(['userid'])
                ->get();
            foreach ($voteRows as $row) {
                $row = (array) $row;
                $voterId = (int) ($row['userid'] ?? 0);
                $locale = function_exists('get_user_locale') ? get_user_locale($voterId) : null;
                $protocol = get_protocol_prefix();
                $detailsUrl = "{$protocol}{$baseUrl}/details.php?id={$id}&hit=1";
                $pn_msg = nexus_trans('torrent.msg_offer_you_voted', [], $locale)
                    .$torrent
                    .nexus_trans('torrent.msg_was_uploaded_by', [], $locale)
                    .(string) $user->username
                    .nexus_trans('torrent.msg_you_can_download', [], $locale)
                    ."[url={$detailsUrl}]"
                    .nexus_trans('torrent.msg_here', [], $locale)
                    .'[/url]';
                $subject = nexus_trans('torrent.msg_offer', [], $locale)
                    .$torrent
                    .nexus_trans('torrent.msg_was_just_uploaded', [], $locale);
                Message::add([
                    'sender' => 0,
                    'subject' => $subject,
                    'receiver' => $voterId,
                    'added' => now(),
                    'msg' => $pn_msg,
                ]);
            }
            NexusDB::table('offers')->where('id', $offerid)->delete();
            NexusDB::table('offervotes')->where('offerid', $offerid)->delete();
            NexusDB::table('comments')->where('offer', $offerid)->delete();
            NexusDB::table('users')
                ->where('id', (int) $user->id)
                ->update(['offer_allowed_count' => NexusDB::raw('offer_allowed_count + 1')]);
        }

        // ─── Final 302 ──────────────────────────────────────────────────
        $protocol = get_protocol_prefix();

        return new RedirectResponse("{$protocol}{$baseUrl}/details.php?id={$id}&uploaded=1");
    }

    private function bark(string $msg): never
    {
        throw new BarkException($msg);
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderBark(string $msg, array $lang): Response
    {
        $heading = (string) ($lang['std_upload_failed'] ?? 'Upload failed');
        if (function_exists('genbark')) {
            ob_start();
            try {
                genbark($msg, $heading);
            } finally {
                $body = (string) ob_get_clean();
            }
            if ($body !== '') {
                return new Response($body, 200);
            }
        }

        // Fallback envelope when genbark is not available.
        $titleEsc = htmlspecialchars($heading, ENT_QUOTES);
        $msgEsc = htmlspecialchars($msg);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head><body>
<table border="0" cellspacing="0" cellpadding="10" width="100%" align="center">
<tr><td class="colhead" align="left">{$titleEsc}</td></tr>
<tr><td class="text" align="left">{$msgEsc}</td></tr>
</table>
</body></html>
HTML;

        return new Response($html, 200);
    }

    /** @return array<string,string> */
    private function loadLang(string $page): array
    {
        $path = base_path(get_langfile_path($page));
        $varName = 'lang_'.preg_replace('/\.php$/', '', $page);
        ${$varName} = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array(${$varName} ?? null) ? ${$varName} : [];
    }
}
