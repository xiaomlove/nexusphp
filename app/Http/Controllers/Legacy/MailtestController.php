<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Email;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/mailtest.php` (deleted in the same PR).
 *
 * Phase 2 batch #9 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` + `get_langfile_path()`.
 *   2. `get_user_class() < UC_SYSOP` → `permissiondenied()`.
 *   3. `$_POST['action'] == 'sendmail'`:
 *      - Validate `$_POST['email']` via `safe_email()` + `check_email()`,
 *        bail to `stderr('Error', 'Invalid email address!')` on failure.
 *      - Build a hard-coded subject (`"<SITENAME> SMTP Testing Mail"`)
 *        + body (`"Hi, If you see this message..."`).
 *      - `(new ToolRepository())->sendMail($email, $title, $body, true)`.
 *      - On success → `stderr('Success', 'No error found...')`.
 *      - On `Throwable` → `do_log(...)` + `stderr('Error',
 *        'Unable to send mail. <br/><br/><code>{message}</code>')`.
 *   4. Otherwise (GET) → render a tiny form (email input + submit).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *     The legacy `permissiondenied()` rendered HTTP 200 with the
 *     `std_access_denied` body; tightened in line with the rest of
 *     Phase 2.
 *   - GET (or POST without `action=sendmail`) → 200 chrome-less HTML
 *     with the form (email + submit).
 *   - POST `action=sendmail` with an invalid email → 200 HTML
 *     re-rendering the form with an inline error notice.
 *   - POST `action=sendmail` with a valid email → calls
 *     `ToolRepository::sendMail($email, $title, $body, true)` and
 *     renders the success page on `true` / the error page on a
 *     thrown `Throwable`.
 *
 * The chrome-less envelope follows the precedent set by every other
 * Phase 2 controller in this directory (`MoreSmiliesController`,
 * `DonatedController`, `FreeleechController`, ...) — the legacy
 * `stdhead()` / `stdfoot()` chrome is not reproduced; the page is an
 * internal admin tool and the original page used the chrome only as
 * a viewport.
 *
 * `ToolRepository` is constructor-injected (rather than instantiated
 * with `new` like the legacy script did) so the test suite can bind
 * a stub via the Laravel container without going through SMTP.
 */
class MailtestController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly ToolRepository $tools,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $email = trim((string) $request->input('email', ''));
        $action = (string) $request->input('action', '');

        if ($request->isMethod('POST') && $action === 'sendmail') {
            return $this->sendTestMail($email);
        }

        return new Response($this->wrap('Mail Test', $this->renderForm($email, null)));
    }

    private function sendTestMail(string $email): Response
    {
        // `App\Support\Email::sanitizeForDisplay` and `isWellFormed` are
        // the Phase-5 drains of the legacy `safe_email()` /
        // `check_email()` regex check. The test mail flow deliberately
        // skips the `bannedemails` DB lookup that the legacy
        // `check_email()` does — admin-only tooling accepts any
        // well-formed address.
        $email = Email::sanitizeForDisplay($email);
        if (! Email::isWellFormed($email)) {
            return new Response($this->wrap(
                'Mail Test',
                $this->renderForm($email, 'Invalid email address!'),
            ));
        }

        $siteName = (string) Setting::get('basic.SITENAME');
        $subject = $siteName.' SMTP Testing Mail';
        $body = 'Hi, If you see this message, your SMTP function works great. Have a nice day.';

        try {
            $this->tools->sendMail($email, $subject, $body, true);

            return new Response($this->wrap(
                'Mail Test',
                '<p>No error found. However this does not mean the mail arrived 100%. Please check the mail.</p>',
            ));
        } catch (\Throwable $e) {
            return new Response($this->wrap(
                'Mail Test',
                '<p>Unable to send mail.</p>'.
                "\n".'<code>'.htmlspecialchars($e->getMessage()).'</code>',
            ));
        }
    }

    private function renderForm(string $email, ?string $error): string
    {
        $emailEsc = htmlspecialchars($email);
        $statusBlock = '';
        if ($error !== null) {
            $statusBlock = '<p align="center"><font class="striking">'
                .htmlspecialchars($error).'</font></p>'."\n";
        }

        return '<h1 align="center">Mail Test</h1>'."\n"
            .$statusBlock
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<form method="post" action="mailtest.php">'."\n"
            .'<input type="hidden" name="action" value="sendmail">'."\n"
            .'<tr><td class="rowhead">Enter email</td><td>'
            .'<input type="text" name="email" size="35" value="'.$emailEsc.'"><br />'
            .'Enter an email address to send a test mail, e.g. yourname@gmail.com</td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" name="sendmail" value="Send it!"></td></tr>'."\n"
            .'</form>'."\n"
            .'</table>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title);

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
