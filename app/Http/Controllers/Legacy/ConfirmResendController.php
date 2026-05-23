<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;

/**
 * Replacement for `public/confirm_resend.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Part 2 of 3 in the auth-flow
 * batch (see `SignupController` PHPDoc for the full split).
 *
 * Multi-method "resend confirmation email" flow. Reachable by users
 * who registered but never received / clicked the original
 * confirmation email — they re-enter their email + a new password,
 * we re-send the link.
 *
 *   - **GET**  → render the form (email + new password + repeat).
 *   - **POST** → validate the inputs, rotate the user's password
 *     (still on the legacy md5 secret-pad shape because the user
 *     hasn't logged in yet — `takelogin.php`'s sha256 auto-upgrade
 *     fires on first successful login), and re-send the
 *     confirmation email via the same `sent_mail` envelope as
 *     `TakeSignupController`.
 *
 * The URL stays `/confirm_resend.php` for both methods so the
 * existing FAQ links (`database/seeders/FaqTableSeeder.php`),
 * the legacy lang_login `p_resend_confirm` paragraph, and any
 * recovery emails referencing the page keep working without a
 * redirect rule.
 *
 * Per-IP lock
 * -----------
 * The legacy script wrapped the entire request in a 10-second
 * `NexusLock::lockOrFail("confirm_resend:lock:".getip(), 10)` to
 * stop someone from hammering the resend button to spam the
 * configured SMTP relay. We preserve this verbatim — the legacy
 * lock helper is pure (no globals other than the keyspace) so it
 * works fine inside the Laravel pipeline. On lock-contention,
 * `NexusLock::lockOrFail` throws which we let bubble — the global
 * exception handler renders the configured 503 envelope.
 *
 * Failed-logins / SMTP-disabled gate
 * ----------------------------------
 *   - `failedloginscheck($recover=true)` — same posture as
 *     `RecoverController` (IP ban gate, abort 403 on threshold,
 *     `loginattempts.type = 'recover'` so the maxlogin admin tool
 *     can categorise the attempts).
 *   - When `$verification == 'admin'`, the legacy script exited
 *     with a "site requires admin verification, can't resend" bark.
 *     We preserve that — there's no user-facing flow that a
 *     resend link can recover when the site is admin-confirmation-
 *     only.
 *
 * CSRF: `/confirm_resend.php` is exempt — the legacy
 * `<form action="confirm_resend.php">` has no `@csrf` field. See
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class ConfirmResendController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        // The legacy script took the lock BEFORE `dbconn()` /
        // `failedloginscheck()` so we mirror that.
        $ip = function_exists('getip') ? (string) getip() : (string) $request->ip();
        if (class_exists(NexusLock::class)) {
            NexusLock::lockOrFail('confirm_resend:lock:'.$ip, 10);
        }

        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('confirm_resend.php');

        try {
            $verification = (string) ($GLOBALS['verification'] ?? 'email');
            if ($verification === 'admin') {
                $this->bark((string) ($lang['std_need_admin_verification'] ?? 'This site requires admin verification.'));
            }

            if ($request->isMethod('POST')) {
                return $this->handleResend($request, $lang);
            }

            return new Response($this->wrap(
                (string) ($lang['head_resend'] ?? 'Resend confirmation email'),
                $this->renderFormBody($lang),
            ));
        } catch (BarkException $e) {
            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function handleResend(Request $request, array $lang): Response
    {
        if (($GLOBALS['iv'] ?? '') === 'yes' && function_exists('check_code')) {
            check_code(
                $request->input('imagehash'),
                $request->input('imagestring'),
                'confirm_resend.php',
                true,
            );
        }

        $email = htmlspecialchars(trim((string) $request->input('email', '')));
        $wantpassword = htmlspecialchars(trim((string) $request->input('wantpassword', '')));
        $passagain = htmlspecialchars(trim((string) $request->input('passagain', '')));

        if (function_exists('safe_email')) {
            $email = (string) safe_email($email);
        }

        if ($wantpassword === '' || $passagain === '' || $email === '') {
            $this->bark((string) ($lang['std_fields_blank'] ?? 'A required field is blank.'));
        }

        if (function_exists('check_email') && ! check_email($email)) {
            $this->failedlogin((string) ($lang['std_invalid_email_address'] ?? 'Invalid email address.'));
        }

        $arr = NexusDB::table('users')
            ->where('email', $email)
            ->limit(1)
            ->first();
        $arr = $arr !== null ? (array) $arr : null;
        if ($arr === null) {
            $this->failedlogin((string) ($lang['std_email_not_found'] ?? 'Email not found.'));
        }
        if (($arr['status'] ?? '') !== 'pending') {
            $this->failedlogin((string) ($lang['std_user_already_confirm'] ?? 'Account is already confirmed.'));
        }

        if ($wantpassword !== $passagain) {
            $this->bark((string) ($lang['std_passwords_unmatched'] ?? 'Passwords do not match.'));
        }

        if (strlen($wantpassword) < 6) {
            $this->bark((string) ($lang['std_password_too_short'] ?? 'Password is too short.'));
        }

        if (strlen($wantpassword) > 40) {
            $this->bark((string) ($lang['std_password_too_long'] ?? 'Password is too long.'));
        }

        // The legacy script compared the new password to the
        // (uninitialised) `$wantusername` variable — effectively
        // comparing `$wantpassword == ''`. We mirror that quirk by
        // rejecting a blank-username password match — i.e. an
        // empty-string password.
        if ($wantpassword === '') {
            $this->bark((string) ($lang['std_password_equals_username'] ?? 'Password cannot be empty.'));
        }

        // Mint a new secret + passhash. The legacy script used the
        // old md5(secret . password . secret) format; we preserve
        // it here because the row hasn't been auto-upgraded to
        // sha256 yet (that happens on first successful login —
        // see `TakeLoginController`'s `!auth_key` branch). Storing
        // sha256 here would force the user through the upgrade
        // path twice and leave the row with a fresh `auth_key`
        // before they've even confirmed.
        $secret = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));
        $wantpasshash = md5($secret.$wantpassword.$secret);
        $verification = (string) ($GLOBALS['verification'] ?? 'email');
        $editsecret = $verification === 'admin' ? '' : $secret;

        $updated = NexusDB::table('users')
            ->where('id', (int) $arr['id'])
            ->update([
                'passhash' => $wantpasshash,
                'secret' => $secret,
                'editsecret' => $editsecret,
            ]);
        if (! $updated) {
            $this->bark((string) ($lang['std_database_error'] ?? 'Database error.'));
        }

        $this->sendConfirmEmail((int) $arr['id'], $email, (string) $arr['username'], md5($editsecret), $lang);

        return new RedirectResponse('/ok.php?type=signup&email='.rawurlencode($email));
    }

    /**
     * Render the resend-confirmation form. Shows the legacy "you
     * have N remaining tries" counter so the user can tell when
     * their IP is approaching the ban threshold.
     *
     * @param  array<string,string>  $lang
     */
    private function renderFormBody(array $lang): string
    {
        $maxAttempts = (int) ($GLOBALS['maxloginattempts'] ?? 0);
        $remaining = $this->remainingAttempts($maxAttempts);
        $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';

        $textNote = sprintf((string) ($lang['text_resend_confirmation_mail_note'] ?? ''), $maxAttempts);
        $textYouHave = htmlspecialchars((string) ($lang['text_you_have'] ?? 'You have '), ENT_QUOTES);
        $textRemainingTries = htmlspecialchars((string) ($lang['text_remaining_tries'] ?? ' remaining attempts.'), ENT_QUOTES);
        $rowEmail = htmlspecialchars((string) ($lang['row_registered_email'] ?? 'Registered email'), ENT_QUOTES);
        $rowNewPassword = htmlspecialchars((string) ($lang['row_new_password'] ?? 'New password'), ENT_QUOTES);
        $rowEnterPassAgain = htmlspecialchars((string) ($lang['row_enter_password_again'] ?? 'Enter password again'), ENT_QUOTES);
        $textPasswordNote = htmlspecialchars((string) ($lang['text_password_note'] ?? 'Min 6 characters.'), ENT_QUOTES);
        $btnSendIt = htmlspecialchars((string) ($lang['submit_send_it'] ?? 'Send'), ENT_QUOTES);

        ob_start();
        try {
            if (function_exists('show_image_code')) {
                show_image_code();
            }
        } finally {
            $imageCaptchaRow = (string) ob_get_clean();
        }

        return <<<HTML
{$textNote}
<p>{$textYouHave}<b>{$remaining}</b>{$textRemainingTries}</p>
<form method="post" action="confirm_resend.php">
<table border="1" cellspacing="0" cellpadding="10" style="width: min(100%, 420px);">
<tr><td class="rowhead nowrap">{$rowEmail}</td>
<td class="rowfollow"><input type="email" name="email" autocomplete="email" {$formInputStyle} /></td></tr>
<tr><td class="rowhead nowrap">{$rowNewPassword}</td><td align="left"><input type="password" name="wantpassword" autocomplete="new-password" {$formInputStyle} /><br />
<font class="small">{$textPasswordNote}</font></td></tr>
<tr><td class="rowhead nowrap">{$rowEnterPassAgain}</td><td align="left"><input type="password" name="passagain" autocomplete="new-password" {$formInputStyle} /></td></tr>
{$imageCaptchaRow}
<tr><td class="toolbox" colspan="2" align="center"><input type="submit" class="btn" value="{$btnSendIt}" /></td></tr>
</table></form>
HTML;
    }

    /**
     * Send the new confirmation email — body preserved verbatim
     * from the legacy script using `lang_confirm_resend` keys.
     *
     * @param  array<string,string>  $lang
     */
    private function sendConfirmEmail(int $userId, string $email, string $username, string $psecret, array $lang): void
    {
        if (! function_exists('sent_mail')) {
            return;
        }
        $siteName = method_exists(Setting::class, 'getSiteName')
            ? (string) Setting::getSiteName()
            : (string) ($GLOBALS['SITENAME'] ?? '');
        $siteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $reportMail = (string) ($GLOBALS['REPORTMAIL'] ?? '');
        $title = $siteName.((string) ($lang['mail_title'] ?? ' Confirmation'));
        $baseUrl = function_exists('getSchemeAndHttpHost') ? (string) getSchemeAndHttpHost() : '';
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $confirmUrl = $baseUrl.'/confirm.php?id='.$userId.'&secret='.$psecret;
        $confirmResendUrl = $baseUrl.'/confirm_resend.php';

        $mailTwo = sprintf((string) ($lang['mail_two'] ?? ''), $siteName);
        $mailFive = sprintf(
            (string) ($lang['mail_five'] ?? ''),
            $siteName,
            $siteName,
            $reportMail,
            $siteName,
        );

        $body = ((string) ($lang['mail_one'] ?? '')).$username.$mailTwo
            .'('.$email.')'.((string) ($lang['mail_three'] ?? '')).$ip
            .((string) ($lang['mail_four'] ?? ''))
            .'<b><a href="javascript:void(null)" onclick="window.open(\''.$confirmUrl.'\')">'
            .((string) ($lang['mail_this_link'] ?? 'this link')).' </a></b><br />'
            .$confirmUrl
            .((string) ($lang['mail_four_1'] ?? ''))
            .'<b><a href="javascript:void(null)" onclick="window.open(\''.$confirmResendUrl.'\')">'
            .((string) ($lang['mail_here'] ?? 'here')).'</a></b><br />'
            .$confirmResendUrl
            .'<br />'.$mailFive;

        sent_mail($email, $siteName, $siteEmail, $title, $body, 'signup', false, false, '');
    }

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
        $heading = (string) ($lang['resend_confirmation_email_failed'] ?? 'Resend confirmation email failed');
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
