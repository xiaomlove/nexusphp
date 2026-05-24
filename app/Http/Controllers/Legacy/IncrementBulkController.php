<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/increment-bulk.php` (deleted in the same PR).
 *
 * Phase 2 migration. The legacy script was a 79-LOC sysop-only form
 * page that renders the "Batch add bonus/attendance card/invite/
 * uploaded/temporary invite" UI. The form POSTs to
 * `/take-increment-bulk.php` (migrated to
 * {@see TakeIncrementBulkController} in the same PR).
 *
 * Original legacy flow:
 *   1. `require "../include/bittorrent.php"; dbconn();` bootstrap.
 *   2. `require_once(get_langfile_path());` — loads `lang_incrementbulk`.
 *   3. `loggedinorreturn();` — redirect to login if not authenticated.
 *   4. `get_user_class() < UC_SYSOP` → `stderr("Sorry", "Access denied.")`.
 *   5. `stdhead(...)` + form HTML + `stdfoot()`.
 *   6. Form contains: type radio buttons, amount input, duration input,
 *      class checkboxes, `do_action('form_role_filter', ...)` plugin hook,
 *      message subject + body textareas, sender radio (self/system),
 *      optional success banner when `?sent=1&type=...`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *   - GET → 200 chrome-less HTML envelope containing the form.
 *   - `?sent=1&type=<type>` → adds the success banner above form fields.
 *   - `do_action('form_role_filter', ...)` plugin hook preserved.
 *   - `<form action="take-increment-bulk.php">` preserved so the
 *     existing `TakeIncrementBulkController` receives POSTs.
 */
class IncrementBulkController extends Controller
{
    /**
     * Hardcoded valid type map — used as fallback when the legacy
     * langfile cannot be loaded. Mirrors `$lang_incrementbulk['types']`.
     */
    private const VALID_TYPES = [
        'seedbonus' => 'bonus',
        'attendance_card' => 'attendance card',
        'invites' => 'invite',
        'uploaded' => 'upload',
        'tmp_invites' => 'temporary invite',
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        // Load the legacy language file for labels and type map.
        $this->ensureLanguageLoaded();
        $lang = $GLOBALS['lang_incrementbulk'] ?? [];

        $validTypeMap = $lang['types'] ?? self::VALID_TYPES;
        $labels = $lang['labels'] ?? [];
        $pageTitle = $lang['page_title'] ?? 'Batch add bonus/attendance card/invite/uploaded/temporary invite';
        $sentSuccess = $lang['sent_success'] ?? ' has been added and inform message has been sent';

        $sent = (string) $request->query('sent', '') === '1';
        $type = (string) $request->query('type', '');

        $returnto = (string) $request->query('returnto', '');
        if ($returnto === '') {
            $returnto = (string) ($request->headers->get('referer') ?? '');
        }

        // Render plugin hook output (same pattern as StaffMessController).
        ob_start();
        do_action('form_role_filter', $labels['roles'] ?? 'Roles');
        $roleFilterHtml = (string) ob_get_clean();

        $username = (string) ($user->username ?? '');

        $body = $this->renderForm(
            validTypeMap: $validTypeMap,
            labels: $labels,
            pageTitle: $pageTitle,
            sentSuccess: $sentSuccess,
            sent: $sent,
            type: $type,
            returnto: $returnto,
            roleFilterHtml: $roleFilterHtml,
            username: $username,
        );

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$pageTitle}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * @param  array<string,string>  $validTypeMap
     * @param  array<string,string>  $labels
     */
    private function renderForm(
        array $validTypeMap,
        array $labels,
        string $pageTitle,
        string $sentSuccess,
        bool $sent,
        string $type,
        string $returnto,
        string $roleFilterHtml,
        string $username,
    ): string {
        $returntoBlock = '';
        if ($returnto !== '') {
            $returntoBlock = '<input type="hidden" name="returnto" value="'
                .htmlspecialchars($returnto).'">'."\n";
        }

        $sentBlock = '';
        if ($sent) {
            $typeLabel = $validTypeMap[$type] ?? '';
            $sentBlock = '<tr><td colspan="2" class="text" align="center"><font color="red"><b> '
                .htmlspecialchars($typeLabel.$sentSuccess)
                .'</font></b></td></tr>'."\n";
        }

        // Type radio buttons
        $typeRadios = '';
        foreach ($validTypeMap as $name => $text) {
            $desc = $name === 'uploaded' ? '&nbsp;(GB)' : '';
            $typeRadios .= sprintf(
                '<label><input type="radio" name="type" value="%s">%s%s</label>',
                htmlspecialchars($name),
                htmlspecialchars($text),
                $desc,
            );
        }

        // Class checkboxes (4-column grid)
        $classes = array_chunk(User::listClass(), 4, true);
        $classRows = '';
        foreach ($classes as $chunk) {
            $classRows .= '<tr>';
            foreach ($chunk as $class => $info) {
                $classRows .= sprintf(
                    '<td style="border: 0"><label><input type="checkbox" name="classes[]" value="%s" />%s</label></td>',
                    htmlspecialchars((string) $class),
                    htmlspecialchars((string) $info),
                );
            }
            $classRows .= '</tr>';
        }

        $labelType = htmlspecialchars($labels['type'] ?? 'Type');
        $labelAmount = htmlspecialchars($labels['amount'] ?? 'Amount');
        $labelDuration = htmlspecialchars($labels['duration'] ?? 'Duration');
        $labelDurationHelp = htmlspecialchars($labels['duration_help'] ?? 'Required only if type is [Temporary Invitation], in days');
        $labelUserClass = htmlspecialchars($labels['user_class'] ?? 'User class');
        $labelMsgSubject = htmlspecialchars($labels['msg_subject'] ?? 'message subject');
        $labelMsgBody = htmlspecialchars($labels['msg_body'] ?? 'message content');
        $labelOperator = htmlspecialchars($labels['operator'] ?? 'Operator');
        $usernameEsc = htmlspecialchars($username);
        $pageTitleEsc = htmlspecialchars($pageTitle);
        $submitLabel = function_exists('nexus_trans') ? nexus_trans('label.submit') : 'Submit';

        return <<<FORM
<table class="main" width="737" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<div align="center">
<h1>{$pageTitleEsc}</h1>
<form method="post" action="take-increment-bulk.php">
{$returntoBlock}<table cellspacing="0" cellpadding="5">
{$sentBlock}<tr>
<td class="rowhead" valign="top">{$labelType}</td>
<td class="rowfollow">{$typeRadios}</td>
</tr>
<tr><td class="rowhead" valign="top">{$labelAmount} </td><td class="rowfollow"><input type="text" name="amount" size="10"></td></tr>
<tr><td class="rowhead" valign="top">{$labelDuration}</td><td class="rowfollow"><input type="number" min="1" name="duration" size="10"> {$labelDurationHelp}</td></tr>
<tr>
<td class="rowhead" valign="top">{$labelUserClass}</td><td class="rowfollow">
<table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
{$classRows}
</table>
</td>
</tr>
{$roleFilterHtml}<tr><td class="rowhead" valign="top">{$labelMsgSubject} </td><td class="rowfollow"><input type="text" name="subject" size="82"></td></tr>
<tr><td class="rowhead" valign="top">{$labelMsgBody} </td><td class="rowfollow"><textarea name="msg" cols="80" rows="5"></textarea></td></tr>
<tr>
<td class="rowfollow" colspan="2"><div align="center"><b>{$labelOperator}:&nbsp;&nbsp;</b>
<label><input name="sender" type="radio" value="self" checked>{$usernameEsc}</label>
&nbsp; <label><input name="sender" type="radio" value="system">System</label>
</div></td></tr>
<tr><td class="rowfollow" colspan="2" align="center"><input type="submit" value="{$submitLabel}" class="btn"></td></tr>
</table>
<input type="hidden" name="receiver" value="">
</form>
</div></td></tr></table>
FORM;
    }

    /**
     * Load the per-locale `$lang_incrementbulk` global. The legacy
     * script did `require_once(get_langfile_path())` which loads
     * `lang/<locale>/lang_increment-bulk.php`.
     */
    private function ensureLanguageLoaded(): void
    {
        if (isset($GLOBALS['lang_incrementbulk'])) {
            return;
        }
        $relative = get_langfile_path('increment-bulk.php');
        $path = base_path($relative);
        if (is_file($path)) {
            require_once $path;
        }
    }
}
