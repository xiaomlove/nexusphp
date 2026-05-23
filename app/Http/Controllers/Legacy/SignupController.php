<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/signup.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. This is **part 2** of
 * the three-PR auth-flow batch:
 *
 *   - PR-A: login.php + takelogin.php (PR #304, merged)
 *   - PR-B (this PR): signup.php + takesignup.php + recover.php +
 *     confirm_resend.php
 *   - PR-C: maxlogin.php (admin failed-login tool)
 *
 * GET-only signup form. Reachable as a guest (the legacy script
 * called `cur_user_check()` to bounce already-logged-in users).
 * Two render modes:
 *   - Normal signup (no query string).
 *   - Invite signup (`?type=invite&invitenumber=<hash>`) — looks up
 *     the matching `invites` row, ignores any payload with an
 *     unknown / already-used hash.
 *
 * Form contract preserved verbatim
 * --------------------------------
 *   - `<form id="signup-form" method="post" action="takesignup.php">`
 *     so the JS rendered by `render_password_hash_js()` keeps
 *     targeting the right form id and the existing
 *     `App\Http\Controllers\Legacy\TakeSignupController` keeps
 *     receiving the same field set without a template / JS edit.
 *   - All field names — `wantusername`, `wantpassword`, `passagain`,
 *     `email`, `country`, `school` (optional), `gender`,
 *     `rulesverify`, `faqverify`, `ageverify`, `hash` (invite code),
 *     `inviter` (invite mode), `type` (`invite` in invite mode) —
 *     are at the same DOM positions, with the same names, so the
 *     existing E2E specs keep passing without selector edits.
 *
 * Cur-user-check
 * --------------
 * Same posture as `LoginController`: already-authenticated callers
 * are 302'd to `/index.php` instead of being shown a `stderr()`
 * envelope.
 *
 * Failed-logins gate
 * ------------------
 * Same inline `failedloginscheck()` parity as `LoginController` —
 * abort 403 once the IP has hit the `$maxloginattempts` threshold
 * so the response goes through Laravel middleware cleanly.
 *
 * Invalid invite handling
 * -----------------------
 * Legacy script: `stderr($lang_signup['std_error'],
 * $lang_signup['std_uninvited'], 0)` (HTTP 200 envelope, message
 * "You are not invited"). We tighten this to a 404 — there's no
 * recoverable input here (the user followed a bad URL), so an
 * envelope is unhelpful and the 404 is the correct semantics for
 * "this thing doesn't exist".
 *
 * Skipped legacy features
 * -----------------------
 * Three minor pieces of the legacy form are NOT re-emitted:
 *   1. The `<select name="sitelanguage">` switcher at the top of
 *      the page. Same Phase 5 sweep item documented on
 *      `LoginController`.
 *   2. The `stdhead()` / `stdfoot()` chrome (legacy logo / top nav
 *      / footer). Same Phase 5 sweep item documented on
 *      `LoginController`.
 *   3. The legacy script's commented-out `passagain` length /
 *      equality client-side validators (those checks are commented
 *      out in `public/takesignup.php` too — `render_password_hash_js`
 *      replaced them with a hash on the JS side).
 */
class SignupController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // cur_user_check() parity.
        if ($this->context->user() !== null) {
            return new RedirectResponse('/index.php');
        }

        // failedloginscheck() parity — IP-banned users get 403,
        // not the form.
        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('signup.php');

        $type = (string) $request->query('type', '');
        $code = (string) $request->query('invitenumber', '');
        $invite = null;
        $inviter = '';

        if ($type === 'invite') {
            if ($code === '') {
                abort(400, (string) ($lang['std_error'] ?? 'Error'));
            }
            $invite = NexusDB::table('invites')
                ->where('valid', (int) Invite::VALID_YES)
                ->where('hash', $code)
                ->first();
            $invite = $invite !== null ? (array) $invite : null;
            if ($invite === null) {
                abort(404, (string) ($lang['std_uninvited'] ?? 'Invite code is invalid.'));
            }
            $inviter = (string) ($invite['inviter'] ?? '');
        }

        $body = $this->renderFormBody($lang, $type, $code, $inviter, $invite);

        $title = $type === 'invite'
            ? (string) ($lang['head_invite_signup'] ?? 'Invite signup')
            : (string) ($lang['head_signup'] ?? 'Signup');

        return new Response($this->wrap($title, $body));
    }

    /**
     * @param  array<string,string>  $lang
     * @param  array<string,mixed>|null  $invite
     */
    private function renderFormBody(array $lang, string $type, string $code, string $inviter, ?array $invite): string
    {
        $isInvite = $type === 'invite';
        $isPreRegister = function_exists('get_setting')
            && get_setting('system.is_invite_pre_email_and_username') === 'yes';
        $showSchool = ($GLOBALS['showschool'] ?? '') === 'yes';
        $restrictEmailDomain = ($GLOBALS['restrictemaildomain'] ?? '') === 'yes';

        $codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $inviterEsc = htmlspecialchars($inviter, ENT_QUOTES, 'UTF-8');

        $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';

        // Username + email inputs are read-only when the inviter
        // pre-registered them and the system-level toggle is on.
        $preUsername = ($isInvite && $invite !== null) ? (string) ($invite['pre_register_username'] ?? '') : '';
        $preEmail = ($isInvite && $invite !== null) ? (string) ($invite['pre_register_email'] ?? '') : '';

        if ($isPreRegister && $preUsername !== '') {
            $usernameInput = sprintf(
                '<input type="text" %s name="wantusername" value="%s" readonly autocomplete="username" />',
                $formInputStyle,
                htmlspecialchars($preUsername, ENT_QUOTES, 'UTF-8'),
            );
        } else {
            $usernameInput = sprintf(
                '<input type="text" %s name="wantusername" autocomplete="username" />',
                $formInputStyle,
            );
        }

        if ($isPreRegister && $preEmail !== '') {
            $emailInput = sprintf(
                '<input type="email" %s name="email" value="%s" readonly autocomplete="email" />',
                $formInputStyle,
                htmlspecialchars($preEmail, ENT_QUOTES, 'UTF-8'),
            );
        } else {
            $emailInput = sprintf(
                '<input type="email" %s name="email" autocomplete="email" />',
                $formInputStyle,
            );
        }

        $rowDesiredUsername = htmlspecialchars((string) ($lang['row_desired_username'] ?? 'Desired username'), ENT_QUOTES);
        $textAllowedChars = htmlspecialchars((string) ($lang['text_allowed_characters'] ?? 'a-z, A-Z, 0-9, _'), ENT_QUOTES);
        $rowPickPassword = htmlspecialchars((string) ($lang['row_pick_a_password'] ?? 'Pick a password'), ENT_QUOTES);
        $textMinSix = htmlspecialchars((string) ($lang['text_minimum_six_characters'] ?? 'Minimum 6 characters'), ENT_QUOTES);
        $rowEnterPassAgain = htmlspecialchars((string) ($lang['row_enter_password_again'] ?? 'Enter password again'), ENT_QUOTES);
        $rowEmail = htmlspecialchars((string) ($lang['row_email_address'] ?? 'Email address'), ENT_QUOTES);
        $rowCountry = htmlspecialchars((string) ($lang['row_country'] ?? 'Country'), ENT_QUOTES);
        $rowSchool = htmlspecialchars((string) ($lang['row_school'] ?? 'School'), ENT_QUOTES);
        $rowGender = htmlspecialchars((string) ($lang['row_gender'] ?? 'Gender'), ENT_QUOTES);
        $rowVerification = htmlspecialchars((string) ($lang['row_verification'] ?? 'Verification'), ENT_QUOTES);
        $radioMale = htmlspecialchars((string) ($lang['radio_male'] ?? 'Male'), ENT_QUOTES);
        $radioFemale = htmlspecialchars((string) ($lang['radio_female'] ?? 'Female'), ENT_QUOTES);
        $cbReadRules = htmlspecialchars((string) ($lang['checkbox_read_rules'] ?? 'I have read the rules'), ENT_QUOTES);
        $cbReadFaq = htmlspecialchars((string) ($lang['checkbox_read_faq'] ?? 'I have read the FAQ'), ENT_QUOTES);
        $cbAge = htmlspecialchars((string) ($lang['checkbox_age'] ?? 'I am old enough'), ENT_QUOTES);
        $textRequired = htmlspecialchars((string) ($lang['text_all_fields_required'] ?? 'All fields are required'), ENT_QUOTES);
        $textCookies = htmlspecialchars((string) ($lang['text_cookies_note'] ?? 'You need cookies enabled to sign up.'), ENT_QUOTES);
        $textNoneSelected = htmlspecialchars((string) ($lang['select_none_selected'] ?? 'None selected'), ENT_QUOTES);
        $btnSignUp = htmlspecialchars((string) ($lang['submit_sign_up'] ?? 'Sign up'), ENT_QUOTES);
        $textEmailNote = (string) ($lang['text_email_note'] ?? '');

        // Captcha row — `show_image_code()` writes a `<tr>` directly
        // when `$iv == 'yes'`, no-op otherwise. Captured into the
        // form body via output buffer the same way `LoginController`
        // does.
        ob_start();
        try {
            if (function_exists('show_image_code')) {
                show_image_code();
            }
        } finally {
            $imageCaptchaRow = (string) ob_get_clean();
        }

        // Render the password-hash JS the same way the legacy
        // script did. Injected at the bottom of the form body.
        ob_start();
        try {
            if (function_exists('render_password_hash_js')) {
                render_password_hash_js('signup-form', 'wantpassword', 'wantpassword', true, 'passagain', 'wantusername');
            }
        } finally {
            $passwordHashJs = (string) ob_get_clean();
        }

        // Country / school dropdown options. Both are sourced from
        // typed Eloquent reads to avoid coupling to legacy
        // `mysql_*` helpers; defaults match the legacy `id == 8`
        // (country) and `id == 35` (school) sentinel values.
        $countriesHtml = '<option value="8">---- '.$textNoneSelected.' ----</option>';
        $countryRows = NexusDB::table('countries')->select(['id', 'name'])->orderBy('name')->get();
        foreach ($countryRows as $row) {
            $row = (array) $row;
            $rid = (int) ($row['id'] ?? 0);
            $rname = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sel = $rid === 8 ? ' selected' : '';
            $countriesHtml .= sprintf('<option value="%d"%s>%s</option>', $rid, $sel, $rname);
        }

        $schoolBlock = '';
        if ($showSchool) {
            $schoolsHtml = '<option value="35">---- '.$textNoneSelected.' ----</option>';
            $schoolRows = NexusDB::table('schools')->select(['id', 'name'])->orderBy('name')->get();
            foreach ($schoolRows as $row) {
                $row = (array) $row;
                $rid = (int) ($row['id'] ?? 0);
                $rname = htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $sel = $rid === 35 ? ' selected' : '';
                $schoolsHtml .= sprintf('<option value="%d"%s>%s</option>', $rid, $sel, $rname);
            }
            $schoolBlock =
                '<tr><td class="rowhead">'.$rowSchool.'</td>'
                .'<td class="rowfollow" align="left"><select name="school">'.$schoolsHtml.'</select></td></tr>';
        }

        $emailNoteBlock = '';
        if ($restrictEmailDomain) {
            $allowed = function_exists('allowedemails') ? (string) allowedemails() : '';
            $emailNoteBlock =
                '<table width="250" border="0" cellspacing="0" cellpadding="0">'
                .'<tr><td class="embedded"><font class="small">'.$textEmailNote.$allowed.'</font></td></tr></table>';
        }

        // Hidden invite-mode inputs.
        $inviteHiddenInputs = '';
        if ($isInvite) {
            $inviteHiddenInputs =
                '<input type="hidden" name="inviter" value="'.$inviterEsc.'" />'
                .'<input type="hidden" name="type" value="invite" />';
        }

        return <<<HTML
<form method="post" action="takesignup.php" id="signup-form">
{$inviteHiddenInputs}
<table border="1" cellspacing="0" cellpadding="10">
<tr><td class="text" align="center" colspan="2">{$textCookies}</td></tr>
<tr><td class="rowhead">{$rowDesiredUsername}</td><td class="rowfollow" align="left">{$usernameInput}<br />
<font class="small">{$textAllowedChars}</font></td></tr>
<tr><td class="rowhead">{$rowPickPassword}</td><td class="rowfollow" align="left"><input type="password" {$formInputStyle} class="wantpassword" autocomplete="new-password" /><br />
<font class="small">{$textMinSix}</font></td></tr>
<tr><td class="rowhead">{$rowEnterPassAgain}</td><td class="rowfollow" align="left"><input type="password" {$formInputStyle} class="passagain" autocomplete="new-password" /></td></tr>
{$imageCaptchaRow}
<tr><td class="rowhead">{$rowEmail}</td><td class="rowfollow" align="left">{$emailInput}{$emailNoteBlock}</td></tr>
<tr><td class="rowhead">{$rowCountry}</td><td class="rowfollow" align="left"><select name="country">{$countriesHtml}</select></td></tr>
{$schoolBlock}
<tr><td class="rowhead">{$rowGender}</td><td class="rowfollow" align="left">
<input type="radio" name="gender" value="Male" />{$radioMale}
<input type="radio" name="gender" value="Female" />{$radioFemale}</td></tr>
<tr><td class="rowhead">{$rowVerification}</td><td class="rowfollow" align="left">
<input type="checkbox" name="rulesverify" value="yes" />{$cbReadRules}<br />
<input type="checkbox" name="faqverify" value="yes" />{$cbReadFaq}<br />
<input type="checkbox" name="ageverify" value="yes" />{$cbAge}</td></tr>
<input type="hidden" name="hash" value="{$codeEsc}" />
<input type="hidden" name="wantpassword" />
<tr><td class="toolbox" colspan="2" align="center"><font color="red"><b>{$textRequired}</b></font><p><input id="submit-btn" type="button" value="{$btnSignUp}" style="height: 25px" /></p></td></tr>
</table>
</form>
{$passwordHashJs}
HTML;
    }

    /**
     * Inline reimplementation of `failedloginscheck()` —
     * see `LoginController` for the rationale.
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
        abort(403, 'Your IP is banned for too many failed login attempts.');
    }

    /**
     * Wrap captured form body in a chrome-less HTML5 envelope —
     * Phase 5 sweep item to replace with the Modern UI auth shell.
     * See `LoginController::wrap()` for the trade-off rationale.
     */
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

    /**
     * @return array<string,string>
     */
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
