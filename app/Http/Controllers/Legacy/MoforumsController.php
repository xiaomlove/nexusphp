<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/moforums.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Over-forum (category group)
 * management tool gated on the `forummanage` permission (default
 * class 14 — Administrator). Three branches:
 *
 *   - GET  `?action=forum`          → list over-forums + add form
 *   - GET  `?action=editforum&id=N` → edit form for one over-forum
 *   - GET  `?action=del&id=N`       → delete an over-forum, redirect
 *   - POST `action=addforum`        → insert, redirect to list
 *   - POST `action=editforum`       → update, redirect to list
 *
 * The legacy script called `stdhead()` / `begin_main_frame()` /
 * `end_main_frame()` / `stdfoot()` for site chrome. The controller
 * replaces those with its own minimal chrome-less envelope, the same
 * pattern used by `StaffController`, `PollOverviewController`, and
 * `MakePollController`.
 *
 * URL preserved exactly so `public/forummanage.php:299` (the
 * "Over-forum management" button) keeps working without a template
 * change. POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`; the legacy
 * `<form method=post action="moforums.php">` had no `@csrf` field.
 */
class MoforumsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('forummanage')) {
            abort(403, 'Permission denied.');
        }

        $lang = $this->loadLang();

        $action = (string) ($request->query('action') ?? $request->input('action', ''));
        if ($action === '') {
            $action = 'forum';
        }
        $id = (int) $request->query('id', 0);

        // POST handlers — mutate then redirect.
        if ($request->isMethod('POST')) {
            $postAction = (string) $request->input('action', '');
            if ($postAction === 'addforum') {
                return $this->handleAdd($request, $lang);
            }
            if ($postAction === 'editforum') {
                return $this->handleEdit($request, $lang);
            }
        }

        // DELETE action (GET).
        if ($action === 'del') {
            return $this->handleDelete($id);
        }

        // Edit form.
        if ($action === 'editforum') {
            return $this->renderEditForm($id, $lang);
        }

        // Default: list + add form.
        return $this->renderList($lang);
    }

    // ─── Mutating handlers ───────────────────────────────────────────────

    private function handleAdd(Request $request, array $lang): RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));
        $desc = trim((string) $request->input('desc', ''));
        if ($name === '' && $desc === '') {
            return redirect('/moforums.php?action=forum');
        }

        NexusDB::insert('overforums', [
            'sort' => (int) $request->input('sort', 0),
            'name' => $name,
            'description' => $desc,
            'minclassview' => (int) $request->input('viewclass', 0),
        ]);
        $this->forgetCache();

        return redirect('/moforums.php?action=forum');
    }

    private function handleEdit(Request $request, array $lang): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $name = (string) $request->input('name', '');
        $desc = (string) $request->input('desc', '');
        if ($id === 0 && $name === '' && $desc === '') {
            return redirect('/moforums.php?action=forum');
        }

        NexusDB::table('overforums')->where('id', $id)->update([
            'sort' => (int) $request->input('sort', 0),
            'name' => $name,
            'description' => $desc,
            'minclassview' => (int) $request->input('viewclass', 0),
        ]);
        $this->forgetCache();

        return redirect('/moforums.php?action=forum');
    }

    private function handleDelete(int $id): RedirectResponse
    {
        if ($id > 0) {
            NexusDB::table('overforums')->where('id', $id)->delete();
            $this->forgetCache();
        }

        return redirect('/moforums.php?action=forum');
    }

    // ─── Read-only views ─────────────────────────────────────────────────

    private function renderList(array $lang): Response
    {
        $rows = NexusDB::table('overforums')->orderBy('sort')->get();
        $totalRows = NexusDB::table('overforums')->count();

        // Table of existing over-forums.
        $tableRows = '';
        foreach ($rows as $row) {
            $row = (array) $row;
            $deleteConfirm = htmlspecialchars(
                (string) ($lang['js_sure_to_delete_overforum'] ?? 'Are you sure?'),
                ENT_QUOTES,
            );
            $tableRows .= '<tr>'
                .'<td><a href="forums.php?action=viewforum&forid='.((int) $row['id']).'"><b>'
                .htmlspecialchars((string) ($row['name'] ?? ''))
                .'</b></a><br />'
                .htmlspecialchars((string) ($row['description'] ?? ''))
                .'</td>'
                .'<td>'.get_user_class_name((int) ($row['minclassview'] ?? 0), false, true, true).'</td>'
                .'<td><b>'
                .'<a href="/moforums.php?action=editforum&id='.((int) $row['id']).'">'
                .htmlspecialchars((string) ($lang['text_edit'] ?? 'Edit'))
                .'</a>&nbsp;|&nbsp;'
                .'<a href="javascript:confirm_delete(\''.((int) $row['id']).'\', \''.$deleteConfirm.'\', \'\');">'
                .'<font color=red>'.htmlspecialchars((string) ($lang['text_delete'] ?? 'Delete')).'</font>'
                .'</a>'
                .'</b></td>'
                .'</tr>';
        }
        if ($tableRows === '') {
            $tableRows = '<tr><td colspan="3">'
                .htmlspecialchars((string) ($lang['text_no_records_found'] ?? 'No records found.'))
                .'</td></tr>';
        }

        // Class select for new-forum form.
        $maxClass = (int) get_user_class();
        $classOpts = '';
        for ($i = 0; $i <= $maxClass; $i++) {
            $classOpts .= sprintf(
                '<option value="%d">%s</option>',
                $i,
                htmlspecialchars((string) get_user_class_name($i, false, true, true)),
            );
        }

        // Sort select for new-forum form.
        $sortOpts = '';
        for ($i = 0; $i <= $totalRows + 1; $i++) {
            $sortOpts .= sprintf('<option value="%d">%d</option>', $i, $i);
        }

        $title = htmlspecialchars((string) ($lang['head_overforum_management'] ?? 'Over-forum Management'));
        $fmLink = htmlspecialchars((string) ($lang['text_forum_management'] ?? 'Forum Management'));
        $oLink = htmlspecialchars((string) ($lang['text_overforum_management'] ?? 'Over-forum Management'));
        $colName = htmlspecialchars((string) ($lang['col_name'] ?? 'Name'));
        $colViewed = htmlspecialchars((string) ($lang['col_viewed_by'] ?? 'Viewed by'));
        $colModify = htmlspecialchars((string) ($lang['col_modify'] ?? 'Modify'));
        $newTitle = htmlspecialchars((string) ($lang['text_new_overforum'] ?? 'New Over-forum'));
        $lblName = htmlspecialchars((string) ($lang['text_overforum_name'] ?? 'Name'));
        $lblDesc = htmlspecialchars((string) ($lang['text_overforum_description'] ?? 'Description'));
        $lblView = htmlspecialchars((string) ($lang['text_minimum_view_permission'] ?? 'Min. view permission'));
        $lblOrder = htmlspecialchars((string) ($lang['text_overforum_order'] ?? 'Order'));
        $lblOrderN = htmlspecialchars((string) ($lang['text_overforum_order_note'] ?? ''));
        $submitLbl = htmlspecialchars((string) ($lang['submit_make_overforum'] ?? 'Create'));

        $body = '<h2 class="transparentbg" align="center">'
            .'<a class="faqlink" href="forummanage.php">'.$fmLink.'</a>'
            .'<b>--></b>'.$oLink
            .'</h2><br />'
            .'<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">'
            .'<tr>'
            .'<td class="colhead" align="left">'.$colName.'</td>'
            .'<td class="colhead">'.$colViewed.'</td>'
            .'<td class="colhead">'.$colModify.'</td>'
            .'</tr>'
            .$tableRows
            .'</table>'
            .'<br /><br />'
            .'<form method="post" action="/moforums.php">'
            .'<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">'
            .'<tr align="center"><td colspan="2" class="colhead">'.$newTitle.'</td></tr>'
            .'<tr><td><b>'.$lblName.'</b></td>'
            .'<td><input name="name" type="text" style="width:200px" maxlength="60"></td></tr>'
            .'<tr><td><b>'.$lblDesc.'</b></td>'
            .'<td><input name="desc" type="text" style="width:400px" maxlength="200"></td></tr>'
            .'<tr><td><b>'.$lblView.'</b></td>'
            .'<td><select name="viewclass">'.$classOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblOrder.'</b></td>'
            .'<td><select name="sort">'.$sortOpts.'</select> '.$lblOrderN.'</td></tr>'
            .'<tr align="center"><td colspan="2">'
            .'<input type="hidden" name="action" value="addforum">'
            .'<input type="submit" name="Submit" value="'.$submitLbl.'">'
            .'</td></tr>'
            .'</table></form>';

        return $this->wrap($title, $body);
    }

    private function renderEditForm(int $id, array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_overforum_management'] ?? 'Over-forum Management'));
        $fmLink = htmlspecialchars((string) ($lang['text_forum_management'] ?? 'Forum Management'));
        $oLink = htmlspecialchars((string) ($lang['text_overforum_management'] ?? 'Over-forum Management'));
        $editOF = htmlspecialchars((string) ($lang['text_edit_overforum'] ?? 'Edit Over-forum'));

        if ($id <= 0) {
            return $this->wrap($title, '<p>'.htmlspecialchars((string) ($lang['text_no_records_found'] ?? 'No records found.')).'</p>');
        }

        $rowObj = NexusDB::table('overforums')->where('id', $id)->first();
        if ($rowObj === null) {
            return $this->wrap($title, '<p>'.htmlspecialchars((string) ($lang['text_no_records_found'] ?? 'No records found.')).'</p>');
        }
        $row = (array) $rowObj;

        $maxClass = (int) get_user_class();
        $totalRows = (int) NexusDB::table('overforums')->count();

        $classOpts = '';
        for ($i = 0; $i <= $maxClass; $i++) {
            $selected = ((int) ($row['minclassview'] ?? 0) === $i) ? ' selected' : '';
            $classOpts .= sprintf(
                '<option value="%d"%s>%s</option>',
                $i,
                $selected,
                htmlspecialchars((string) get_user_class_name($i, false, true, true)),
            );
        }

        $sortOpts = '';
        for ($i = 0; $i <= $totalRows + 1; $i++) {
            $selected = ((int) ($row['sort'] ?? 0) === $i) ? ' selected' : '';
            $sortOpts .= sprintf('<option value="%d"%s>%d</option>', $i, $selected, $i);
        }

        $nameVal = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES);
        $descVal = htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES);
        $lblName = htmlspecialchars((string) ($lang['text_overforum_name'] ?? 'Name'));
        $lblDesc = htmlspecialchars((string) ($lang['text_overforum_description'] ?? 'Description'));
        $lblView = htmlspecialchars((string) ($lang['text_minimum_view_permission'] ?? 'Min. view permission'));
        $lblOrder = htmlspecialchars((string) ($lang['text_overforum_order'] ?? 'Order'));
        $lblOrderN = htmlspecialchars((string) ($lang['text_overforum_order_note'] ?? ''));
        $submitLbl = htmlspecialchars((string) ($lang['submit_edit_overforum'] ?? 'Save'));

        $body = '<h2 class="transparentbg" align="center">'
            .'<a class="faqlink" href="forummanage.php">'.$fmLink.'</a>'
            .'<b>--></b>'
            .'<a class="faqlink" href="/moforums.php">'.$oLink.'</a>'
            .'<b>--></b>'.$editOF
            .'</h2><br />'
            .'<form method="post" action="/moforums.php">'
            .'<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">'
            .'<tr align="center"><td colspan="2" class="colhead">'.$editOF.' -- '.$nameVal.'</td></tr>'
            .'<tr><td><b>'.$lblName.'</b></td>'
            .'<td><input name="name" type="text" style="width:200px" maxlength="60" value="'.$nameVal.'"></td></tr>'
            .'<tr><td><b>'.$lblDesc.'</b></td>'
            .'<td><input name="desc" type="text" style="width:400px" maxlength="200" value="'.$descVal.'"></td></tr>'
            .'<tr><td><b>'.$lblView.'</b></td>'
            .'<td><select name="viewclass">'.$classOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblOrder.'</b></td>'
            .'<td><select name="sort">'.$sortOpts.'</select> '.$lblOrderN.'</td></tr>'
            .'<tr align="center"><td colspan="2">'
            .'<input type="hidden" name="action" value="editforum">'
            .'<input type="hidden" name="id" value="'.$id.'">'
            .'<input type="submit" name="Submit" value="'.$submitLbl.'">'
            .'</td></tr>'
            .'</table></form>';

        return $this->wrap($title, $body);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function forgetCache(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('overforums_list');

            return;
        }
        NexusDB::cache_del('overforums_list');
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
    private function loadLang(): array
    {
        $path = base_path(get_langfile_path('moforums.php'));
        $lang_moforums = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_moforums) ? $lang_moforums : [];
    }
}
