<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/forummanage.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Forum (sub-forum) management page
 * gated on the `forummanage` permission (default class 14 —
 * Administrator). The legacy script handles four branches:
 *
 *   - GET  (default)                → list forums + edit/delete buttons
 *   - GET  ?action=newforum         → create form
 *   - GET  ?action=editforum&id=N   → edit form for one forum
 *   - GET  ?action=del&id=N         → delete forum + topics + posts, redirect
 *   - POST action=addforum          → insert, redirect to list
 *   - POST action=editforum         → update, redirect to list
 *
 * URL preserved exactly so:
 *   - `database/seeders/SysoppanelTableSeeder.php:30`
 *     (`url=forummanage.php`) keeps working without a data migration
 *   - `public/forums.php:1781` (the "Forum manager" link rendered for
 *     `forummanage`-able users in the forum index footer) keeps
 *     working without a template change
 *   - The "back to forum management" link in `MoforumsController`
 *     keeps pointing here
 *
 * POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`; the legacy
 * `<form method=post action="forummanage.php">` had no `@csrf` field.
 *
 * The legacy script called `stdhead()` / `begin_main_frame()` /
 * `end_main_frame()` / `stdfoot()` for site chrome. The controller
 * renders a chrome-less envelope (same pattern as
 * `MoforumsController` / `PollOverviewController`).
 */
class ForummanageController extends Controller
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

        // POST handlers — mutate then redirect.
        if ($request->isMethod('POST')) {
            $postAction = (string) $request->input('action', '');
            if ($postAction === 'addforum') {
                return $this->handleAdd($request);
            }
            if ($postAction === 'editforum') {
                return $this->handleEdit($request);
            }
        }

        $action = (string) $request->query('action', '');

        if ($action === 'del') {
            return $this->handleDelete($request);
        }
        if ($action === 'editforum') {
            return $this->renderEditForm($request, $user, $lang);
        }
        if ($action === 'newforum') {
            return $this->renderNewForm($user, $lang);
        }

        return $this->renderList($lang);
    }

    // ─── Mutating handlers ───────────────────────────────────────────────

    private function handleDelete(Request $request): RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return redirect('/forummanage.php');
        }

        // Cascade: delete posts of every topic in this forum, then topics,
        // then the forum itself, then any moderator pivot rows.
        $topicIds = NexusDB::table('topics')
            ->where('forumid', $id)
            ->pluck('id')
            ->all();

        if (! empty($topicIds)) {
            NexusDB::table('posts')->whereIn('topicid', $topicIds)->delete();
        }
        NexusDB::table('topics')->where('forumid', $id)->delete();
        NexusDB::table('forums')->where('id', $id)->delete();
        NexusDB::table('forummods')->where('forumid', $id)->delete();

        $this->forgetCache();

        return redirect('/forummanage.php');
    }

    private function handleAdd(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));
        $desc = trim((string) $request->input('desc', ''));
        if ($name === '' && $desc === '') {
            return redirect('/forummanage.php');
        }

        $id = (int) NexusDB::insert('forums', [
            'sort' => (int) $request->input('sort', 0),
            'name' => $name,
            'description' => $desc,
            'minclassread' => (int) $request->input('readclass', 0),
            'minclasswrite' => (int) $request->input('writeclass', 0),
            'minclasscreate' => (int) $request->input('createclass', 0),
            'forid' => (int) $request->input('overforums', 0),
        ]);

        $this->forgetCache();

        $moderator = trim((string) $request->input('moderator', ''));
        if ($moderator !== '' && function_exists('set_forum_moderators')) {
            set_forum_moderators($moderator, $id);
        }

        return redirect('/forummanage.php');
    }

    private function handleEdit(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $name = (string) $request->input('name', '');
        $desc = (string) $request->input('desc', '');
        if ($id === 0 && $name === '' && $desc === '') {
            return redirect('/forummanage.php');
        }

        $moderator = trim((string) $request->input('moderator', ''));
        if ($moderator !== '' && function_exists('set_forum_moderators')) {
            set_forum_moderators($moderator, $id);
        } else {
            NexusDB::table('forummods')->where('forumid', $id)->delete();
        }

        NexusDB::table('forums')
            ->where('id', $id)
            ->update([
                'sort' => (int) $request->input('sort', 0),
                'name' => $name,
                'description' => $desc,
                'forid' => (int) $request->input('overforums', 0),
                'minclassread' => (int) $request->input('readclass', 0),
                'minclasswrite' => (int) $request->input('writeclass', 0),
                'minclasscreate' => (int) $request->input('createclass', 0),
            ]);

        $this->forgetCache();

        return redirect('/forummanage.php');
    }

    // ─── Read-only views ─────────────────────────────────────────────────

    /**
     * @param  array<string,string>  $lang
     */
    private function renderList(array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_forum_management'] ?? 'Forum Management'));
        $titleH2 = htmlspecialchars((string) ($lang['text_forum_management'] ?? 'Forum Management'));
        $btnOver = htmlspecialchars((string) ($lang['submit_overforum_management'] ?? 'Over-forum Management'), ENT_QUOTES);
        $btnAdd = htmlspecialchars((string) ($lang['submit_add_forum'] ?? 'Add Forum'), ENT_QUOTES);
        $colName = htmlspecialchars((string) ($lang['col_name'] ?? 'Name'));
        $colOver = htmlspecialchars((string) ($lang['col_overforum'] ?? 'Over-forum'));
        $colRead = htmlspecialchars((string) ($lang['col_read'] ?? 'Read'));
        $colWrite = htmlspecialchars((string) ($lang['col_write'] ?? 'Write'));
        $colCreate = htmlspecialchars((string) ($lang['col_create_topic'] ?? 'Create Topic'));
        $colMod = htmlspecialchars((string) ($lang['col_moderator'] ?? 'Moderator'));
        $colModify = htmlspecialchars((string) ($lang['col_modify'] ?? 'Modify'));
        $textNa = htmlspecialchars((string) ($lang['text_not_available'] ?? 'N/A'));
        $textEdit = htmlspecialchars((string) ($lang['text_edit'] ?? 'Edit'));
        $textDelete = htmlspecialchars((string) ($lang['text_delete'] ?? 'Delete'));
        $textNoRows = htmlspecialchars((string) ($lang['text_no_records_found'] ?? 'No records found.'));
        $jsConfirm = htmlspecialchars(
            (string) ($lang['js_sure_to_delete_forum'] ?? 'Are you sure you want to delete this forum?'),
            ENT_QUOTES,
        );

        $rows = NexusDB::select(
            'SELECT forums.*, overforums.name AS of_name FROM forums '
            .'LEFT JOIN overforums ON forums.forid = overforums.id ORDER BY forums.sort ASC'
        );

        $body = '<h2 class="transparentbg" align="center">'.$titleH2.'</h2>'
            .'<table border="0" class="main" cellspacing="0" cellpadding="5" width="1%"><tr>'
            .'<td class="embedded" align="left">'
            .'<form method="get" action="/moforums.php">'
            .'<input type="submit" value="'.$btnOver.'" class="btn"></form>'
            .'</td>'
            .'<td class="embedded" align="left">'
            .'<form method="get" action="/forummanage.php">'
            .'<input type="hidden" name="action" value="newforum">'
            .'<input type="submit" value="'.$btnAdd.'" class="btn"></form>'
            .'</td>'
            .'</tr></table>'
            .'<table width="100%" border="0" align="center" cellpadding="2" cellspacing="0">'
            .'<tr>'
            .'<td class="colhead" align="left">'.$colName.'</td>'
            .'<td class="colhead">'.$colOver.'</td>'
            .'<td class="colhead">'.$colRead.'</td>'
            .'<td class="colhead">'.$colWrite.'</td>'
            .'<td class="colhead">'.$colCreate.'</td>'
            .'<td class="colhead">'.$colMod.'</td>'
            .'<td class="colhead">'.$colModify.'</td>'
            .'</tr>';

        if (count($rows) === 0) {
            $body .= '<tr><td colspan="7">'.$textNoRows.'</td></tr>';
        } else {
            foreach ($rows as $row) {
                $row = (array) $row;
                $moderators = function_exists('get_forum_moderators')
                    ? (string) get_forum_moderators((int) $row['id'], false)
                    : '';
                if ($moderators === '') {
                    $moderators = $textNa;
                }
                $body .= '<tr>'
                    .'<td><a href="forums.php?action=viewforum&forumid='.(int) $row['id'].'">'
                    .'<b>'.htmlspecialchars((string) ($row['name'] ?? '')).'</b></a><br />'
                    .htmlspecialchars((string) ($row['description'] ?? '')).'</td>'
                    .'<td>'.htmlspecialchars((string) ($row['of_name'] ?? '')).'</td>'
                    .'<td>'.get_user_class_name((int) ($row['minclassread'] ?? 0), false, true, true).'</td>'
                    .'<td>'.get_user_class_name((int) ($row['minclasswrite'] ?? 0), false, true, true).'</td>'
                    .'<td>'.get_user_class_name((int) ($row['minclasscreate'] ?? 0), false, true, true).'</td>'
                    .'<td>'.$moderators.'</td>'
                    .'<td><b>'
                    .'<a href="/forummanage.php?action=editforum&id='.(int) $row['id'].'">'.$textEdit.'</a>'
                    .'&nbsp;|&nbsp;'
                    .'<a href="javascript:confirm_delete(\''.(int) $row['id'].'\', \''.$jsConfirm.'\', \'\');">'
                    .'<font color="red">'.$textDelete.'</font></a>'
                    .'</b></td>'
                    .'</tr>';
            }
        }

        $body .= '</table>';

        return $this->wrap($title, $body);
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderEditForm(Request $request, User $user, array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_forum_management'] ?? 'Forum Management'));
        $textFm = htmlspecialchars((string) ($lang['text_forum_management'] ?? 'Forum Management'));
        $textEdit = htmlspecialchars((string) ($lang['text_edit_forum'] ?? 'Edit Forum'));
        $textNoRows = htmlspecialchars((string) ($lang['text_no_records_found'] ?? 'No records found.'));

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return $this->wrap($title, '<p>'.$textNoRows.'</p>');
        }

        $rowObj = NexusDB::table('forums')->where('id', $id)->first();
        if ($rowObj === null) {
            return $this->wrap($title, '<p>'.$textNoRows.'</p>');
        }
        $row = (array) $rowObj;

        $body = '<h1 align="center">'
            .'<a class="faqlink" href="/forummanage.php">'.$textFm.'</a>'
            .'<b>--></b>'.$textEdit
            .'</h1><br />'
            .'<form method="post" action="/forummanage.php">'
            .'<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">'
            .'<tr align="center"><td colspan="2" class="colhead">'.$textEdit
            .' -- '.htmlspecialchars((string) ($row['name'] ?? '')).'</td></tr>'
            .$this->renderFormFields($row, $lang, isEdit: true)
            .'<tr align="center"><td colspan="2">'
            .'<input type="hidden" name="action" value="editforum">'
            .'<input type="hidden" name="id" value="'.$id.'">'
            .'<input type="submit" name="Submit" value="'
            .htmlspecialchars((string) ($lang['submit_edit_forum'] ?? 'Save'), ENT_QUOTES)
            .'" class="btn">'
            .'</td></tr>'
            .'</table></form>';

        return $this->wrap($title, $body);
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderNewForm(User $user, array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_forum_management'] ?? 'Forum Management'));
        $textFm = htmlspecialchars((string) ($lang['text_forum_management'] ?? 'Forum Management'));
        $textAdd = htmlspecialchars((string) ($lang['text_add_forum'] ?? 'Add Forum'));
        $textMake = htmlspecialchars((string) ($lang['text_make_new_forum'] ?? 'Make new forum'));

        // Default class selections seed from the current user's class.
        $defaults = [
            'name' => '',
            'description' => '',
            'forid' => 0,
            'sort' => 0,
            'minclassread' => (int) $user->class,
            'minclasswrite' => (int) $user->class,
            'minclasscreate' => (int) $user->class,
        ];

        $body = '<h2 class="transparentbg" align="center">'
            .'<a class="faqlink" href="/forummanage.php">'.$textFm.'</a>'
            .'<b>--></b>'.$textAdd
            .'</h2><br />'
            .'<form method="post" action="/forummanage.php">'
            .'<table width="100%" border="0" cellspacing="0" cellpadding="3" align="center">'
            .'<tr align="center"><td colspan="2" class="colhead">'.$textMake.'</td></tr>'
            .$this->renderFormFields($defaults, $lang, isEdit: false)
            .'<tr align="center"><td colspan="2">'
            .'<input type="hidden" name="action" value="addforum">'
            .'<input type="submit" name="Submit" value="'
            .htmlspecialchars((string) ($lang['submit_make_forum'] ?? 'Create'), ENT_QUOTES)
            .'" class="btn">'
            .'</td></tr>'
            .'</table></form>';

        return $this->wrap($title, $body);
    }

    /**
     * Shared form-field block for the new/edit forms.
     *
     * @param  array<string,mixed>  $row
     * @param  array<string,string>  $lang
     */
    private function renderFormFields(array $row, array $lang, bool $isEdit): string
    {
        $name = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES);
        $desc = htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES);
        $forid = (int) ($row['forid'] ?? 0);
        $sort = (int) ($row['sort'] ?? 0);
        $minRead = (int) ($row['minclassread'] ?? 0);
        $minWrite = (int) ($row['minclasswrite'] ?? 0);
        $minCreate = (int) ($row['minclasscreate'] ?? 0);

        // Over-forum select.
        $overOpts = '';
        foreach (NexusDB::table('overforums')->get() as $arr) {
            $arr = (array) $arr;
            $i = (int) $arr['id'];
            $overOpts .= '<option value="'.$i.'"'.($forid === $i ? ' selected' : '').'>'
                .htmlspecialchars((string) ($arr['name'] ?? ''))
                .'</option>';
        }

        $maxClass = (int) get_user_class();

        $readOpts = '';
        $writeOpts = '';
        $createOpts = '';
        for ($i = 0; $i <= $maxClass; $i++) {
            $label = htmlspecialchars((string) get_user_class_name($i, false, true, true));
            $readOpts .= '<option value="'.$i.'"'.($minRead === $i ? ' selected' : '').'>'.$label.'</option>';
            $writeOpts .= '<option value="'.$i.'"'.($minWrite === $i ? ' selected' : '').'>'.$label.'</option>';
            $createOpts .= '<option value="'.$i.'"'.($minCreate === $i ? ' selected' : '').'>'.$label.'</option>';
        }

        $totalForums = (int) NexusDB::table('forums')->count();
        $maxSort = $totalForums + 1;
        $sortOpts = '';
        for ($i = 0; $i <= $maxSort; $i++) {
            $sortOpts .= '<option value="'.$i.'"'.($sort === $i ? ' selected' : '').'>'.$i.'</option>';
        }

        $moderatorVal = '';
        if ($isEdit && function_exists('get_forum_moderators') && isset($row['id'])) {
            $moderatorVal = htmlspecialchars(
                (string) get_forum_moderators((int) $row['id'], true),
                ENT_QUOTES,
            );
        }

        $lblName = htmlspecialchars((string) ($lang['row_forum_name'] ?? 'Name'));
        $lblDesc = htmlspecialchars((string) ($lang['row_forum_description'] ?? 'Description'));
        $lblOver = htmlspecialchars((string) ($lang['row_overforum'] ?? 'Over-forum'));
        $lblMod = htmlspecialchars((string) ($lang['row_moderator'] ?? 'Moderator'));
        $lblModNote = htmlspecialchars((string) ($lang['text_moderator_note'] ?? ''));
        $lblRead = htmlspecialchars((string) ($lang['row_minimum_read_permission'] ?? 'Min. read permission'));
        $lblWrite = htmlspecialchars((string) ($lang['row_minimum_write_permission'] ?? 'Min. write permission'));
        $lblCreate = htmlspecialchars((string) ($lang['row_minimum_create_topic_permission'] ?? 'Min. create-topic permission'));
        $lblOrder = htmlspecialchars((string) ($lang['row_forum_order'] ?? 'Order'));
        $lblOrderNote = htmlspecialchars((string) ($lang['text_forum_order_note'] ?? ''));

        return ''
            .'<tr><td><b>'.$lblName.'</b></td>'
            .'<td><input name="name" type="text" style="width:200px" maxlength="60" value="'.$name.'"></td></tr>'
            .'<tr><td><b>'.$lblDesc.'</b></td>'
            .'<td><input name="desc" type="text" style="width:400px" maxlength="200" value="'.$desc.'"></td></tr>'
            .'<tr><td><b>'.$lblOver.'</b></td>'
            .'<td><select name="overforums">'.$overOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblMod.'</b></td>'
            .'<td><input name="moderator" type="text" style="width:200px" maxlength="200" value="'.$moderatorVal.'">&nbsp;'
            .$lblModNote.'</td></tr>'
            .'<tr><td><b>'.$lblRead.'</b></td>'
            .'<td><select name="readclass">'.$readOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblWrite.'</b></td>'
            .'<td><select name="writeclass">'.$writeOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblCreate.'</b></td>'
            .'<td><select name="createclass">'.$createOpts.'</select></td></tr>'
            .'<tr><td><b>'.$lblOrder.'</b></td>'
            .'<td><select name="sort">'.$sortOpts.'</select> '.$lblOrderNote.'</td></tr>';
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function forgetCache(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('forums_list');
            $cache->delete_value('forum_moderator_array');

            return;
        }
        NexusDB::cache_del('forums_list');
        NexusDB::cache_del('forum_moderator_array');
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
        $path = base_path(get_langfile_path('forummanage.php'));
        $lang_forummanage = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_forummanage) ? $lang_forummanage : [];
    }
}
