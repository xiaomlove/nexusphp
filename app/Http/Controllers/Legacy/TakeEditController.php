<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\StaffMessage;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\SearchRepository;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Nexus\PTGen\PTGen;

/**
 * Replacement for `public/takeedit.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. POST-only torrent-edit write
 * handler. Receives a multipart/form-data POST from
 * `App\Http\Controllers\Legacy\EditController` (the migrated
 * `/edit.php` form).
 *
 * Faithful 1:1 transcription of the legacy 311-LOC script preserving
 * every side effect:
 *   1. Validate POST fields (`id`, `name`, `descr`, `type`).
 *   2. Permission check: owner OR `user_can('torrentmanage')`.
 *   3. Move-between-sections gate: `enablespecial=yes` AND
 *      `user_can('movetorrent')`.
 *   4. Compute the `$updateset` + `$extraUpdate` arrays from POST.
 *   5. Promotion-state / pos-state / picker / HR / price branches.
 *   6. Cover extraction from description.
 *   7. UPDATE `torrents` + UPDATE/INSERT `torrent_extras`.
 *   8. Custom fields + tags update.
 *   9. write_log + StaffMessage on banned-edit-by-owner +
 *      TorrentOperationLog on cross-user edit.
 *   10. SearchRepository::updateTorrent + MeiliSearch reimport.
 *   11. fire_event('torrent_updated', ...).
 *   12. 302 to `details.php?id=N&edited=1` (or `returnto`).
 *
 * URL preserved exactly so the legacy `<form action="takeedit.php">`
 * in the migrated `EditController` keeps posting to the same endpoint.
 *
 * POST is CSRF-exempt — the legacy form had no `@csrf` field; see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * Bark/exit transformation
 * ------------------------
 * Same `BarkException` pattern as `TakeUploadController`. The legacy
 * `bark($msg)` called `genbark()` (which exited internally); the
 * controller throws and the catch-block renders the buffered
 * `genbark()` envelope.
 */
class TakeEditController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly SearchRepository $searchRep,
        private readonly MeiliSearchRepository $meiliSearch,
    ) {}

    public function __invoke(): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $lang = $this->loadLang('takeedit.php');

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
        foreach (['id', 'name', 'descr', 'type'] as $v) {
            if (! isset($_POST[$v])) {
                $this->bark((string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
            }
        }
        $name = (string) $_POST['name'];
        $descr = (string) $_POST['descr'];
        $typeId = (int) $_POST['type'];

        $maxPrice = (int) get_setting('torrent.max_price');
        $paidTorrentEnabled = get_setting('torrent.paid_torrent_enabled') === 'yes';
        if ($maxPrice > 0 && isset($_POST['price']) && (int) $_POST['price'] > $maxPrice && $paidTorrentEnabled) {
            $this->bark('price too much');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $rowObj = NexusDB::table('torrents')
            ->where('id', $id)
            ->select([
                'id', 'category', 'owner', 'filename', 'save_as', 'anonymous',
                'picktype', 'picktime', 'added', 'banned',
            ])
            ->first();
        if ($rowObj === null) {
            abort(404);
        }
        $row = (array) $rowObj;
        $torrentAddedTime = (string) ($row['added'] ?? '');

        $torrentOld = Torrent::query()->find($id);

        if ((int) $user->id !== (int) ($row['owner'] ?? 0) && ! user_can('torrentmanage')) {
            $this->bark((string) ($lang['std_not_owner'] ?? 'Not owner.'));
        }

        $oldCatMode = NexusDB::table('categories')->where('id', (int) ($row['category'] ?? 0))->value('mode');
        $newCatMode = NexusDB::table('categories')->where('id', $typeId)->value('mode');

        if (function_exists('is_valid_id') && ! is_valid_id($typeId)) {
            $this->bark((string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
        }
        if ($name === '' || $descr === '') {
            $this->bark((string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
        }

        $allowMove = (($GLOBALS['enablespecial'] ?? '') === 'yes' && user_can('movetorrent'));
        if ($oldCatMode !== $newCatMode && ! $allowMove) {
            $this->bark((string) ($lang['std_cannot_move_torrent'] ?? 'Cannot move torrent.'));
        }

        $url = function_exists('parse_imdb_id') ? parse_imdb_id($_POST['url'] ?? '') : (string) ($_POST['url'] ?? '');

        // ─── Extras (PT-Gen / technical_info / nfo) ─────────────────────
        $extraUpdate = [];
        $existingExtraObj = NexusDB::table('torrent_extras')->where('torrent_id', $id)->first();
        $existingExtra = $existingExtraObj ? (array) $existingExtraObj : [];

        if (! empty($_POST['pt_gen'])) {
            $postPtGen = (string) $_POST['pt_gen'];
            $existsPtGenInfo = json_decode((string) ($existingExtra['pt_gen'] ?? ''), true) ?? [];
            $ptGen = new PTGen;
            if ($postPtGen !== $ptGen->getLink($existsPtGenInfo)) {
                $extraUpdate['pt_gen'] = $postPtGen;
            }
        } else {
            $extraUpdate['pt_gen'] = '';
        }
        $extraUpdate['media_info'] = (string) ($_POST['technical_info'] ?? '');

        if (($GLOBALS['enablenfo_main'] ?? '') === 'yes') {
            $nfoaction = (string) ($_POST['nfoaction'] ?? '');
            if ($nfoaction === 'update') {
                $nfofile = $_FILES['nfo'] ?? null;
                if (! $nfofile) {
                    $this->bark('No NFO data');
                }
                if ($nfofile['size'] > 65535) {
                    $this->bark((string) ($lang['std_nfo_too_big'] ?? 'NFO too big.'));
                }
                $nfofilename = $nfofile['tmp_name'];
                if (@is_uploaded_file($nfofilename) && @filesize($nfofilename) > 0) {
                    $extraUpdate['nfo'] = str_replace(
                        "\x0d\x0d\x0a", "\x0d\x0a",
                        (string) file_get_contents($nfofilename),
                    );
                }
                $cache = $GLOBALS['Cache'] ?? null;
                if (is_object($cache) && method_exists($cache, 'delete_value')) {
                    $cache->delete_value('nfo_block_torrent_id_'.$id);
                }
            } elseif ($nfoaction === 'remove') {
                $extraUpdate['nfo'] = '';
                $cache = $GLOBALS['Cache'] ?? null;
                if (is_object($cache) && method_exists($cache, 'delete_value')) {
                    $cache->delete_value('nfo_block_torrent_id_'.$id);
                }
            }
        }

        $extraUpdate['descr'] = $descr;

        // ─── Build $updateset ───────────────────────────────────────────
        $updateset = [];
        $updateset['anonymous'] = ! empty($_POST['anonymous']) ? 'yes' : 'no';
        $updateset['name'] = $name;
        $updateset['url'] = (string) $url;
        $updateset['small_descr'] = (string) ($_POST['small_descr'] ?? '');
        $updateset['category'] = $typeId;
        $updateset['source'] = (int) ($_POST['source_sel'][$newCatMode] ?? 0);
        $updateset['medium'] = (int) ($_POST['medium_sel'][$newCatMode] ?? 0);
        $updateset['codec'] = (int) ($_POST['codec_sel'][$newCatMode] ?? 0);
        $updateset['standard'] = (int) ($_POST['standard_sel'][$newCatMode] ?? 0);
        $updateset['processing'] = (int) ($_POST['processing_sel'][$newCatMode] ?? 0);
        $updateset['team'] = (int) ($_POST['team_sel'][$newCatMode] ?? 0);
        $updateset['audiocodec'] = (int) ($_POST['audiocodec_sel'][$newCatMode] ?? 0);

        if (user_can('torrentmanage')) {
            $updateset['visible'] = ! empty($_POST['visible']) ? 'yes' : 'no';
        }

        // ─── Promotion state ────────────────────────────────────────────
        if (user_can('torrentonpromotion')) {
            $sp = (int) ($_POST['sel_spstate'] ?? 0);
            $updateset['sp_state'] = in_array($sp, [1, 2, 3, 4, 5, 6, 7], true) ? $sp : 1;

            $promoType = (int) ($_POST['promotion_time_type'] ?? 0);
            if ($promoType === 0) {
                $updateset['promotion_time_type'] = 0;
                $updateset['promotion_until'] = null;
            } elseif ($promoType === 1) {
                $updateset['promotion_time_type'] = 1;
                $updateset['promotion_until'] = null;
            } elseif ($promoType === 2) {
                $promoUntil = (string) ($_POST['promotionuntil'] ?? '');
                if ($promoUntil !== '' && strtotime($torrentAddedTime) <= strtotime($promoUntil)) {
                    $updateset['promotion_time_type'] = 2;
                    $updateset['promotion_until'] = $promoUntil;
                } else {
                    $updateset['promotion_time_type'] = 0;
                    $updateset['promotion_until'] = null;
                }
            }
        }

        // ─── Pos state (sticky) ─────────────────────────────────────────
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
            $updateset['pos_state'] = $posState;
            $updateset['pos_state_until'] = $posStateUntil;
        }

        // ─── Picker (recommended) ────────────────────────────────────────
        $pickInfo = '';
        $doRecommend = false;
        if (user_can('torrentmanage')
            && (($user->picker ?? 'no') === 'yes' || (int) $user->class >= User::CLASS_SYSOP)) {
            $sel = (int) ($_POST['sel_recmovie'] ?? 0);
            $oldType = (string) ($row['picktype'] ?? 'normal');
            if ($sel === 0) {
                if ($oldType !== 'normal') {
                    $pickInfo = ', recomendation canceled!';
                }
                $updateset['picktype'] = 'normal';
                $updateset['picktime'] = null;
                $doRecommend = true;
            } elseif ($sel === 1) {
                if ($oldType !== 'hot') {
                    $pickInfo = ', recommend as hot movie';
                }
                $updateset['picktype'] = 'hot';
                $updateset['picktime'] = date('Y-m-d H:i:s');
                $doRecommend = true;
            } elseif ($sel === 2) {
                if ($oldType !== 'classic') {
                    $pickInfo = ', recommend as classic movie';
                }
                $updateset['picktype'] = 'classic';
                $updateset['picktime'] = date('Y-m-d H:i:s');
                $doRecommend = true;
            } elseif ($sel === 3) {
                if ($oldType !== 'recommended') {
                    $pickInfo = ', recommend as recommended movie';
                }
                $updateset['picktype'] = 'recommended';
                $updateset['picktime'] = date('Y-m-d H:i:s');
                $doRecommend = true;
            }
            if ($doRecommend) {
                do_log('[DEL_HOT_CLASSIC_RESOURCES]');
                foreach ([(string) ($GLOBALS['browsecatmode'] ?? ''), (string) ($GLOBALS['specialcatmode'] ?? '')] as $mode) {
                    if ($mode !== '') {
                        NexusDB::cache_del("hot_{$mode}_resources");
                        NexusDB::cache_del("classic_{$mode}_resources");
                    }
                }
            }
        }

        // ─── Cover from description ─────────────────────────────────────
        $cover = '';
        if (function_exists('format_description') && function_exists('get_image_from_description')) {
            $descriptionArr = format_description($descr);
            $cover = (string) get_image_from_description($descriptionArr, true, false);
        }
        $updateset['cover'] = $cover;

        // ─── HR ─────────────────────────────────────────────────────────
        if (isset($_POST['hr'][$newCatMode])
            && isset(Torrent::$hrStatus[$_POST['hr'][$newCatMode]])
            && user_can('torrent_hr')) {
            $updateset['hr'] = (string) $_POST['hr'][$newCatMode];
        }

        // ─── Price ──────────────────────────────────────────────────────
        if (user_can('torrent-set-price') && $paidTorrentEnabled) {
            $updateset['price'] = (string) ($_POST['price'] ?? 0);
        }

        do_log('[UPDATE_TORRENT] id='.$id.' columns='.implode(',', array_keys($updateset)));
        $affectedRows = NexusDB::table('torrents')->where('id', $id)->update($updateset);

        $torrentInfo = Torrent::query()->find($id);
        if ($torrentInfo !== null) {
            $torrentInfo->extra()->updateOrCreate(['torrent_id' => $id], $extraUpdate);
            if (function_exists('fire_event')) {
                fire_event('torrent_updated', $torrentInfo, $torrentOld);
            }
        }

        // ─── Custom fields ──────────────────────────────────────────────
        if (! empty($_POST['custom_fields'][$newCatMode])) {
            $customField = new Field;
            $customField->saveFieldValues($newCatMode, $id, $_POST['custom_fields'][$newCatMode]);
        }

        // ─── Tags ───────────────────────────────────────────────────────
        $tagIdArr = array_filter($_POST['tags'][$newCatMode] ?? []);
        if (function_exists('insert_torrent_tags')) {
            insert_torrent_tags($id, $tagIdArr, true);
        }

        // ─── write_log ──────────────────────────────────────────────────
        $isOwner = (int) $user->id === (int) ($row['owner'] ?? 0);
        if ($isOwner) {
            if (($row['anonymous'] ?? '') === 'yes') {
                write_log('Torrent '.$id.' ('.$name.') was edited by Anonymous'.$pickInfo);
            } else {
                write_log('Torrent '.$id.' ('.$name.') was edited by '.((string) $user->username).$pickInfo);
            }
        } else {
            write_log('Torrent '.$id.' ('.$name.') was edited by '.((string) $user->username).', Mod Edit'.$pickInfo);
        }

        // ─── Search reindex ─────────────────────────────────────────────
        $this->searchRep->updateTorrent($id);

        if ($affectedRows >= 0) {
            $torrentUrl = sprintf('details.php?id=%s', $row['id']);
            // Owner editing a banned torrent → notify staff via StaffMessage.
            if (($row['banned'] ?? '') === 'yes' && (int) ($row['owner'] ?? 0) === (int) $user->id) {
                StaffMessage::query()->insert([
                    'sender' => (int) $user->id,
                    'subject' => (string) nexus_trans('torrent.owner_update_torrent_subject', [
                        'detail_url' => $torrentUrl,
                        'torrent_name' => $name,
                    ]),
                    'msg' => (string) nexus_trans('torrent.owner_update_torrent_msg', [
                        'detail_url' => $torrentUrl,
                        'torrent_name' => $name,
                    ]),
                    'added' => now(),
                    'permission' => 'torrent-approval',
                ]);
                if (function_exists('clear_staff_message_cache')) {
                    clear_staff_message_cache();
                }
            }
            // Cross-user edit → log to torrent_operation_logs.
            if ((int) ($row['owner'] ?? 0) !== (int) $user->id) {
                TorrentOperationLog::add([
                    'torrent_id' => (int) ($row['id'] ?? 0),
                    'uid' => (int) $user->id,
                    'action_type' => TorrentOperationLog::ACTION_TYPE_EDIT,
                    'comment' => '',
                ], true);
            }
            $this->meiliSearch->doImportFromDatabase((int) ($row['id'] ?? 0));
        }

        // ─── Final 302 ──────────────────────────────────────────────────
        $returl = 'details.php?id='.$id.'&edited=1';
        if (isset($_POST['returnto'])) {
            $returl = (string) $_POST['returnto'];
        }

        return new RedirectResponse(str_starts_with($returl, '/') ? $returl : '/'.$returl);
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
        $heading = (string) ($lang['std_edit_failed'] ?? 'Edit failed');
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
