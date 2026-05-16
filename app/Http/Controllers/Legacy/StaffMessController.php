<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/staffmess.php` (deleted in the same PR).
 *
 * Phase 2 batch of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * This is the form-render half of the staff mass-PM flow. The
 * write-handler half — `public/takestaffmess.php` — was already
 * migrated to {@see TakeStaffMessController} in a previous PR; that
 * controller still redirects to `/staffmess.php?sent=1` on success,
 * and the route registered for this controller serves that URL.
 *
 * Original legacy flow (`public/staffmess.php`, 73 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_ADMINISTRATOR` → `stderr("Sorry", "Access denied.")`.
 *   3. `stdhead("Mass PM", false);` + a `<form action=takestaffmess.php>`
 *      block containing:
 *        - a hidden `returnto` (from `?returnto=` or `Referer`, if
 *          either is set), HTML-escaped;
 *        - an optional success banner ("The message has been sent.")
 *          when the URL has `?sent=1`;
 *        - a 4-column class checkbox grid driven by
 *          `User::$classes`;
 *        - `do_action('form_role_filter', 'Send to Role:')` (plugin
 *          hook that may echo extra form rows);
 *        - subject input + message textarea;
 *        - sender radio (`self` (default) / `system`);
 *        - submit button.
 *   4. `stdfoot();`
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` →
 *     `abort(403)` (legacy was HTTP 200 + `stderr()` body, same
 *     hardening as `AddUserController` / `DonatedController`).
 *   - GET → 200 chrome-less HTML envelope containing the form
 *     (matching the legacy markup so the existing form-side CSS keeps
 *     working without a template change). The `<form action=...>`
 *     still points at `/takestaffmess.php` so the existing
 *     `TakeStaffMessController` write-handler keeps receiving POSTs
 *     without a URL change.
 *   - `?sent=1` → adds the legacy "The message has been sent."
 *     confirmation row above the form fields. (Fix-in-passing: the
 *     legacy text was "The message has ben sent." — typo. The
 *     migrated copy spells "been" correctly. No JS / external page
 *     greps for that string.)
 *   - `?returnto=<url>` → renders `<input type=hidden name=returnto
 *     value=...>` so a downstream cancel/back button can route the
 *     user back to where they came from. Falls back to the `Referer`
 *     header when `?returnto` is absent, matching the legacy fallback.
 *   - `do_action('form_role_filter', 'Send to Role:')` is preserved
 *     verbatim. Plugin callbacks `echo` directly, so the controller
 *     uses an `ob_start()` / `ob_get_clean()` sandwich to capture
 *     their output and splice it into the form body at the same
 *     position the legacy script did (between the class selector
 *     and the subject row).
 *
 * Behaviour preserved from legacy:
 *   - The `<input type=hidden name=receiver value="">` field is
 *     kept (legacy `<?php echo $receiver ?>` always emitted an
 *     empty value because `$receiver` is never assigned in the
 *     legacy file); preserved so any plugin reading the form via
 *     `dom-querySelector('input[name=receiver]')` still finds it.
 *   - The form's `action=takestaffmess.php` URL is preserved
 *     exactly so the existing route → `TakeStaffMessController`
 *     wiring keeps working without a separate template change.
 */
class StaffMessController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $returnto = (string) $request->query('returnto', '');
        if ($returnto === '') {
            $returnto = (string) ($request->headers->get('referer') ?? '');
        }

        $sent = (string) $request->query('sent', '') === '1';

        // Render plugin hook output. Callbacks registered against
        // `form_role_filter` echo directly (see e.g. plugin examples
        // under `plugins/`); buffer their output so we can splice
        // it into the form at the legacy splice point.
        ob_start();
        do_action('form_role_filter', 'Send to Role:');
        $roleFilterHtml = (string) ob_get_clean();

        $username = (string) ($user->username ?? '');

        $body = $this->renderForm(
            classes: User::$classes,
            roleFilterHtml: $roleFilterHtml,
            returnto: $returnto,
            sent: $sent,
            username: $username,
        );

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Mass PM</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Render the form body. Markup mirrors the legacy
     * `public/staffmess.php` output (no `stdhead/stdfoot` chrome —
     * that's intentional, see {@see ContactStaffController} for the
     * Phase 2 precedent).
     *
     * @param  array<string|int,array{text:string}>  $classes
     */
    private function renderForm(
        array $classes,
        string $roleFilterHtml,
        string $returnto,
        bool $sent,
        string $username,
    ): string {
        $returntoBlock = '';
        if ($returnto !== '') {
            $returntoBlock = '<input type="hidden" name="returnto" value="'
                .htmlspecialchars($returnto).'">'."\n";
        }

        $sentBlock = '';
        if ($sent) {
            $sentBlock = '<tr><td colspan="2"><font color="red"><b>'
                .'The message has been sent.</b></font></td></tr>'."\n";
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
            .'<h1>Mass PM to all Staff members and users:</h1>'."\n"
            .'<form method="post" action="takestaffmess.php">'."\n"
            .$returntoBlock
            .'<table cellspacing="0" cellpadding="5">'."\n"
            .$sentBlock
            .'<tr>'."\n"
            .'<td><b>Send to class:</b></td>'."\n"
            .'<td>'."\n"
            .'<table style="border: 0" width="100%" cellpadding="0" cellspacing="0">'."\n"
            .$classRows."\n"
            .'</table>'."\n"
            .'</td>'."\n"
            .'</tr>'."\n"
            .$roleFilterHtml
            .'<tr>'."\n"
            .'<td class="rowhead">Subject</td>'."\n"
            .'<td><input type="text" name="subject" size="75"></td>'."\n"
            .'</tr>'."\n"
            .'<tr>'."\n"
            .'<td class="rowhead">Message</td>'."\n"
            .'<td><textarea name="msg" cols="80" rows="15"></textarea></td>'."\n"
            .'</tr>'."\n"
            .'<tr>'."\n"
            .'<td colspan="2"><div align="center"><b>Sender:&nbsp;&nbsp;</b>'."\n"
            .$usernameEsc."\n"
            .'<input name="sender" type="radio" value="self" checked>'."\n"
            .'&nbsp; System'."\n"
            .'<input name="sender" type="radio" value="system">'."\n"
            .'</div></td></tr>'."\n"
            .'<tr><td colspan="2" align="center"><input type="submit" value="Send!" class="btn"></td></tr>'."\n"
            .'</table>'."\n"
            .'<input type="hidden" name="receiver" value="">'."\n"
            .'</form>'."\n"
            .'</div></td></tr></table>'."\n"
            .'<br />'."\n"
            .'NOTE: Do not use BB codes. (NO HTML)'."\n";
    }
}
