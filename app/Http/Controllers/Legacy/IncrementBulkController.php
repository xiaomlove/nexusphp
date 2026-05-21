<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SendIncrementBulkRequest;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/increment-bulk.php` (deleted in the same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md`.
 *
 * This is the form-render half of the SYSOP+ "batch add bonus /
 * attendance card / invites / uploaded / temporary invites" flow.
 * The write-handler half — `public/take-increment-bulk.php` — is
 * migrated to {@see TakeIncrementBulkController} in the same PR;
 * that controller redirects back to `/increment-bulk.php?sent=1&type=<type>`
 * on success, and the route registered for this controller serves
 * that URL.
 *
 * Original legacy flow (`public/increment-bulk.php`, 79 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `get_langfile_path()`
 *      bootstrap.
 *   2. `get_user_class() < UC_SYSOP` → `stderr("Sorry", "Access denied.")`.
 *   3. `stdhead($lang_incrementbulk['page_title'], false);` + a
 *      `<form action="take-increment-bulk.php">` block containing:
 *        - a hidden `returnto` (from `?returnto=` or `Referer`),
 *        - an optional success banner ("$type has been added and
 *          inform message has been sent") when `?sent=1&type=...`,
 *        - a type-radio (seedbonus / attendance_card / invites /
 *          uploaded / tmp_invites; the "uploaded" label has a
 *          " (GB)" suffix so admins know the unit),
 *        - amount + duration inputs,
 *        - a 4-column class-checkbox grid driven by `User::$classes`,
 *        - `do_action('form_role_filter', 'Roles')` (plugin hook
 *          that may echo extra rows),
 *        - subject + message fields,
 *        - sender radio (`self` (default) / `system`),
 *        - submit button + hidden `receiver` field.
 *   4. `stdfoot();`
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`
 *     (legacy was HTTP 200 + `stderr()` body, same hardening as
 *     `DonatedController` / `MailtestController`).
 *   - GET → 200 chrome-less HTML envelope containing the form.
 *     The `<form action="take-increment-bulk.php">` URL is preserved
 *     exactly so the existing `TakeIncrementBulkController` write-
 *     handler keeps receiving POSTs without a URL change.
 *   - `?sent=1&type=<type>` → adds the legacy success banner above
 *     the form fields. Unknown `type` → no banner (legacy printed
 *     `' has been added and inform message has been sent'` — the
 *     leading space looked like a bug; we drop the banner instead).
 *   - `do_action('form_role_filter', 'Roles')` is preserved
 *     verbatim. Plugin callbacks `echo` directly, so the controller
 *     uses an `ob_start()` / `ob_get_clean()` sandwich to capture
 *     their output and splice it into the form at the same position
 *     the legacy script did (between the class selector and the
 *     subject row).
 *
 * UI strings: hardcoded English, same trade-off as
 * `StaffMessController` / `DonatedController`. The
 * `lang/<locale>/lang_increment-bulk.php` files stay in tree —
 * after this migration they're unused, but keeping them lets a
 * Phase 5 sweep restore localisation cleanly without re-extracting
 * the strings from git history.
 */
class IncrementBulkController extends Controller
{
    /**
     * Type → display label, with the legacy " (GB)" suffix on
     * `uploaded` so SYSOP admins know the unit. Matches the keys in
     * {@see SendIncrementBulkRequest::VALID_TYPES} so the form's
     * radio set and the FormRequest's `in:` rule can never drift.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        SendIncrementBulkRequest::TYPE_SEEDBONUS => 'bonus',
        SendIncrementBulkRequest::TYPE_ATTENDANCE_CARD => 'attendance card',
        SendIncrementBulkRequest::TYPE_INVITES => 'invite',
        SendIncrementBulkRequest::TYPE_UPLOADED => 'upload',
        SendIncrementBulkRequest::TYPE_TMP_INVITES => 'temporary invite',
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

        $returnto = (string) $request->query('returnto', '');
        if ($returnto === '') {
            $returnto = (string) ($request->headers->get('referer') ?? '');
        }

        $sent = (string) $request->query('sent', '') === '1';
        $sentType = (string) $request->query('type', '');
        $sentBanner = '';
        if ($sent && isset(self::TYPE_LABELS[$sentType])) {
            $sentBanner = '<tr><td colspan="2" class="text" align="center"><font color="red"><b> '
                .htmlspecialchars(self::TYPE_LABELS[$sentType])
                .' has been added and inform message has been sent</b></font></td></tr>'."\n";
        }

        ob_start();
        do_action('form_role_filter', 'Roles');
        $roleFilterHtml = (string) ob_get_clean();

        $username = (string) ($user->username ?? '');

        $body = $this->renderForm(
            classes: User::$classes,
            roleFilterHtml: $roleFilterHtml,
            returnto: $returnto,
            sentBanner: $sentBanner,
            username: $username,
        );

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Batch add bonus/attendance card/invite/uploaded/temporary invite</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * @param  array<string|int, array{text:string}>  $classes
     */
    private function renderForm(
        array $classes,
        string $roleFilterHtml,
        string $returnto,
        string $sentBanner,
        string $username,
    ): string {
        $returntoBlock = '';
        if ($returnto !== '') {
            $returntoBlock = '<input type="hidden" name="returnto" value="'
                .htmlspecialchars($returnto).'">'."\n";
        }

        $typeRadios = '';
        foreach (self::TYPE_LABELS as $name => $text) {
            $desc = $name === SendIncrementBulkRequest::TYPE_UPLOADED ? '&nbsp;(GB)' : '';
            $typeRadios .= sprintf(
                '<label><input type="radio" name="type" value="%s">%s%s</label> ',
                htmlspecialchars($name),
                htmlspecialchars($text),
                $desc,
            );
        }

        $classRows = '';
        foreach (array_chunk($classes, 4, true) as $chunk) {
            $classRows .= '<tr>';
            foreach ($chunk as $class => $info) {
                $classRows .= sprintf(
                    '<td style="border: 0"><label><input type="checkbox" name="classes[]" value="%s" />%s</label></td>',
                    htmlspecialchars((string) $class),
                    htmlspecialchars((string) ($info['text'] ?? '')),
                );
            }
            $classRows .= '</tr>';
        }

        $usernameEsc = htmlspecialchars($username);

        return '<table class="main" width="737" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">'."\n"
            .'<div align="center">'."\n"
            .'<h1>Batch add bonus/attendance card/invite/uploaded/temporary invite</h1>'."\n"
            .'<form method="post" action="take-increment-bulk.php">'."\n"
            .$returntoBlock
            .'<table cellspacing="0" cellpadding="5">'."\n"
            .$sentBanner
            .'<tr><td class="rowhead" valign="top">Type</td>'
            .'<td class="rowfollow">'.$typeRadios.'</td></tr>'."\n"
            .'<tr><td class="rowhead" valign="top">Amount</td>'
            .'<td class="rowfollow"><input type="text" name="amount" size="10"></td></tr>'."\n"
            .'<tr><td class="rowhead" valign="top">Duration</td>'
            .'<td class="rowfollow"><input type="number" min="1" name="duration" size="10"> '
            .'Required only if type is [Temporary Invitation], in days</td></tr>'."\n"
            .'<tr><td class="rowhead" valign="top">User class</td>'
            .'<td class="rowfollow">'."\n"
            .'<table style="border: 0" width="100%" cellpadding="0" cellspacing="0">'."\n"
            .$classRows."\n"
            .'</table>'."\n"
            .'</td></tr>'."\n"
            .$roleFilterHtml
            .'<tr><td class="rowhead" valign="top">Message subject</td>'
            .'<td class="rowfollow"><input type="text" name="subject" size="82"></td></tr>'."\n"
            .'<tr><td class="rowhead" valign="top">Message body</td>'
            .'<td class="rowfollow"><textarea name="msg" cols="80" rows="5"></textarea></td></tr>'."\n"
            .'<tr><td class="rowfollow" colspan="2"><div align="center"><b>Operator:&nbsp;&nbsp;</b>'."\n"
            .'<label><input name="sender" type="radio" value="self" checked>'.$usernameEsc.'</label>'."\n"
            .'&nbsp; <label><input name="sender" type="radio" value="system">System</label>'."\n"
            .'</div></td></tr>'."\n"
            .'<tr><td class="rowfollow" colspan="2" align="center">'
            .'<input type="submit" value="Submit" class="btn"></td></tr>'."\n"
            .'</table>'."\n"
            .'<input type="hidden" name="receiver" value="">'."\n"
            .'</form>'."\n"
            .'</div></td></tr></table>'."\n";
    }
}
