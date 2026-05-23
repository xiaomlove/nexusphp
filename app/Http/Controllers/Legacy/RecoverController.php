<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/recover.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Part 2 of 3 in the auth-flow
 * batch (see `SignupController` PHPDoc for the full split).
 *
 * Multi-method recovery flow. The legacy script handled three
 * distinct scenarios in a single file via `$_SERVER["REQUEST_METHOD"]`
 * branching:
 *
 *   1. **Plain GET** (`/recover.php`) — render the "enter your
 *      registered email" form.
 *   2. **GET with token** (`/recover.php?id=<int>&secret=<md5>`) —
 *      consume the recovery link emailed in step (3): regenerate
 *      a 10-character random password, write the new sha256
 *      `passhash`, mint a fresh `auth_key`, blank the
 *      `editsecret`, and email the new password to the user.
 *   3. **POST email** (`/recover.php` with `email=...` body) —
 *      look up the user by email, mint a fresh `editsecret`, hash
 *      it with the email + passhash + secret to produce the
 *      one-shot `secret` token, store the token in the
 *      `recover:<hash>` cache key, and email the resulting
 *      `recover.php?id=...&secret=...` link.
 *
 * The URL stays `/recover.php` for all three branches so existing
 * recovery emails in the wild continue to work without a
 * redirect rule. The form action is also `recover.php`, which
 * makes the POST path self-targeting (same as the legacy script).
 *
 * Failed-logins gate
 * ------------------
 * Same inline `failedloginscheck($recover=true)` parity as
 * `LoginController` / `TakeLoginController` — abort 403 once the
 * IP has hit the `$maxloginattempts` threshold. The `$recover=true`
 * flag flips the `loginattempts.type` column to `recover` so the
 * sysop admin tool (`maxlogin.php`, migrated in PR-C) can tell
 * recovery brute-forces apart from login brute-forces.
 *
 * Bark / failed-attempts posture
 * ------------------------------
 * The legacy script used a mix of `bark()` and `failedlogins()`
 * for different error classes:
 *   - `bark()` for "page-level" issues (DB error, nothing to
 *     update). No counter increment.
 *   - `failedlogins()` for input validation (missing / invalid
 *     email, email not in DB, account unconfirmed). Counter
 *     increment, same as `TakeLoginController`.
 * We preserve the split — only `failedlogins`-style failures
 * increment `loginattempts` (with `type = 'recover'`).
 *
 * Skipped legacy features
 * -----------------------
 * The `<select name="sitelanguage">` switcher at the top of the
 * legacy form is NOT re-emitted. Same Phase 5 sweep item as
 * `LoginController`.
 *
 * CSRF: `/recover.php` is exempt — the legacy
 * `<form action="recover.php">` has no `@csrf` field. See
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class RecoverController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        // failedloginscheck($recover=true) parity. The legacy
        // script ran this BEFORE any branch, so we mirror that —
        // the IP-ban gate fires regardless of method / params.
        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('recover.php');

        try {
            if ($request->isMethod('POST')) {
                return $this->handlePostEmail($request, $lang);
            }

            // GET with id+secret = consume token branch.
            if ($request->query('id') !== null && $request->query('secret') !== null) {
                return $this->handleConsumeToken($request, $lang);
            }

            // Plain GET = render form.
            return new Response($this->wrap(
                (string) ($lang['head_recover'] ?? 'Recover account'),
                $this->renderFormBody($lang),
            ));
        } catch (BarkException $e) {
            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * POST email branch. Mints a one-shot recovery token, stores
     * it in the cache, and emails the recovery link to the user.
     *
     * @param  array<string,string>  $lang
     */
    private function handlePostEmail(Request $request, array $lang): Response
    {
        if (($GLOBALS['iv'] ?? '') === 'yes' && function_exists('check_code')) {
            check_code(
                $request->input('imagehash'),
                $request->input('imagestring'),
                'recover.php',
                true,
            );
        }

        $email = htmlspecialchars(trim((string) $request->input('email', '')));
        if (function_exists('safe_email')) {
            $email = (string) safe_email($email);
        }
        if ($email === '') {
            $this->failedlogin((string) ($lang['std_missing_email_address'] ?? 'Missing email address.'));
        }
        if (function_exists('check_email') && ! check_email($email)) {
            $this->failedlogin((string) ($lang['std_invalid_email_address'] ?? 'Invalid email address.'));
        }

        $arr = NexusDB::table('users')
            ->whereRaw('BINARY email = ?', [$email])
            ->limit(1)
            ->first();
        $arr = $arr !== null ? (array) $arr : null;
        if ($arr === null) {
            $this->failedlogin((string) ($lang['std_email_not_in_database'] ?? 'Email not found.'));
        }
        if (($arr['status'] ?? '') === 'pending') {
            $this->failedlogin((string) ($lang['std_user_account_unconfirmed'] ?? 'Account is not confirmed.'));
        }

        $sec = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));

        $updated = NexusDB::table('users')
            ->where('id', (int) $arr['id'])
            ->update(['editsecret' => $sec]);
        if (! $updated) {
            $this->bark((string) ($lang['std_database_error'] ?? 'Database error.'));
        }

        $hash = md5($sec.$email.((string) $arr['passhash']).$sec);
        if (function_exists('do_log')) {
            do_log("hash: {$hash} = md5(sec: {$sec} . email: {$email} . passhash: {$arr['passhash']} . sec: {$sec})");
        }

        // Stash the token in a cache key so step (2) can verify it
        // wasn't fabricated. Cache TTL is the same as the legacy
        // (default `cache_put` lifetime — `recover:<hash>` is
        // referenced by the consume-token branch with `cache_get`).
        NexusDB::cache_put('recover:'.$hash, now()->toDateTimeString());

        $this->sendRecoveryLinkEmail((int) $arr['id'], $email, $hash, $lang);

        // The legacy script renders no body after a successful
        // POST — `sent_mail()` sends and the script exits via
        // `}` (last brace of the if-block). We mirror that with an
        // empty 200, which keeps the URL stable and produces no
        // redirect side effect.
        return new Response('', 200);
    }

    /**
     * GET with id+secret. Consumes the recovery token, mints a
     * new password, and emails it to the user.
     *
     * @param  array<string,string>  $lang
     */
    private function handleConsumeToken(Request $request, array $lang): Response
    {
        $id = (int) $request->query('id', '0');
        $md5 = (string) $request->query('secret', '');
        if ($id <= 0) {
            abort(404);
        }
        if (! NexusDB::cache_get('recover:'.$md5)) {
            if (function_exists('do_log')) {
                do_log("secret: {$md5} is expired", 'error');
            }
            abort(404);
        }

        $arr = NexusDB::table('users')
            ->where('id', $id)
            ->select(['username', 'email', 'passhash', 'editsecret'])
            ->first();
        $arr = $arr !== null ? (array) $arr : null;
        if ($arr === null) {
            abort(404);
        }

        $email = (string) $arr['email'];
        $sec = function_exists('hash_pad') ? (string) hash_pad((string) $arr['editsecret']) : (string) $arr['editsecret'];
        if ($md5 !== md5($sec.$email.((string) $arr['passhash']).$sec)) {
            if (function_exists('do_log')) {
                do_log("secret: {$md5} != md5(...)", 'error');
            }
            abort(404);
        }

        // Generate a 10-character random password — same pool as
        // the legacy script. `mt_rand` is deliberately preserved
        // (the legacy used it; switching to `random_int` is a
        // separate hardening change).
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $newPassword = '';
        for ($i = 0; $i < 10; $i++) {
            $newPassword .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        $newSec = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));
        $newPasshash = hash('sha256', $newSec.hash('sha256', $newPassword));
        $authKey = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));

        $updated = NexusDB::table('users')
            ->where('id', $id)
            ->where('editsecret', (string) $arr['editsecret'])
            ->update([
                'secret' => $newSec,
                'editsecret' => '',
                'passhash' => $newPasshash,
                'auth_key' => $authKey,
            ]);
        if (! $updated) {
            $this->bark((string) ($lang['std_unable_updating_user_data'] ?? 'Unable to update user data.'));
        }

        $this->sendNewPasswordEmail($email, (string) $arr['username'], $newPassword, $lang);

        return new Response('', 200);
    }

    /**
     * Render the recovery form. Includes the legacy "you have N
     * remaining tries" counter so the user can tell whether their
     * IP is about to be banned.
     *
     * @param  array<string,string>  $lang
     */
    private function renderFormBody(array $lang): string
    {
        $maxAttempts = (int) ($GLOBALS['maxloginattempts'] ?? 0);
        $remaining = $this->remainingAttempts($maxAttempts);
        $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';

        $textHeader = htmlspecialchars((string) ($lang['text_recover_user'] ?? 'Recover account'), ENT_QUOTES);
        $textUseForm = htmlspecialchars((string) ($lang['text_use_form_below'] ?? 'Enter your registered email below.'), ENT_QUOTES);
        $textReply = htmlspecialchars((string) ($lang['text_reply_to_confirmation_email'] ?? ''), ENT_QUOTES);
        $textNote = htmlspecialchars((string) ($lang['text_note'] ?? 'Note: '), ENT_QUOTES);
        $textBanIp = htmlspecialchars((string) ($lang['text_ban_ip'] ?? ' failed attempts will result in an IP ban.'), ENT_QUOTES);
        $textYouHave = htmlspecialchars((string) ($lang['text_you_have'] ?? 'You have '), ENT_QUOTES);
        $textRemainingTries = htmlspecialchars((string) ($lang['text_remaining_tries'] ?? ' remaining attempts.'), ENT_QUOTES);
        $rowEmail = htmlspecialchars((string) ($lang['row_registered_email'] ?? 'Registered email'), ENT_QUOTES);
        $btnRecover = htmlspecialchars((string) ($lang['submit_recover_it'] ?? 'Recover'), ENT_QUOTES);

        ob_start();
        try {
            if (function_exists('show_image_code')) {
                show_image_code();
            }
        } finally {
            $imageCaptchaRow = (string) ob_get_clean();
        }

        return <<<HTML
<h1>{$textHeader}</h1>
<p>{$textUseForm}</p>
<p>{$textReply}</p>
<p><b>{$textNote}{$maxAttempts}</b>{$textBanIp}</p>
<p>{$textYouHave}<b>{$remaining}</b>{$textRemainingTries}</p>
<form method="post" action="recover.php">
<table border="1" cellspacing="0" cellpadding="10">
<tr><td class="rowhead">{$rowEmail}</td>
<td class="rowfollow"><input type="email" {$formInputStyle} name="email" autocomplete="email" /></td></tr>
{$imageCaptchaRow}
<tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="{$btnRecover}" class="btn" /></td></tr>
</table></form>
HTML;
    }

    /**
     * Send the "click to reset your password" email — body
     * preserved verbatim from the legacy script.
     *
     * @param  array<string,string>  $lang
     */
    private function sendRecoveryLinkEmail(int $userId, string $email, string $hash, array $lang): void
    {
        if (! function_exists('sent_mail')) {
            return;
        }
        $siteName = method_exists(Setting::class, 'getSiteName')
            ? (string) Setting::getSiteName()
            : (string) ($GLOBALS['SITENAME'] ?? '');
        $siteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $title = $siteName.((string) ($lang['mail_title'] ?? ' Recovery'));
        $baseUrl = function_exists('getSchemeAndHttpHost') ? (string) getSchemeAndHttpHost() : '';
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $mailOne = sprintf((string) ($lang['mail_one'] ?? ''), $siteName);
        $mailFour = sprintf((string) ($lang['mail_four'] ?? ''), $siteName);
        $mailTwo = (string) ($lang['mail_two'] ?? '');
        $mailThree = (string) ($lang['mail_three'] ?? '');
        $mailThisLink = (string) ($lang['mail_this_link'] ?? 'this link');
        $url = $baseUrl.'/recover.php?id='.$userId.'&secret='.$hash;

        $body = $mailOne.'('.$email.')'.$mailTwo.$ip.$mailThree
            .'<b><a href="'.$url.'" target="_blank"> '.$mailThisLink.' </a></b><br />'
            .$url
            .$mailFour;

        sent_mail($email, $siteName, $siteEmail, $title, $body, 'confirmation', true, false, '');
    }

    /**
     * Send the "your new password is X" email after a successful
     * token consume — body preserved verbatim.
     *
     * @param  array<string,string>  $lang
     */
    private function sendNewPasswordEmail(string $email, string $username, string $newPassword, array $lang): void
    {
        if (! function_exists('sent_mail')) {
            return;
        }
        $siteName = method_exists(Setting::class, 'getSiteName')
            ? (string) Setting::getSiteName()
            : (string) ($GLOBALS['SITENAME'] ?? '');
        $siteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $title = $siteName.((string) ($lang['mail_two_title'] ?? ' New password'));
        $baseUrl = function_exists('getSchemeAndHttpHost') ? (string) getSchemeAndHttpHost() : '';
        $mailTwoOne = (string) ($lang['mail_two_one'] ?? '');
        $mailTwoTwo = (string) ($lang['mail_two_two'] ?? '');
        $mailTwoThree = (string) ($lang['mail_two_three'] ?? '');
        $mailTwoFour = sprintf((string) ($lang['mail_two_four'] ?? ''), $siteName);
        $mailHere = (string) ($lang['mail_here'] ?? 'here');

        $body = $mailTwoOne.$username
            ."\n".$mailTwoTwo.$newPassword
            ."\n".$mailTwoThree
            .'<b><a href="'.$baseUrl.'/login.php">'.$mailHere.'</a></b>'
            ."\n".$mailTwoFour;

        sent_mail($email, $siteName, $siteEmail, $title, $body, 'details', true, false, '');
    }

    /**
     * Same inline `failedloginscheck($recover=true)` parity. The
     * `loginattempts.type` value is set to `recover` on the row
     * we just inserted/updated so `maxlogin.php` (PR-C) can tell
     * recovery brute-forces from login brute-forces.
     */
    private function ipBanGateOrAbort(): void
    {
        $maxAttempts = (int) ($GLOBALS['maxloginattempts'] ?? 0);
        if ($maxAttempts <= 0) {
            return;
        }
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $total = (int) NexusDB::table('loginattempts')->where('ip', $ip)->sum('attempts');
        if ($total < $maxAttempts) {
            return;
        }
        NexusDB::table('loginattempts')->where('ip', $ip)->update(['banned' => 'yes']);
        abort(403, 'Your IP is banned for too many failed login / recover attempts.');
    }

    /**
     * `failedlogins(_, true)` parity — bump the per-IP attempt
     * counter with `type = 'recover'`, then bark with the supplied
     * message.
     */
    private function failedlogin(string $msg): never
    {
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $count = (int) NexusDB::table('loginattempts')->where('ip', $ip)->count();
        if ($count === 0) {
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => 1,
                'type' => 'recover',
            ]);
        } else {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update([
                    'attempts' => NexusDB::raw('attempts + 1'),
                    'type' => 'recover',
                ]);
        }
        $this->bark($msg);
    }

    private function remainingAttempts(int $maxAttempts): int
    {
        if ($maxAttempts <= 0) {
            return 0;
        }
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $total = (int) NexusDB::table('loginattempts')->where('ip', $ip)->sum('attempts');

        return max(0, $maxAttempts - $total);
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
        $heading = (string) ($lang['std_recover_failed'] ?? 'Recover failed');
        if (function_exists('stdmsg')
            && function_exists('stdhead')
            && function_exists('stdfoot')) {
            ob_start();
            try {
                stdhead();
                stdmsg($heading, $msg);
                stdfoot();
            } finally {
                $body = (string) ob_get_clean();
            }
            if ($body !== '') {
                return new Response($body, 200);
            }
        }

        $titleEsc = htmlspecialchars($heading, ENT_QUOTES | ENT_HTML5);
        $msgEsc = htmlspecialchars($msg, ENT_QUOTES | ENT_HTML5);
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

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body></html>
HTML;
    }

    /** @return array<string,string> */
    private function loadLang(string $page): array
    {
        if (! function_exists('get_langfile_path')) {
            return [];
        }
        $path = base_path(get_langfile_path($page));
        $varName = 'lang_'.preg_replace('/\.php$/', '', $page);
        ${$varName} = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array(${$varName} ?? null) ? ${$varName} : [];
    }
}
