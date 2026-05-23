<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Nexus\PTGen\PTGen;

/**
 * Replacement for `public/upload.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. GET-only torrent-upload form.
 * Permission gate: `$CURUSER['uploadpos'] !== 'no'` AND
 * (`user_can_upload('torrents')` OR `user_can_upload('music')` OR
 * has an `offers.allowed='allowed'` row). Form posts to
 * `/takeupload.php` (still legacy, deferred).
 *
 * URL preserved exactly so:
 *   - `include/functions.php:1887` (the global navigation
 *     "Upload" link),
 *   - any in-the-wild bookmarks / cross-site links,
 *   - the legacy `?details=1` post-success redirect chain
 *   keep working without template / JS changes.
 *
 * Chrome-less envelope (same pattern as `EditController` /
 * `ForummanageController` / `MoforumsController`). The legacy
 * `tr()` / `genrelist()` / `textbbcode()` / `datetimepicker_input()`
 * helpers from `include/functions.php` are still used to keep
 * the form fields byte-identical to the legacy rendering.
 */
class UploadController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly TorrentRepository $torrentRep,
        private readonly SearchBoxRepository $searchBoxRep,
        private readonly TagRepository $tagRep,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }
        if (($user->uploadpos ?? 'yes') === 'no') {
            $lang = $this->loadLang('upload.php');
            abort(403, (string) ($lang['std_unauthorized_to_upload'] ?? 'Unauthorized to upload.'));
        }

        $lang = $this->loadLang('upload.php');
        $langEdit = $this->loadLang('edit.php');
        $langFunctions = $GLOBALS['lang_functions'] ?? [];

        $enableOffer = ($GLOBALS['enableoffer'] ?? '') === 'yes';
        $hasAllowedOffer = 0;
        if ($enableOffer) {
            $hasAllowedOffer = (int) NexusDB::table('offers')
                ->where('allowed', 'allowed')
                ->where('userid', (int) $user->id)
                ->count();
        }
        $uploadFreely = (bool) user_can_upload('torrents');
        $allowTorrents = ($hasAllowedOffer > 0 || $uploadFreely);
        $allowSpecial = (bool) user_can_upload('music');
        if (! $allowTorrents && ! $allowSpecial) {
            abort(403, (string) ($lang['std_please_offer'] ?? 'Please make an offer first.'));
        }
        $allowTwoSec = ($allowTorrents && $allowSpecial);

        $browseCatMode = (string) ($GLOBALS['browsecatmode'] ?? '');
        $specialCatMode = (string) ($GLOBALS['specialcatmode'] ?? '');

        $settingMain = (array) get_setting('main');
        $maxTorrentSize = (int) ($GLOBALS['max_torrent_size'] ?? 0);
        $torrentDir = (string) ($GLOBALS['torrent_dir'] ?? '');

        $body = $this->renderForm(
            $user, $lang, $langEdit, $langFunctions,
            $allowTorrents, $allowSpecial, $allowTwoSec, $uploadFreely,
            $browseCatMode, $specialCatMode,
            $settingMain, $maxTorrentSize, $torrentDir,
        );

        return $this->wrap((string) ($lang['head_upload'] ?? 'Upload'), $body);
    }

    /**
     * @param  array<string,string>  $lang
     * @param  array<string,string>  $langEdit
     * @param  array<string,string>  $langFunctions
     * @param  array<string,mixed>  $settingMain
     */
    private function renderForm(
        User $user,
        array $lang,
        array $langEdit,
        array $langFunctions,
        bool $allowTorrents,
        bool $allowSpecial,
        bool $allowTwoSec,
        bool $uploadFreely,
        string $browseCatMode,
        string $specialCatMode,
        array $settingMain,
        int $maxTorrentSize,
        string $torrentDir,
    ): string {
        ob_start();
        echo '<form id="compose" enctype="multipart/form-data" action="/takeupload.php" method="post" name="upload">';
        echo '<p align="center">'.(string) ($lang['text_red_star_required'] ?? 'Fields marked with a red star are required.').'</p>';
        echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';
        echo '<tr><td class="colhead" colspan="2" align="center">'
            .(string) ($lang['text_tracker_url'] ?? 'Tracker URL').': &nbsp;&nbsp;&nbsp;&nbsp;<b>'
            .(string) get_tracker_schema_and_host((int) $user->tracker_url_id, true)
            .'</b>';
        if (function_exists('getFullDirectory') && ! is_writable(getFullDirectory($torrentDir))) {
            echo '<br /><br /><b>ATTENTION</b>: Torrent directory isn\'t writable. Please contact the administrator.';
        }
        if ($maxTorrentSize === 0) {
            echo '<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator.';
        }
        echo '</td></tr>';

        // Torrent file upload row.
        tr(
            (string) ($lang['row_torrent_file'] ?? 'Torrent file').'<font color="red">*</font>',
            '<input type="file" class="file" id="torrent" name="file" onchange="getname()" />',
            1,
        );

        // Name row.
        if (($GLOBALS['altname_main'] ?? '') === 'yes') {
            tr(
                (string) ($lang['row_torrent_name'] ?? 'Name'),
                '<b>'.(string) ($lang['text_english_title'] ?? 'English title').'</b>&nbsp;'
                .'<input type="text" style="width:250px" name="name" />&nbsp;&nbsp;&nbsp;'
                .'<b>'.(string) ($lang['text_chinese_title'] ?? 'Chinese title').'</b>&nbsp;'
                .'<input type="text" style="width:250px" name="cnname"><br />'
                .'<font class="medium">'.(string) ($lang['text_titles_note'] ?? '').'</font>',
                1,
            );
        } else {
            $autoFillText = (string) ($lang['fill_quality'] ?? '');
            $nameInput = (string) $this->torrentRep->buildUploadFieldInput(
                'name', '', (string) ($lang['text_torrent_name_note'] ?? ''), $autoFillText,
            );
            tr((string) ($lang['row_torrent_name'] ?? 'Name'), $nameInput, 1);
        }

        if (($GLOBALS['smalldescription_main'] ?? '') === 'yes') {
            tr(
                (string) ($lang['row_small_description'] ?? 'Short description'),
                '<input type="text" style="width:99%" name="small_descr" /><br />'
                .'<font class="medium">'.(string) ($lang['text_small_description_note'] ?? '').'</font>',
                1,
            );
        }
        if (function_exists('get_external_tr')) {
            get_external_tr();
        }
        if (($settingMain['enable_pt_gen_system'] ?? '') === 'yes' && class_exists(PTGen::class)) {
            $ptGen = new PTGen;
            echo $ptGen->renderUploadPageFormInput('');
        }
        if (($GLOBALS['enablenfo_main'] ?? '') === 'yes') {
            $viewNfoClass = (int) ($GLOBALS['viewnfo_class'] ?? 0);
            tr(
                (string) ($lang['row_nfo_file'] ?? 'NFO file'),
                '<input type="file" class="file" name="nfo" /><br />'
                .'<font class="medium">'.(string) ($lang['text_only_viewed_by'] ?? '')
                .get_user_class_name($viewNfoClass, false, true, true)
                .(string) ($lang['text_or_above'] ?? '').'</font>',
                1,
            );
        }
        if (user_can('torrent-set-price') && get_setting('torrent.paid_torrent_enabled') === 'yes') {
            $maxPrice = (int) get_setting('torrent.max_price');
            $pricePlaceholder = '';
            if ($maxPrice > 0) {
                $pricePlaceholder = (string) nexus_trans('label.torrent.max_price_help', ['max_price' => $maxPrice]);
            }
            tr(
                (string) nexus_trans('label.torrent.price'),
                '<input type="number" min="0" name="price" placeholder="'.htmlspecialchars($pricePlaceholder, ENT_QUOTES).'" />&nbsp;&nbsp;'
                .nexus_trans('label.torrent.price_help', [
                    'tax_factor' => ((float) get_setting('torrent.tax_factor', 0) * 100).'%',
                ]),
                1,
            );
        }

        echo '<tr><td class="rowhead" style="padding:3px" valign="top">'
            .(string) ($lang['row_description'] ?? 'Description').'<font color="red">*</font></td>'
            .'<td class="rowfollow">';
        textbbcode('upload', 'descr', '', false, 130, true);
        echo '</td></tr>';

        if (($settingMain['enable_technical_info'] ?? '') === 'yes') {
            tr(
                (string) ($langFunctions['text_technical_info'] ?? 'Technical info'),
                '<textarea name="technical_info" rows="8" style="width:99%"></textarea><br/>'
                .(string) ($langFunctions['text_technical_info_help_text'] ?? ''),
                1,
            );
        }

        // Type select(s).
        $s = $s2 = '';
        if ($allowTorrents) {
            $disableSpecial = $allowTwoSec
                ? ' onchange="disableother(\'browsecat\',\'specialcat\')"' : '';
            $s = '<select name="type" id="browsecat" data-mode="'.htmlspecialchars($browseCatMode, ENT_QUOTES).'"'.$disableSpecial.'>'
                .'<option value="0">'.(string) ($lang['select_choose_one'] ?? 'Choose one').'</option>';
            foreach ((array) genrelist($browseCatMode) as $cat) {
                $s .= '<option value="'.((int) ($cat['id'] ?? 0)).'">'
                    .htmlspecialchars((string) ($cat['name'] ?? '')).'</option>';
            }
            $s .= '</select>';
        }
        if ($allowSpecial) {
            $s2 = '<select name="type" id="specialcat" data-mode="'.htmlspecialchars($specialCatMode, ENT_QUOTES).'" '
                .'onchange="disableother(\'specialcat\',\'browsecat\')">'
                .'<option value="0">'.(string) ($lang['select_choose_one'] ?? 'Choose one').'</option>';
            foreach ((array) genrelist($specialCatMode) as $cat) {
                $s2 .= '<option value="'.((int) ($cat['id'] ?? 0)).'">'
                    .htmlspecialchars((string) ($cat['name'] ?? '')).'</option>';
            }
            $s2 .= '</select>';
        }
        $textToBrowse = $allowTwoSec ? (string) ($lang['text_to_browse_section'] ?? '') : '';
        $textToSpecial = $allowTwoSec ? (string) ($lang['text_to_special_section'] ?? '') : '';
        $textTypeNote = $allowTwoSec ? (string) ($lang['text_type_note'] ?? '') : '';
        tr(
            (string) ($lang['row_type'] ?? 'Type').'<font color="red">*</font>',
            $textToBrowse.$s.$textToSpecial.$s2.$textTypeNote,
            1,
        );

        $customField = new Field;
        $hitAndRunRep = new HitAndRunRepository;
        if ($allowTorrents && $browseCatMode !== '') {
            $selectNormal = $this->searchBoxRep->renderTaxonomySelect($browseCatMode);
            tr((string) ($lang['row_quality'] ?? 'Quality'), $selectNormal, 1, 'mode_'.$browseCatMode);
            echo $customField->renderOnUploadPage(0, $browseCatMode);
            echo $hitAndRunRep->renderOnUploadPage('', $browseCatMode);
            tr(
                (string) ($langFunctions['text_tags'] ?? 'Tags'),
                $this->tagRep->renderCheckbox($browseCatMode),
                1, 'mode_'.$browseCatMode,
            );
        }
        if ($allowSpecial && $specialCatMode !== '') {
            $selectSpecial = $this->searchBoxRep->renderTaxonomySelect($specialCatMode);
            tr((string) ($lang['row_quality'] ?? 'Quality'), $selectSpecial, 1, 'mode_'.$specialCatMode);
            echo $customField->renderOnUploadPage(0, $specialCatMode);
            echo $hitAndRunRep->renderOnUploadPage('', $specialCatMode);
            tr(
                (string) ($langFunctions['text_tags'] ?? 'Tags'),
                $this->tagRep->renderCheckbox($specialCatMode),
                1, 'mode_'.$specialCatMode,
            );
        }

        // Offer dropdown.
        $offerRows = NexusDB::table('offers')
            ->where('userid', (int) $user->id)
            ->where('allowed', 'allowed')
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get();
        if (count($offerRows) > 0) {
            $offer = '<select name="offer"><option value="0">'.(string) ($lang['select_choose_one'] ?? 'Choose one').'</option>';
            foreach ($offerRows as $offerRow) {
                $offerRow = (array) $offerRow;
                $offer .= '<option value="'.((int) ($offerRow['id'] ?? 0)).'">'
                    .htmlspecialchars((string) ($offerRow['name'] ?? '')).'</option>';
            }
            $offer .= '</select>';
            $required = (! $uploadFreely && ! $allowSpecial) ? '<font color="red">*</font>' : '';
            tr(
                (string) ($lang['row_your_offer'] ?? 'Your offer').$required,
                $offer.(string) ($lang['text_please_select_offer'] ?? ''),
                1,
            );
        }

        // Pick / pos_state.
        $pickContent = '';
        if (user_can('torrentsticky')) {
            $options = [];
            foreach (Torrent::listPosStates() as $key => $value) {
                $options[] = '<option value="'.$key.'">'.htmlspecialchars((string) ($value['text'] ?? '')).'</option>';
            }
            $pickContent .= '<b>'.(string) ($langEdit['row_torrent_position'] ?? 'Position').':&nbsp;</b>'
                .'<select name="pos_state" style="width:100px">'.implode('', $options).'</select>&nbsp;&nbsp;&nbsp;';
            if (function_exists('datetimepicker_input')) {
                $pickContent .= datetimepicker_input(
                    'pos_state_until', '',
                    (string) nexus_trans('label.deadline').':&nbsp;',
                    ['require_files' => true],
                );
            }
        }
        if (user_can('torrentmanage') && (($user->picker ?? 'no') === 'yes' || (int) $user->class >= User::CLASS_SYSOP)) {
            if ($pickContent !== '') {
                $pickContent .= '<br />';
            }
            $pickContent .= '<b>'.(string) ($langEdit['row_recommended_movie'] ?? 'Recommended movie').':&nbsp;</b>'
                .'<select name="picktype" style="width:100px">';
            foreach (Torrent::listPickInfo(true) as $type => $text) {
                $pickContent .= sprintf(
                    '<option value="%s">%s</option>',
                    htmlspecialchars((string) $type, ENT_QUOTES),
                    htmlspecialchars((string) $text),
                );
            }
            $pickContent .= '</select>';
        }
        if ($pickContent !== '') {
            tr((string) ($langEdit['row_pick'] ?? 'Pick'), $pickContent, 1);
        }

        if (user_can('beanonymous')) {
            tr(
                (string) ($lang['row_show_uploader'] ?? 'Show uploader'),
                '<input type="checkbox" name="uplver" value="yes" />'.(string) ($lang['checkbox_hide_uploader_note'] ?? ''),
                1,
            );
        }

        echo '<tr><td class="toolbox" align="center" colspan="2"><b>'
            .(string) ($lang['text_read_rules'] ?? 'Please read the rules!').'</b> '
            .'<input id="qr" type="submit" class="btn" value="'
            .htmlspecialchars((string) ($lang['submit_upload'] ?? 'Upload'), ENT_QUOTES).'" />'
            .'</td></tr>';
        echo '</table></form>';

        // Inline JS hooks (replaces Nexus::js() footer injection).
        $browseCatModeEsc = htmlspecialchars($browseCatMode, ENT_QUOTES);
        echo <<<'HTML'
<script>
jQuery("#compose").on("change", "select[name=type]", function () {
    var _this = jQuery(this);
    var mode = _this.attr("data-mode");
    var value = _this.val();
    jQuery("tr[relation]").hide();
    if (value > 0) {
        jQuery("tr[relation=mode_" + mode + "]").show();
    }
});
jQuery("tr[relation]").hide();
</script>
HTML;

        return (string) ob_get_clean();
    }

    private function wrap(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }

    /** @return array<string,string> */
    private function loadLang(string $page): array
    {
        $path = base_path(get_langfile_path($page));
        ${$this->varNameForLang($page)} = [];
        if (is_file($path)) {
            require $path;
        }
        $varName = $this->varNameForLang($page);

        return is_array(${$varName} ?? null) ? ${$varName} : [];
    }

    private function varNameForLang(string $page): string
    {
        return 'lang_'.preg_replace('/\.php$/', '', $page);
    }
}
