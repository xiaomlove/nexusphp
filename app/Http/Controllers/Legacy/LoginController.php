<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\OauthProvider;
use App\Models\Setting;
use App\Repositories\UserPasskeyRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/login.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. This is **part 1** of
 * a three-PR auth-flow batch:
 *
 *   - PR-A (this PR): login.php + takelogin.php
 *   - PR-B: signup.php + takesignup.php + recover.php + confirm_resend.php
 *   - PR-C: maxlogin.php (admin failed-login tool)
 *
 * GET-only login form. Reachable as a guest (the legacy script had
 * no `loggedinorreturn()` gate — by definition this is the page
 * you visit BEFORE you have a session). The route deliberately
 * lives OUTSIDE `auth.nexus:nexus-web` for that reason.
 *
 * Form contract preserved verbatim
 * --------------------------------
 *   - `<form id="login-form" method="post" action="takelogin.php">`
 *     so the existing JS in `public/js/common.js` (the challenge-
 *     response wiring rendered by `render_password_challenge_js()`)
 *     keeps targeting the right form id and the existing
 *     `App\Models\Setting::getIsUseChallengeResponseAuthentication()`
 *     toggle keeps working without a JS change.
 *   - `username` / `password` (or `class="password"` when challenge-
 *     response is enabled) / `two_step_code` / `imagestring` +
 *     `imagehash` (image captcha) / `logout` / `returnto` /
 *     `response` (challenge-response hidden input) inputs are all
 *     present at the same DOM positions, with the same names,
 *     so the existing E2E specs `login.spec.ts` etc. keep passing
 *     without selector edits.
 *   - The OAuth-providers list and the "Submit a complaint" link
 *     are rendered immediately below the form, as before.
 *   - The `[N] failed logins → ban` warning + `remaining()` counter
 *     are kept verbatim (they're the user-visible part of the
 *     legacy anti-bruteforce).
 *
 * Cur-user-check
 * --------------
 * Legacy `cur_user_check()` (`include/functions.php:1069`)
 * `stderr()`-exited with the "you are already logged in" page when
 * `$CURUSER` was set. We tighten that to a 302 redirect to
 * `/index.php` — semantically equivalent ("don't show the login
 * form to a logged-in user") but doesn't render an error envelope
 * for what is effectively a benign double-visit. The same posture
 * is used by every Modern UI auth surface.
 *
 * Failed-logins gate
 * ------------------
 * The legacy `failedloginscheck()` global `stderr()`-exits when the
 * IP has hit the `$maxloginattempts` threshold. Inside Laravel that
 * `die()` skips middleware. We re-implement the check inline here
 * so we can return a proper 403 abort that goes through the
 * pipeline cleanly. The check semantics are bit-for-bit identical
 * (sum `loginattempts.attempts` for the IP; flip `banned='yes'`
 * once over threshold).
 *
 * Skipped legacy features
 * -----------------------
 * Two minor pieces of the legacy form are NOT re-emitted by this
 * controller:
 *   1. The `<select name="sitelanguage">` switcher at the top of
 *      the page (line 25-43 of legacy login.php). It hadn't a
 *      first-class entry point anywhere in the modern UI; users
 *      switch locale through their profile (after signup) or
 *      via the `c_lang_folder` cookie. Phase 5 will re-add a
 *      proper Modern UI locale switcher.
 *   2. The `helpbox` iframe + shoutbox embed at the bottom of the
 *      page (line 109-127). `shoutbox.php` is itself slated for
 *      Phase 3 Livewire migration and `smile_row()` depends on a
 *      bundle of legacy globals (`$lang_helpbox`, `$BASEURL`,
 *      `$smileys`, etc.) that bootstrap brittle when called from
 *      the Laravel pipeline. The "Need help?" affordance is
 *      re-added via the existing `complains.php` link below the
 *      form.
 *
 * Both omissions are documentation-noted Phase 5 sweep items, not
 * functional regressions.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // cur_user_check() parity — already-logged-in users
        // shouldn't see the login form.
        if ($this->context->user() !== null) {
            return new RedirectResponse('/index.php');
        }

        // failedloginscheck() parity — IP-banned users get a 403,
        // not the form.
        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('login.php');

        $secret = (string) $request->query('secret', '');
        $returnto = (string) $request->query('returnto', '');
        $nowarn = $request->query('nowarn');

        // Build the form body in a single output buffer so we can
        // splice in the legacy `print()`-based helpers (image
        // captcha, challenge-response JS, passkey login JS) at
        // their original DOM positions.
        ob_start();
        try {
            echo $this->renderFormBody($lang, $secret, $returnto, $nowarn);
        } finally {
            $body = (string) ob_get_clean();
        }

        return new Response($this->wrap(
            (string) ($lang['head_login'] ?? 'Login'),
            $body,
        ));
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderFormBody(array $lang, string $secret, string $returnto, mixed $nowarn): string
    {
        $secretEsc = htmlspecialchars($secret, ENT_QUOTES, 'UTF-8');
        $returntoEsc = htmlspecialchars($returnto, ENT_QUOTES, 'UTF-8');
        $maxAttempts = (int) ($GLOBALS['maxloginattempts'] ?? 0);
        $remaining = $this->remainingAttempts($maxAttempts);

        $useChallengeResponse = Setting::getIsUseChallengeResponseAuthentication();
        $passwordAttr = 'class="password"';
        if (! $useChallengeResponse) {
            $passwordAttr .= ' name="password"';
        }

        $smtptype = (string) ($GLOBALS['smtptype'] ?? 'none');

        // Wrap top-of-page returnto warning the same way the legacy
        // script did: the warning shows only when the URL carried
        // `?returnto=...` AND the optional `?nowarn=1` opt-out
        // wasn't set.
        $returntoWarning = '';
        if ($returnto !== '' && $nowarn === null) {
            $returntoWarning =
                '<h1>'.htmlspecialchars((string) ($lang['h1_not_logged_in'] ?? 'Not logged in'), ENT_QUOTES).'</h1>'
                .'<p><b>'.htmlspecialchars((string) ($lang['p_error'] ?? 'Error:'), ENT_QUOTES).'</b> '
                .htmlspecialchars((string) ($lang['p_after_logged_in'] ?? 'You must be logged in to access that page.'), ENT_QUOTES)
                .'</p>';
        }

        // Anti-bruteforce warning paragraph + remaining-attempts
        // counter. Wording is verbatim from the legacy lang file.
        $antiBruteforce =
            '<p>'.htmlspecialchars((string) ($lang['p_need_cookies_enables'] ?? 'You need cookies enabled to log in.'), ENT_QUOTES)
            .'<br /> [<b>'.$maxAttempts.'</b>] '
            .htmlspecialchars((string) ($lang['p_fail_ban'] ?? 'failed login attempts will result in an IP ban.'), ENT_QUOTES)
            .'</p>'
            .'<p>'.htmlspecialchars((string) ($lang['p_you_have'] ?? 'You have'), ENT_QUOTES)
            .' <b>'.$remaining.'</b> '
            .htmlspecialchars((string) ($lang['p_remaining_tries'] ?? 'remaining attempts.'), ENT_QUOTES)
            .'</p>';

        $formInputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';

        $rowUsername = htmlspecialchars((string) ($lang['rowhead_username'] ?? 'Username'), ENT_QUOTES);
        $rowPassword = htmlspecialchars((string) ($lang['rowhead_password'] ?? 'Password'), ENT_QUOTES);
        $rowTwoStep = htmlspecialchars((string) ($lang['rowhead_two_step_code'] ?? '2-step code'), ENT_QUOTES);
        $twoStepTooltip = htmlspecialchars((string) ($lang['two_step_code_tooltip'] ?? 'Optional 2-step authenticator code'), ENT_QUOTES);
        $textAdvanced = htmlspecialchars((string) ($lang['text_advanced_options'] ?? 'Advanced options'), ENT_QUOTES);
        $textAutoLogout = htmlspecialchars((string) ($lang['text_auto_logout'] ?? 'Auto-logout'), ENT_QUOTES);
        $checkboxAutoLogout = htmlspecialchars((string) ($lang['checkbox_auto_logout'] ?? 'Log me out after 15 minutes of inactivity.'), ENT_QUOTES);
        $btnLogin = htmlspecialchars((string) ($lang['button_login'] ?? 'Login'), ENT_QUOTES);
        $btnReset = htmlspecialchars((string) ($lang['button_reset'] ?? 'Reset'), ENT_QUOTES);

        // Capture the legacy `print()`-based helpers (image captcha
        // row, challenge-response JS, passkey JS) into the same
        // output buffer as the form body. `show_image_code()` is a
        // no-op when `$iv != 'yes'`, so the captcha `<tr>` is
        // omitted automatically when the deployment doesn't enable
        // image captcha.
        ob_start();
        try {
            if (function_exists('show_image_code')) {
                show_image_code();
            }
        } finally {
            $imageCaptchaRow = (string) ob_get_clean();
        }

        ob_start();
        try {
            if (function_exists('render_password_challenge_js')) {
                render_password_challenge_js('login-form', 'username', 'password');
            }
        } finally {
            $challengeResponseJs = (string) ob_get_clean();
        }

        ob_start();
        try {
            if (class_exists(UserPasskeyRepository::class)
                && method_exists(UserPasskeyRepository::class, 'renderLogin')) {
                UserPasskeyRepository::renderLogin();
            }
        } finally {
            $passkeyForm = (string) ob_get_clean();
        }

        // Below-form navigation paragraphs. Hidden when SMTP is
        // disabled (the legacy script wrapped them in
        // `if ($smtptype != 'none') { ... }` because the recover/
        // confirm-resend flows are SMTP-dependent).
        $navParagraphs =
            '<p>'.((string) ($lang['p_no_account_signup'] ?? '')).'</p>';
        if ($smtptype !== 'none') {
            $navParagraphs .=
                '<p>'.((string) ($lang['p_forget_pass_recover'] ?? '')).'</p>'
                .'<p>'.((string) ($lang['p_account_banned'] ?? '')).'</p>'
                .'<p>'.((string) ($lang['p_resend_confirm'] ?? '')).'</p>';
        }

        $oauthList = $this->renderOauthProviderList($lang);
        $complainLink = $this->renderComplainLink($lang);

        $returntoInput = '';
        if ($returnto !== '') {
            $returntoInput = '<input type="hidden" name="returnto" value="'.$returntoEsc.'" />';
        }

        $challengeResponseInput = '';
        if ($useChallengeResponse) {
            $challengeResponseInput = '<input type="hidden" name="response" />';
        }

        return <<<HTML
{$returntoWarning}
<form id="login-form" method="post" action="takelogin.php">
    <input type="hidden" name="secret" value="{$secretEsc}">
{$antiBruteforce}
<table border="0" cellpadding="5">
<tr><td class="rowhead">{$rowUsername}</td><td class="rowfollow" align="left"><input type="text" class="username" name="username" autocomplete="username" {$formInputStyle} /></td></tr>
<tr><td class="rowhead">{$rowPassword}</td><td class="rowfollow" align="left"><input type="password" {$passwordAttr} autocomplete="current-password" {$formInputStyle} /></td></tr>
<tr><td class="rowhead">{$rowTwoStep}</td><td class="rowfollow" align="left"><input type="text" name="two_step_code" inputmode="numeric" pattern="[0-9]*" placeholder="{$twoStepTooltip}" {$formInputStyle} /></td></tr>
{$imageCaptchaRow}
<tr><td class="toolbox" colspan="2" align="left">{$textAdvanced}</td></tr>
<tr><td class="rowhead">{$textAutoLogout}</td><td class="rowfollow" align="left"><input class="checkbox" type="checkbox" name="logout" value="yes" />{$checkboxAutoLogout}</td></tr>
<tr><td class="toolbox" colspan="2" align="right"><input id="submit-btn" type="button" value="{$btnLogin}" class="btn" /> <input type="reset" value="{$btnReset}" class="btn" /></td></tr>
</table>
{$returntoInput}
{$challengeResponseInput}
{$passkeyForm}
</form>
{$oauthList}
{$complainLink}
{$navParagraphs}
{$challengeResponseJs}
HTML;
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderOauthProviderList(array $lang): string
    {
        $oauthProviders = OauthProvider::query()
            ->orderBy('priority', 'desc')
            ->where('enabled', '=', 1)
            ->get();
        if ($oauthProviders->isEmpty()) {
            return '';
        }
        $items = [];
        foreach ($oauthProviders as $provider) {
            $items[] = sprintf(
                '[<b><a href="oauth/redirect/%s">%s</a></b>]',
                htmlspecialchars((string) $provider->uuid, ENT_QUOTES),
                htmlspecialchars((string) $provider->name, ENT_QUOTES),
            );
        }
        $heading = htmlspecialchars((string) ($lang['other_methods'] ?? 'Other login methods'), ENT_QUOTES);

        return sprintf('<p>%s: %s</p>', $heading, implode('&nbsp;&nbsp;', $items));
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderComplainLink(array $lang): string
    {
        if (! Setting::getIsComplainEnabled()) {
            return '';
        }

        return sprintf(
            '<p>[<b><a href="complains.php">%s</a></b>]</p>',
            htmlspecialchars((string) ($lang['text_complain'] ?? 'Submit a complaint'), ENT_QUOTES),
        );
    }

    /**
     * Inline reimplementation of `failedloginscheck()` from
     * `include/functions.php:1308`. The legacy version `stderr()`-
     * exits via `die()`, which inside the Laravel pipeline skips
     * middleware. We re-implement so we can `abort(403)` cleanly.
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
     * Mirror of the legacy `remaining()` helper. Returns the number
     * of attempts the user has left before they hit the IP ban.
     */
    private function remainingAttempts(int $maxAttempts): int
    {
        if ($maxAttempts <= 0) {
            return 0;
        }
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $total = (int) NexusDB::table('loginattempts')->where('ip', $ip)->sum('attempts');

        return max(0, $maxAttempts - $total);
    }

    /**
     * Wrap the captured form body in a chrome-less HTML5 envelope.
     * Same trade-off every Phase 2 controller has made — the legacy
     * `stdhead()` / `stdfoot()` chrome (logo, top nav, footer) is
     * not reproduced here because it depends on top-level-script
     * globals that the Laravel pipeline does not expose. Phase 5
     * will replace the envelope with a Modern UI auth shell
     * (Livewire `Login` page).
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
     * Load the legacy `lang_login` dictionary for the current
     * language folder. Mirrors the resolution strategy used by
     * every other Phase 2 controller — `get_langfile_path()`
     * normalises the cookie / fallback chain so we don't have to.
     *
     * @return array<string,string>
     */
    private function loadLang(string $page): array
    {
        $path = base_path(get_langfile_path($page));
        $varName = 'lang_'.preg_replace('/\.php$/', '', $page);
        ${$varName} = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array(${$varName} ?? null) ? ${$varName} : [];
    }
}
