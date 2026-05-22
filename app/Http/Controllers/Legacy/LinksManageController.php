<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/linksmanage.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`.
 *
 * Two distinct flows live behind one URL:
 *
 * 1. **`applylink` permission** — `?action=apply` GET renders an
 *    "apply for a reciprocal-link exchange" form; the matching
 *    `?action=newapply` POST inserts a row into `staffmessages` so
 *    a staff member can review the application later.
 * 2. **`linkmanage` permission** — default GET renders the admin's
 *    add-form + listing of every `links` row; `?action=add` POST
 *    inserts, `?action=editlink` POST updates, `?action=del&id=N`
 *    GET deletes, `?action=edit&id=N` GET pre-fills the edit form.
 *
 * The legacy script combined both flows into one file gated by
 * `if (user_can('applylink')) { ... } elseif (! user_can('linkmanage'))
 * { permissiondenied(); } else { ... }`. The migrated controller
 * keeps the same dispatch shape but tightens error responses
 * (legacy `permissiondenied()` rendered HTTP 200; the new code
 * `abort(403)`s).
 *
 * URL preserved exactly so `public/index.php:824,828` (the home-page
 * footer's "Apply for link" / "Manage links" anchors) keeps working
 * without template changes. POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except` (the legacy form
 * has no `@csrf` field).
 */
class LinksManageController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $lang = $this->loadLangLinksManage();
        $action = (string) $request->input('action', '');

        // --- "Apply for a reciprocal-link exchange" branch -----
        if ($action === 'apply') {
            if (! user_can('applylink')) {
                abort(403, 'Permission denied.');
            }

            return $this->renderApplyForm($lang);
        }
        if ($action === 'newapply') {
            if (! user_can('applylink')) {
                abort(403, 'Permission denied.');
            }

            return $this->handleNewApply($request, $lang, (int) $user->id);
        }

        // --- Admin (linkmanage) branch -------------------------
        if (! user_can('linkmanage')) {
            abort(403, 'Permission denied.');
        }

        if ($action === 'del' && $request->isMethod('GET')) {
            $id = (int) $request->query('id', 0);
            if ($id > 0) {
                NexusDB::table('links')->where('id', $id)->delete();
                $this->forgetLinksCache();
            }

            return redirect('/linksmanage.php');
        }
        if ($action === 'editlink' && $request->isMethod('POST')) {
            return $this->handleEditLink($request);
        }
        if ($action === 'add' && $request->isMethod('POST')) {
            return $this->handleAdd($request, $lang);
        }

        // --- Default admin view: add-form + listing ------------
        return $this->renderAdminPanel($request, $lang);
    }

    private function renderApplyForm(array $lang): Response
    {
        $siteName = Setting::getSiteName();
        $baseUrl = getSchemeAndHttpHost();
        $slogan = (string) (get_setting('basic.SLOGAN') ?? '');

        $rule1 = sprintf((string) ($lang['text_rule_one'] ?? ''), $baseUrl, $slogan, $siteName);
        $rule2 = sprintf((string) ($lang['text_rule_two'] ?? ''), $siteName);
        $rule5 = sprintf((string) ($lang['text_rule_five'] ?? ''), $siteName);
        $rule6 = sprintf((string) ($lang['text_rule_six'] ?? ''), $siteName);

        $heading = htmlspecialchars((string) ($lang['head_apply_for_links'] ?? 'Apply for links'));
        $rulesText = htmlspecialchars((string) ($lang['text_rules'] ?? 'Rules'));
        $redStarRequired = htmlspecialchars((string) ($lang['text_red_star_required'] ?? '* required'));
        $siteNameLabel = htmlspecialchars((string) ($lang['text_site_name'] ?? 'Site name'));
        $sitenameNote = htmlspecialchars((string) ($lang['text_sitename_note'] ?? ''));
        $urlLabel = htmlspecialchars((string) ($lang['text_url'] ?? 'URL'));
        $urlNote = htmlspecialchars((string) ($lang['text_url_note'] ?? ''));
        $titleLabel = htmlspecialchars((string) ($lang['text_title'] ?? 'Title'));
        $titleNote = htmlspecialchars((string) ($lang['text_title_note'] ?? ''));
        $adminLabel = htmlspecialchars((string) ($lang['text_administrator'] ?? 'Administrator'));
        $adminNote = htmlspecialchars((string) ($lang['text_administrator_note'] ?? ''));
        $emailLabel = htmlspecialchars((string) ($lang['text_email'] ?? 'Email'));
        $emailNote = htmlspecialchars((string) ($lang['text_email_note'] ?? ''));
        $reasonLabel = htmlspecialchars((string) ($lang['text_reason'] ?? 'Reason'));
        $submitOk = htmlspecialchars((string) ($lang['submit_okay'] ?? 'OK'));
        $submitReset = htmlspecialchars((string) ($lang['submit_reset'] ?? 'Reset'));

        $body = <<<HTML
<h1 align="center">{$heading}</h1>
<p><b><font size="5">{$rulesText}</font></b></p>
<p>&nbsp;&nbsp;{$rule1}</p>
<p>&nbsp;&nbsp;{$rule2}</p>
<p>&nbsp;&nbsp;{$lang['text_rule_three']}</p>
<p>&nbsp;&nbsp;{$lang['text_rule_four']}</p>
<p>&nbsp;&nbsp;{$rule5}</p>
<p>&nbsp;&nbsp;{$rule6}</p>
<p>{$redStarRequired}</p>
<form method="post" action="/linksmanage.php">
<table class="main" border="1" cellspacing="0" cellpadding="5">
<tr><td class="rowhead">{$siteNameLabel}<font color="red">*</font></td><td class="rowfollow"><input type="text" name="linkname" style="width: 200px">&nbsp;<font class="small">{$sitenameNote}</font></td></tr>
<tr><td class="rowhead">{$urlLabel}<font color="red">*</font></td><td class="rowfollow"><input type="text" name="url" style="width: 200px">&nbsp;<font class="small">{$urlNote}</font></td></tr>
<tr><td class="rowhead">{$titleLabel}</td><td class="rowfollow"><input type="text" name="title" style="width: 200px">&nbsp;<font class="small">{$titleNote}</font></td></tr>
<tr><td class="rowhead">{$adminLabel}<font color="red">*</font></td><td class="rowfollow"><input type="text" name="admin" style="width: 200px">&nbsp;<font class="small">{$adminNote}</font></td></tr>
<tr><td class="rowhead">{$emailLabel}<font color="red">*</font></td><td class="rowfollow"><input type="text" name="email" style="width: 200px">&nbsp;<font class="small">{$emailNote}</font></td></tr>
<tr><td class="rowhead">{$reasonLabel}<font color="red">*</font></td><td class="rowfollow"><textarea name="reason" style="width: 400px" rows="10"></textarea></td></tr>
<tr><td colspan="2" align="center"><input type="hidden" name="action" value="newapply"><input type="submit" value="{$submitOk}" class="btn"><input type="reset" class="btn" value="{$submitReset}"></td></tr>
</table>
</form>
HTML;

        return $this->wrap($heading, $body);
    }

    private function handleNewApply(Request $request, array $lang, int $userId): Response
    {
        $sitename = trim((string) $request->input('linkname', ''));
        $url = trim((string) $request->input('url', ''));
        $title = trim((string) $request->input('title', ''));
        $admin = trim((string) $request->input('admin', ''));
        $email = trim((string) $request->input('email', ''));
        $reason = trim((string) $request->input('reason', ''));

        if ($sitename === '') {
            abort(422, (string) ($lang['std_no_sitename'] ?? 'Sitename required.'));
        }
        if ($url === '') {
            abort(422, (string) ($lang['std_no_url'] ?? 'URL required.'));
        }
        if ($admin === '') {
            abort(422, (string) ($lang['std_no_admin'] ?? 'Administrator required.'));
        }
        if ($email === '') {
            abort(422, (string) ($lang['std_no_email'] ?? 'Email required.'));
        }
        $email = (string) safe_email($email);
        if (! check_email($email)) {
            abort(422, (string) ($lang['std_invalid_email'] ?? 'Invalid email.'));
        }
        if ($reason === '') {
            abort(422, (string) ($lang['std_no_reason'] ?? 'Reason required.'));
        }
        if (strlen($reason) < 20) {
            abort(422, (string) ($lang['std_reason_too_short'] ?? 'Reason too short.'));
        }

        $message = sprintf(
            "[b]Sitename[/b]: %s\n[b]URL[/b]: %s\n[b]Title[/b]: %s\n[b]Administrator: [/b]%s\n[b]EMail[/b]: %s\n[b]Reason[/b]: \n%s\n",
            $sitename, $url, $title, $admin, $email, $reason,
        );
        $subject = $sitename.' applys for links';

        NexusDB::insert('staffmessages', [
            'sender' => $userId,
            'added' => date('Y-m-d H:i:s'),
            'msg' => $message,
            'subject' => $subject,
        ]);

        $heading = htmlspecialchars((string) ($lang['std_success'] ?? 'Success'));
        $note = (string) ($lang['std_success_note'] ?? 'Your application has been submitted.');

        return $this->wrap($heading, '<h1 align="center">'.$heading.'</h1><p align="center">'.$note.'</p>');
    }

    private function handleEditLink(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $name = (string) $request->input('linkname', '');
        $url = (string) $request->input('url', '');
        $title = (string) $request->input('title', '');

        if ($id > 0 && ($name !== '' || $url !== '' || $title !== '')) {
            NexusDB::table('links')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'url' => $url,
                    'title' => $title,
                ]);
            $this->forgetLinksCache();
        }

        return redirect('/linksmanage.php');
    }

    private function handleAdd(Request $request, array $lang): Response|RedirectResponse
    {
        $name = (string) $request->input('linkname', '');
        $url = (string) $request->input('url', '');
        $title = (string) $request->input('title', '');

        if ($name === '' || $url === '' || $title === '') {
            abort(422, (string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
        }

        $newId = (int) NexusDB::insert('links', [
            'name' => $name,
            'url' => $url,
            'title' => $title,
        ]);
        $this->forgetLinksCache();

        if ($newId === 0) {
            abort(500, (string) ($lang['std_unable_creating_new_link'] ?? 'Unable to create new link.'));
        }

        return redirect('/linksmanage.php');
    }

    private function renderAdminPanel(Request $request, array $lang): Response
    {
        $heading = htmlspecialchars((string) ($lang['std_links_manage'] ?? 'Links management'));
        $addHeading = htmlspecialchars((string) ($lang['text_add_link'] ?? 'Add link'));
        $manageHeading = htmlspecialchars((string) ($lang['text_manage_links'] ?? 'Manage links'));
        $siteNameLabel = htmlspecialchars((string) ($lang['text_site_name'] ?? 'Site name'));
        $urlLabel = htmlspecialchars((string) ($lang['text_url'] ?? 'URL'));
        $titleLabel = htmlspecialchars((string) ($lang['text_title'] ?? 'Title'));
        $modifyLabel = htmlspecialchars((string) ($lang['text_modify'] ?? 'Modify'));
        $editLabel = htmlspecialchars((string) ($lang['text_edit'] ?? 'Edit'));
        $deleteLabel = htmlspecialchars((string) ($lang['text_delete'] ?? 'Delete'));
        $noLinks = htmlspecialchars((string) ($lang['text_no_links_found'] ?? 'No links found.'));
        $submitOk = htmlspecialchars((string) ($lang['submit_okay'] ?? 'OK'));
        $jsConfirm = (string) ($lang['js_sure_to_delete_link'] ?? 'Are you sure?');
        // Single-quote-escape for the JS prompt.
        $jsConfirmEsc = addslashes($jsConfirm);

        $body = <<<HTML
<h1>{$addHeading}</h1>
<form method="post" action="/linksmanage.php">
<table border="1" cellspacing="0" cellpadding="5">
<tr><td class="rowhead">{$siteNameLabel}</td><td><input type="text" name="linkname" style="width: 200px"></td></tr>
<tr><td class="rowhead">{$urlLabel}</td><td><input type="text" name="url" style="width: 200px"></td></tr>
<tr><td class="rowhead">{$titleLabel}</td><td><input type="text" name="title" style="width: 200px"></td></tr>
<tr><td colspan="2" align="center"><input type="hidden" name="action" value="add"><input type="submit" value="{$submitOk}" class="btn"></td></tr>
</table>
</form>
<h1>{$manageHeading}</h1>
<table width="80%" border="0" align="center" cellpadding="2" cellspacing="0">
<tr><td class="colhead" align="left">{$siteNameLabel}</td><td class="colhead">{$urlLabel}</td><td class="colhead">{$titleLabel}</td><td class="colhead" align="center">{$modifyLabel}</td></tr>
HTML;

        $rows = NexusDB::table('links')->orderBy('id')->get();
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $row = (array) $row;
                $name = htmlspecialchars((string) ($row['name'] ?? ''));
                $rowUrl = htmlspecialchars((string) ($row['url'] ?? ''));
                $rowTitle = htmlspecialchars((string) ($row['title'] ?? ''));
                $id = (int) $row['id'];
                $body .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td>'
                    .'<td align="center" nowrap><b>'
                    .'<a href="?action=edit&id=%d">%s</a>&nbsp;|&nbsp;'
                    .'<a href="javascript:if(confirm(\'%s\')){location.href=\'?action=del&id=%d\';}">'
                    .'<font color="red">%s</font></a></b></td></tr>',
                    $name,
                    $rowUrl,
                    $rowTitle,
                    $id,
                    $editLabel,
                    $jsConfirmEsc,
                    $id,
                    $deleteLabel,
                );
            }
        } else {
            $body .= '<tr><td colspan="4">'.$noLinks.'</td></tr>';
        }
        $body .= '</table>';

        // Pre-fill an edit form when ?action=edit&id=N
        if ($request->query('action') === 'edit') {
            $editId = (int) $request->query('id', 0);
            if ($editId > 0) {
                $editRow = NexusDB::table('links')->where('id', $editId)->first();
                if ($editRow !== null) {
                    $editArr = (array) $editRow;
                    $body .= $this->renderEditForm($editArr, $lang);
                }
            }
        }

        return $this->wrap($heading, $body);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function renderEditForm(array $row, array $lang): string
    {
        $editHeading = htmlspecialchars((string) ($lang['text_edit_link'] ?? 'Edit link'));
        $siteNameLabel = htmlspecialchars((string) ($lang['text_site_name'] ?? 'Site name'));
        $urlLabel = htmlspecialchars((string) ($lang['text_url'] ?? 'URL'));
        $titleLabel = htmlspecialchars((string) ($lang['text_title'] ?? 'Title'));
        $submitOk = htmlspecialchars((string) ($lang['submit_okay'] ?? 'OK'));

        $name = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES);
        $url = htmlspecialchars((string) ($row['url'] ?? ''), ENT_QUOTES);
        $title = htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES);
        $id = (int) ($row['id'] ?? 0);

        return <<<HTML
<h1>{$editHeading}</h1>
<form method="post" action="/linksmanage.php">
<table border="1" cellspacing="0" cellpadding="5">
<tr><td class="rowhead">{$siteNameLabel}</td><td><input type="text" name="linkname" size="40" value="{$name}"></td></tr>
<tr><td class="rowhead">{$urlLabel}</td><td><input type="text" name="url" size="40" value="{$url}"></td></tr>
<tr><td class="rowhead">{$titleLabel}</td><td><input type="text" name="title" size="40" value="{$title}"></td></tr>
<tr><td colspan="2" align="center"><input type="hidden" name="id" value="{$id}"><input type="hidden" name="action" value="editlink"><input type="submit" value="{$submitOk}" class="btn"></td></tr>
</table>
</form>
HTML;
    }

    private function forgetLinksCache(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('links');

            return;
        }
        NexusDB::cache_del('links');
    }

    /** @return array<string,string> */
    private function loadLangLinksManage(): array
    {
        $path = base_path(get_langfile_path('linksmanage.php'));
        $lang_linksmanage = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_linksmanage) ? $lang_linksmanage : [];
    }

    private function wrap(string $title, string $body): Response
    {
        $titleHtml = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleHtml}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }
}
