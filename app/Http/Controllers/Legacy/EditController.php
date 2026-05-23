<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Field\Field;
use Nexus\PTGen\PTGen;

/**
 * Replacement for `public/edit.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. GET-only torrent-edit form.
 * Permission gate: `$CURUSER['id'] == $row['owner']` OR
 * `user_can('torrentmanage')`. Form posts to `/takeedit.php` (still
 * legacy, deferred). Embedded "delete" form posts to `/delete.php`
 * (already migrated → `DeleteTorrentController`).
 *
 * URL preserved exactly so:
 *   - `include/functions.php:3639` (the staff-edit icon),
 *   - any in-the-wild bookmarks / cross-site links,
 *   - the post-edit `?edited=1` redirect chain
 *   keep working without template / JS changes.
 *
 * Chrome-less envelope (same pattern as `UploadController` /
 * `ForummanageController` / `MoforumsController`).
 */
class EditController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
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

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $rowObj = NexusDB::table('torrents')
            ->leftJoin('categories', 'category', '=', 'categories.id')
            ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
            ->where('torrents.id', $id)
            ->selectRaw('torrents.*, categories.mode as cat_mode, torrent_extras.media_info as technical_info, torrent_extras.descr, torrent_extras.pt_gen')
            ->first();
        if ($rowObj === null) {
            abort(404);
        }
        $row = (array) $rowObj;

        $lang = $this->loadLang('edit.php');
        $langFunctions = $GLOBALS['lang_functions'] ?? [];
        $settingMain = (array) get_setting('main');

        if ((int) $user->id !== (int) ($row['owner'] ?? 0) && ! user_can('torrentmanage')) {
            $title = (string) ($lang['head_edit_torrent'] ?? 'Edit torrent').'"'.($row['name'] ?? '').'"';
            $body = '<h1 align="center">'
                .htmlspecialchars((string) ($lang['text_cannot_edit_torrent'] ?? 'You cannot edit this torrent.'))
                .'</h1>'
                .'<p>'.sprintf(
                    (string) ($lang['text_cannot_edit_torrent_note'] ?? 'You cannot edit this torrent: %s.'),
                    htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '')),
                ).'</p>';

            return $this->wrap($title, $body);
        }

        $title = (string) ($lang['head_edit_torrent'] ?? 'Edit torrent').'"'.($row['name'] ?? '').'"';
        $body = $this->renderForm($id, $row, $user, $lang, $langFunctions, $settingMain, $request);

        return $this->wrap($title, $body);
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,string>  $lang
     * @param  array<string,string>  $langFunctions
     * @param  array<string,mixed>  $settingMain
     */
    private function renderForm(
        int $id,
        array $row,
        User $user,
        array $lang,
        array $langFunctions,
        array $settingMain,
        Request $request,
    ): string {
        $browseCatMode = (string) ($GLOBALS['browsecatmode'] ?? '');
        $specialCatMode = (string) ($GLOBALS['specialcatmode'] ?? '');
        $sectionMode = (string) ($row['cat_mode'] ?? $browseCatMode);
        $allowMove = (($GLOBALS['enablespecial'] ?? '') === 'yes' && user_can('movetorrent'));
        if ($sectionMode === $browseCatMode) {
            $otherMode = $specialCatMode;
            $moveNote = (string) ($lang['text_move_to_special'] ?? '');
        } else {
            $otherMode = $browseCatMode;
            $moveNote = (string) ($lang['text_move_to_browse'] ?? '');
        }

        $customField = new Field;
        $hitAndRunRep = new HitAndRunRepository;
        $tagIdArr = TorrentTag::query()->where('torrent_id', $id)->pluck('tag_id')->toArray();

        $returnTo = (string) ($request->query('returnto') ?? '');

        ob_start();
        echo '<form method="post" id="compose" name="edittorrent" action="/takeedit.php" enctype="multipart/form-data">';
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        if ($returnTo !== '') {
            echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($returnTo, ENT_QUOTES).'" />';
        }
        echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';
        echo '<tr><td class="colhead" colspan="2" align="center">'.htmlspecialchars((string) ($row['name'] ?? '')).'</td></tr>';

        tr(
            (string) ($lang['row_torrent_name'] ?? 'Name').'<font color="red">*</font>',
            '<input type="text" style="width:99%" name="name" value="'.htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES).'" />',
            1,
        );
        if (($GLOBALS['smalldescription_main'] ?? '') === 'yes') {
            tr(
                (string) ($lang['row_small_description'] ?? 'Short description'),
                '<input type="text" style="width:99%" name="small_descr" value="'.htmlspecialchars((string) ($row['small_descr'] ?? ''), ENT_QUOTES).'" />',
                1,
            );
        }
        if (function_exists('get_external_tr')) {
            get_external_tr((string) ($row['url'] ?? ''));
        }
        if (($settingMain['enable_pt_gen_system'] ?? '') === 'yes' && class_exists(PTGen::class)) {
            $ptGen = new PTGen;
            echo $ptGen->renderUploadPageFormInput((string) ($row['pt_gen'] ?? ''));
        }

        if (($GLOBALS['enablenfo_main'] ?? '') === 'yes') {
            tr(
                (string) ($lang['row_nfo_file'] ?? 'NFO file'),
                '<font class="medium"><input type="radio" name="nfoaction" value="keep" checked="checked" />'
                .(string) ($lang['radio_keep_current'] ?? 'Keep current')
                .'<input type="radio" name="nfoaction" value="remove" />'
                .(string) ($lang['radio_remove'] ?? 'Remove')
                .'<input id="nfoupdate" type="radio" name="nfoaction" value="update" />'
                .(string) ($lang['radio_update'] ?? 'Update')
                .'</font><br />'
                .'<input type="file" name="nfo" onchange="document.getElementById(\'nfoupdate\').checked=true" />',
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
                '<input type="number" min="0" name="price" value="'.htmlspecialchars((string) ($row['price'] ?? ''), ENT_QUOTES).'" '
                .'placeholder="'.htmlspecialchars($pricePlaceholder, ENT_QUOTES).'" />&nbsp;&nbsp;'
                .nexus_trans('label.torrent.price_help', [
                    'tax_factor' => ((float) get_setting('torrent.tax_factor', 0) * 100).'%',
                ]),
                1,
            );
        }

        echo '<tr><td class="rowhead">'.(string) ($lang['row_description'] ?? 'Description').'<font color="red">*</font></td>'
            .'<td class="rowfollow">';
        textbbcode('edittorrent', 'descr', (string) ($row['descr'] ?? ''), false, 130, true);
        echo '</td></tr>';

        if (($settingMain['enable_technical_info'] ?? '') === 'yes') {
            tr(
                (string) ($langFunctions['text_technical_info'] ?? 'Technical info'),
                '<textarea name="technical_info" rows="8" style="width:99%">'.htmlspecialchars((string) ($row['technical_info'] ?? '')).'</textarea><br/>'
                .(string) ($langFunctions['text_technical_info_help_text'] ?? ''),
                1,
            );
        }

        // Type select(s).
        $s = '<select name="type" id="oricat" data-mode="'.htmlspecialchars($sectionMode, ENT_QUOTES).'">';
        foreach ((array) genrelist($sectionMode) as $cat) {
            $catId = (int) ($cat['id'] ?? 0);
            $selected = ($catId === (int) ($row['category'] ?? 0)) ? ' selected="selected"' : '';
            $s .= '<option value="'.$catId.'"'.$selected.'>'.htmlspecialchars((string) ($cat['name'] ?? '')).'</option>';
        }
        $s .= '</select>';

        $moveCheckbox = '';
        $s2 = '';
        if ($allowMove && $otherMode !== '') {
            $s2 = '<select name="type" id="newcat" disabled data-mode="'.htmlspecialchars($otherMode, ENT_QUOTES).'">';
            foreach ((array) genrelist($otherMode) as $cat) {
                $catId = (int) ($cat['id'] ?? 0);
                $selected = ($catId === (int) ($row['category'] ?? 0)) ? ' selected="selected"' : '';
                $s2 .= '<option value="'.$catId.'"'.$selected.'>'.htmlspecialchars((string) ($cat['name'] ?? '')).'</option>';
            }
            $s2 .= '</select>';
            $moveCheckbox = '<input type="checkbox" id="movecheck" name="movecheck" value="1" '
                .'onclick="disableother2(\'oricat\',\'newcat\')" />';
        }
        tr(
            (string) ($lang['row_type'] ?? 'Type').'<font color="red">*</font>',
            $s.($allowMove ? '&nbsp;&nbsp;'.$moveCheckbox.htmlspecialchars($moveNote).$s2 : ''),
            1,
        );

        $sectionCurrent = $this->searchBoxRep->renderTaxonomySelect($sectionMode, $row);
        tr((string) ($lang['row_quality'] ?? 'Quality'), $sectionCurrent, 1, 'mode_'.$sectionMode);
        echo $customField->renderOnUploadPage($id, $sectionMode);
        echo $hitAndRunRep->renderOnUploadPage((string) ($row['hr'] ?? ''), $sectionMode);
        tr(
            (string) ($langFunctions['text_tags'] ?? 'Tags'),
            $this->tagRep->renderCheckbox($sectionMode, $tagIdArr),
            1, 'mode_'.$sectionMode,
        );
        if ($allowMove && $otherMode !== '') {
            $selectOther = $this->searchBoxRep->renderTaxonomySelect($otherMode, $row);
            tr((string) ($lang['row_quality'] ?? 'Quality'), $selectOther, 1, 'mode_'.$otherMode);
            echo $customField->renderOnUploadPage($id, $otherMode);
            echo $hitAndRunRep->renderOnUploadPage((string) ($row['hr'] ?? ''), $otherMode);
            tr(
                (string) ($langFunctions['text_tags'] ?? 'Tags'),
                $this->tagRep->renderCheckbox($otherMode, $tagIdArr),
                1, 'mode_'.$otherMode,
            );
        }

        // Anonymous / visible checkboxes.
        $rowChecks = [];
        if (user_can('beanonymous') || user_can('torrentmanage')) {
            $checked = (($row['anonymous'] ?? '') === 'yes') ? ' checked="checked"' : '';
            $rowChecks[] = '<label><input type="checkbox" name="anonymous"'.$checked.' value="1" />'
                .(string) ($lang['checkbox_anonymous_note'] ?? '').'</label>';
        }
        if (user_can('torrentmanage')) {
            $checked = (($row['visible'] ?? '') === 'yes') ? ' checked="checked"' : '';
            array_unshift(
                $rowChecks,
                '<label><input id="visible" type="checkbox" name="visible"'.$checked.' value="1" />'
                .(string) ($lang['checkbox_visible'] ?? '').'</label>',
            );
        }
        if (! empty($rowChecks)) {
            tr((string) ($lang['row_check'] ?? 'Flags'), implode('&nbsp;&nbsp;', $rowChecks), 1);
        }

        // Pick / pos_state.
        if (user_can('torrentsticky') || (user_can('torrentmanage') && ($user->picker ?? 'no') === 'yes')) {
            $pickContent = '';
            if (user_can('torrentonpromotion') && function_exists('promotion_selection')) {
                $promotionType = (int) ($row['promotion_time_type'] ?? 0);
                $promotionUntil = (string) ($row['promotion_until'] ?? '');
                $added = (string) ($row['added'] ?? '');
                $pickContent .= '<b>'.(string) ($lang['row_special_torrent'] ?? '').':&nbsp;</b>'
                    .'<select name="sel_spstate" style="width:100px">'
                    .promotion_selection((int) ($row['sp_state'] ?? 0), 0)
                    .'</select>&nbsp;&nbsp;&nbsp;'
                    .'<select name="promotion_time_type" onchange="if (this.value == \'2\') {document.getElementById(\'promotion_until_note\').style.display=\'\';} else {document.getElementById(\'promotion_until_note\').style.display=\'none\';}">'
                    .'<option value="0"'.($promotionType === 0 ? ' selected="selected"' : '').'>'.(string) ($lang['select_use_global_setting'] ?? 'Global').'</option>'
                    .'<option value="1"'.($promotionType === 1 ? ' selected="selected"' : '').'>'.(string) ($lang['select_forever'] ?? 'Forever').'</option>'
                    .'<option value="2"'.($promotionType === 2 ? ' selected="selected"' : '').'>'.(string) ($lang['select_until'] ?? 'Until').'</option>'
                    .'</select><span id="promotion_until_note"'.($promotionType === 2 ? '' : ' style="display:none"').'>'
                    .'<input type="text" id="promotionuntiltime" name="promotionuntil" style="width:120px" value="'
                    .htmlspecialchars($promotionUntil > $added ? $promotionUntil : '', ENT_QUOTES).'" />'
                    .'&nbsp;</span>&nbsp;&nbsp;';
            }
            if (user_can('torrentsticky')) {
                if ($pickContent !== '') {
                    $pickContent .= '<br />';
                }
                $options = [];
                foreach (Torrent::listPosStates() as $key => $value) {
                    $sel = ($row['pos_state'] ?? '') === (string) $key ? ' selected="selected"' : '';
                    $options[] = '<option'.$sel.' value="'.htmlspecialchars((string) $key, ENT_QUOTES).'">'.htmlspecialchars((string) ($value['text'] ?? '')).'</option>';
                }
                $pickContent .= '<b>'.(string) ($lang['row_torrent_position'] ?? 'Position').':&nbsp;</b>'
                    .'<select name="pos_state" style="width:100px">'.implode('', $options).'</select>&nbsp;&nbsp;&nbsp;';
                if (function_exists('datetimepicker_input')) {
                    $pickContent .= datetimepicker_input(
                        'pos_state_until',
                        (string) ($row['pos_state_until'] ?? ''),
                        (string) nexus_trans('label.deadline').':&nbsp;',
                        ['require_files' => true],
                    );
                }
            }
            if (user_can('torrentmanage') && (($user->picker ?? 'no') === 'yes' || (int) $user->class >= User::CLASS_SYSOP)) {
                if ($pickContent !== '') {
                    $pickContent .= '<br />';
                }
                $picktype = (string) ($row['picktype'] ?? 'normal');
                $pickContent .= '<b>'.(string) ($lang['row_recommended_movie'] ?? 'Recommended').':&nbsp;</b>'
                    .'<select name="sel_recmovie" style="width:100px">'
                    .'<option'.($picktype === 'normal' ? ' selected="selected"' : '').' value="0">'.(string) ($lang['select_normal'] ?? 'Normal').'</option>'
                    .'<option'.($picktype === 'hot' ? ' selected="selected"' : '').' value="1">'.(string) ($lang['select_hot'] ?? 'Hot').'</option>'
                    .'<option'.($picktype === 'classic' ? ' selected="selected"' : '').' value="2">'.(string) ($lang['select_classic'] ?? 'Classic').'</option>'
                    .'<option'.($picktype === 'recommended' ? ' selected="selected"' : '').' value="3">'.(string) ($lang['select_recommended'] ?? 'Recommended').'</option>'
                    .'</select>';
            }
            if ($pickContent !== '') {
                tr((string) ($lang['row_pick'] ?? 'Pick'), $pickContent, 1);
            }
        }

        echo '<tr><td class="toolbox" colspan="2" align="center">'
            .'<input id="qr" type="submit" value="'.htmlspecialchars((string) ($lang['submit_edit_it'] ?? 'Save'), ENT_QUOTES).'" /> '
            .'<input type="reset" value="'.htmlspecialchars((string) ($lang['submit_revert_changes'] ?? 'Reset'), ENT_QUOTES).'" />'
            .'</td></tr>';
        echo '</table>';
        echo '</form>';

        // Delete-form (admin-only).
        if (user_can('torrent-delete') && user_can('torrentmanage')) {
            echo '<br /><br />';
            echo '<form method="post" action="/delete.php">';
            echo '<input type="hidden" name="id" value="'.$id.'" />';
            if ($returnTo !== '') {
                echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($returnTo, ENT_QUOTES).'" />';
            }
            echo '<table border="1" cellspacing="0" cellpadding="5">';
            echo '<tr><td class="colhead" align="left" colspan="2">'.(string) ($lang['text_delete_torrent'] ?? 'Delete torrent').'</td></tr>';
            tr('<input name="reasontype" type="radio" value="1" />&nbsp;'.(string) ($lang['radio_dead'] ?? 'Dead'),
                (string) ($lang['text_dead_note'] ?? ''), 1);
            tr('<input name="reasontype" type="radio" value="2" />&nbsp;'.(string) ($lang['radio_dupe'] ?? 'Dupe'),
                '<input type="text" style="width:200px" name="reason[]" />', 1);
            tr('<input name="reasontype" type="radio" value="3" />&nbsp;'.(string) ($lang['radio_nuked'] ?? 'Nuked'),
                '<input type="text" style="width:200px" name="reason[]" />', 1);
            tr('<input name="reasontype" type="radio" value="4" />&nbsp;'.(string) ($lang['radio_rules'] ?? 'Rules'),
                '<input type="text" style="width:200px" name="reason[]" />'.(string) ($lang['text_req'] ?? ''), 1);
            tr('<input name="reasontype" type="radio" value="5" checked="checked" />&nbsp;'.(string) ($lang['radio_other'] ?? 'Other'),
                '<input type="text" style="width:200px" name="reason[]" />'.(string) ($lang['text_req'] ?? ''), 1);
            echo '<tr><td class="toolbox" colspan="2" align="center">'
                .'<input type="submit" value="'.htmlspecialchars((string) ($lang['submit_delete_it'] ?? 'Delete'), ENT_QUOTES).'" />'
                .'</td></tr>';
            echo '</table>';
            echo '</form>';
        }

        // Inline JS hooks (replaces Nexus::js() footer injection).
        $sectionModeEsc = htmlspecialchars($sectionMode, ENT_QUOTES);
        echo <<<HTML
<script>
jQuery("#movecheck").on("change", function () {
    var _this = jQuery(this);
    var checked = _this.prop("checked");
    var activeSelect = checked ? jQuery("#newcat") : jQuery("#oricat");
    var mode = activeSelect.attr("data-mode");
    jQuery("tr[relation]").hide();
    jQuery("tr[relation=mode_" + mode + "]").show();
});
jQuery("tr[relation]").hide();
jQuery("tr[relation=mode_{$sectionModeEsc}]").show();
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
        $varName = 'lang_'.preg_replace('/\.php$/', '', $page);
        ${$varName} = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array(${$varName} ?? null) ? ${$varName} : [];
    }
}
