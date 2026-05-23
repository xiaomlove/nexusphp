<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takelogin.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Part 1 of 3 in the auth-flow
 * batch (see `LoginController` PHPDoc for the full split).
 *
 * POST-only login write-handler. Receives the credential payload
 * from the `<form action="takelogin.php">` rendered by
 * `LoginController`. The URL stays `/takelogin.php` so the existing
 * form action keeps targeting the right endpoint without template /
 * JS changes.
 *
 * Faithful 1:1 transcription of the legacy 120-LOC script
 * preserving every side effect — login is the highest-risk path on
 * the site and a regression here locks every user out
 * simultaneously, so the migration is intentionally minimal-
 * surface-area:
 *
 *   1. `failedloginscheck()` — IP ban gate (re-implemented inline).
 *   2. `cur_user_check()` — redirect already-logged-in users to
 *      `/index.php` (matches `LoginController` posture).
 *   3. Optional image captcha verification when `$iv == 'yes'`.
 *   4. Required POST field gate: `response` (challenge-response
 *      mode) or `password` (legacy mode).
 *   5. Load the `users` row by username; missing → `failedlogins()`.
 *   6. `status='pending'` → "account unconfirmed" bark.
 *   7. `enabled='no'` (and self-enable bonus disabled) → "account
 *      disabled" bark.
 *   8. Two-step code verification when `users.two_step_secret` is
 *      set.
 *   9. Challenge-response OR md5-fallback hash comparison via
 *      `hash_equals` (constant-time).
 *  10. Auto-upgrade old md5 `passhash` to sha256 challenge-response
 *      format on successful md5-mode login.
 *  11. `clear_user_cache()` + `users.lang` update + `auth_key` mint
 *      when missing.
 *  12. `UserRepository::saveLoginLog()`.
 *  13. `logincookie()` with `?logout=yes` → 15-minute TTL,
 *      otherwise the configured `system.cookie_valid_days`.
 *  14. 302 redirect to `?returnto=...` or `/index.php`.
 *
 * The cookie format (`c_secure_pass`), hash algorithms (sha256 +
 * md5 fallback), secret seeding (20-hex `mksecret`), and cache key
 * shape (`get_challenge_key`) are all preserved by calling the
 * EXISTING legacy helpers directly — `logincookie()`, `mksecret()`,
 * `get_challenge_key()` are pure functions that work fine inside
 * the Laravel pipeline.
 *
 * Bark / exit transformation
 * --------------------------
 * The legacy `failedlogins()` and `bark()` helpers `stderr()`-exit
 * via `die()`. Inside the Laravel pipeline `die()` mid-request
 * skips middleware (no session save, no cookie queue flush). We
 * re-use the `App\Http\Controllers\Legacy\BarkException` pattern
 * introduced by PR #302 (`TakeUploadController`): every legacy
 * `failedlogins()` call site throws the exception, and the
 * `__invoke` catch block records the failed attempt + renders the
 * legacy chrome envelope captured into a `Response`. The IP-ban
 * counter increment side effect is preserved.
 *
 * CSRF: `/takelogin.php` is exempt — the legacy form has no
 * `@csrf` field. Adding CSRF plumbing to login forms is a separate
 * cross-cutting change because it touches the challenge-response
 * JS in `public/js/common.js` and the OAuth + Passkey login
 * branches. See `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class TakeLoginController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $userRep,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // cur_user_check() parity — already authenticated callers
        // (e.g., a stray double-submit from a freshly-confirmed
        // user) don't need to re-authenticate.
        if ($this->context->user() !== null) {
            return new RedirectResponse('/index.php');
        }

        // failedloginscheck() parity — abort 403 if the IP is past
        // the threshold.
        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('takelogin.php');

        try {
            return $this->handle($request, $lang);
        } catch (BarkException $e) {
            // failedlogins() side effect: increment the per-IP
            // attempt counter so a brute-forcer eventually trips
            // the IP ban.
            $this->recordFailedAttempt(false);

            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function handle(Request $request, array $lang): RedirectResponse
    {
        // mkglobal('username') parity — the legacy script bailed
        // silently (`die()`) when the field was missing. We use
        // a bark instead so the user sees a useful error.
        $username = (string) ($request->input('username', ''));
        if ($username === '') {
            $this->bark((string) ($lang['std_login_fail_note'] ?? 'Username and password did not match.'));
        }

        // Image captcha. Legacy script: `if ($iv == "yes")
        // check_code($_POST['imagehash'], $_POST['imagestring'],
        // 'login.php', true);`. `check_code()` itself
        // `stderr()`-exits on failure — we let it; on success it
        // returns and we keep going. See the PHPDoc above for the
        // bark / exit posture rationale.
        if (($GLOBALS['iv'] ?? '') === 'yes' && function_exists('check_code')) {
            check_code(
                $request->input('imagehash'),
                $request->input('imagestring'),
                'login.php',
                true,
            );
        }

        $useChallengeResponse = Setting::getIsUseChallengeResponseAuthentication();
        if ($useChallengeResponse) {
            if ((string) $request->input('response', '') === '') {
                $this->bark('Require response parameter.');
            }
        } else {
            if ((string) $request->input('password', '') === '') {
                $this->bark('Require password parameter.');
            }
        }

        $row = NexusDB::table('users')
            ->where('username', $username)
            ->select(['id', 'passhash', 'secret', 'auth_key', 'enabled', 'status', 'two_step_secret', 'lang'])
            ->first();
        $row = $row !== null ? (array) $row : null;

        if ($row === null) {
            $this->bark((string) ($lang['std_login_fail_note'] ?? 'Username and password did not match.'));
        }

        if (($row['status'] ?? '') === 'pending') {
            $this->bark((string) ($lang['std_user_account_unconfirmed'] ?? 'Your account has not been confirmed yet.'));
        }

        if (($row['enabled'] ?? '') === 'no' && (int) Setting::getSelfEnableBonus() <= 0) {
            $this->bark((string) ($lang['std_account_disabled'] ?? 'Your account has been disabled.'));
        }

        // Two-step code verification.
        if (! empty($row['two_step_secret'])) {
            $twoStep = (string) $request->input('two_step_code', '');
            if ($twoStep === '') {
                $this->bark((string) ($lang['std_require_two_step_code'] ?? 'Please enter the two-step authenticator code.'));
            }
            $ga = new \PHPGangsta_GoogleAuthenticator();
            if (! $ga->verifyCode($row['two_step_secret'], $twoStep)) {
                $this->bark((string) ($lang['std_invalid_two_step_code'] ?? 'Invalid two-step authenticator code.'));
            }
        }

        $ip = function_exists('getip') ? (string) getip() : (string) $request->ip();
        $log = "user: {$row['id']}, ip: {$ip}";
        $update = [];

        if ($useChallengeResponse) {
            // Challenge-response path: the JS rendered by
            // `render_password_challenge_js()` minted a per-form
            // nonce (`get_challenge_key($username)`), the user's
            // browser computed the HMAC client-side, and submitted
            // it as `response`. We compare the expected HMAC
            // against the submitted one in constant time.
            $challenge = (string) NexusDB::cache_get(get_challenge_key($username));
            if ($challenge === '') {
                $this->bark('expired');
            }
            $log .= ', useChallengeResponse, client response: '.((string) $request->input('response'));
        } else {
            // Legacy plain-password path: validate via either the
            // current sha256 passhash or the historical md5
            // passhash, with auto-upgrade on success. Wording of
            // the log lines is preserved verbatim from the legacy
            // script for log-reader continuity.
            $password = (string) $request->input('password');
            $passwordHash = hash('sha256', ((string) $row['secret']).hash('sha256', $password));
            $log .= ", !useChallengeResponse, passwordHash: {$passwordHash}";

            if (empty($row['auth_key'])) {
                // Old md5-format passhash — verify with md5 first,
                // then auto-upgrade the row to sha256.
                $expectedMd5 = md5(((string) $row['secret']).$password.((string) $row['secret']));
                if (! hash_equals((string) $row['passhash'], $expectedMd5)) {
                    do_log("{$log}, md5 not equal");
                    $this->bark((string) ($lang['std_login_fail_note'] ?? 'Username and password did not match.'));
                }
                $log .= ', no auth_key, upgrade to challenge response';
                $update['passhash'] = $passwordHash;
                $row['passhash'] = $passwordHash;
            }

            // Synthesise a server-side challenge so the rest of
            // this method can use the same hash_equals comparison
            // path as the challenge-response branch above.
            $challenge = mksecret();
            $serverResponse = hash_hmac('sha256', $passwordHash, $challenge);
            $request->merge(['response' => $serverResponse]);
            $log .= ", server generate response: {$serverResponse}";
        }

        $expectedResponse = hash_hmac('sha256', (string) $row['passhash'], $challenge);
        $log .= ", expectedResponse: {$expectedResponse}";

        if (! hash_equals($expectedResponse, (string) $request->input('response'))) {
            do_log("{$log}, !hash_equals");
            $this->bark((string) ($lang['std_login_fail_note'] ?? 'Username and password did not match.'));
        }

        // Login successful. Drop the per-username challenge cache
        // entry so the same nonce can't be replayed.
        NexusDB::cache_del(get_challenge_key($username));
        do_log("{$log}, login successful");

        $this->userRep->saveLoginLog((int) $row['id'], $ip, 'Web', true);

        // Sync user.lang with the language cookie if they differ.
        $language = Language::query()
            ->where('site_lang_folder', get_langfolder_cookie())
            ->first();
        if ($language !== null && (int) $language->id !== (int) $row['lang']) {
            do_log(sprintf('update user: %s lang: %s => %s', $row['id'], $row['lang'], $language->id));
            $update['lang'] = $language->id;
        }

        // Mint an auth_key on first sha256 login.
        if (empty($row['auth_key'])) {
            $row['auth_key'] = $update['auth_key'] = hash('sha256', mksecret(32));
        }

        if (! empty($update)) {
            User::query()->where('id', $row['id'])->update($update);
            if (function_exists('clear_user_cache')) {
                clear_user_cache((int) $row['id']);
            }
        }

        // Set the auth cookie. logincookie() is a pure
        // setcookie+UPDATE call — works fine inside the Laravel
        // pipeline. The 900-second TTL preserves the legacy
        // "auto-logout" checkbox semantics.
        $logoutChecked = (string) $request->input('logout', '') === 'yes';
        logincookie(
            (int) $row['id'],
            (string) $row['auth_key'],
            $logoutChecked ? 900 : 0,
        );

        $returnto = (string) $request->input('returnto', '');
        if ($returnto !== '') {
            return new RedirectResponse($returnto);
        }

        return new RedirectResponse('/index.php');
    }

    /**
     * Inline reimplementation of `failedloginscheck()` from
     * `include/functions.php:1308`. See `LoginController` PHPDoc
     * for the rationale.
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
     * Inline reimplementation of `failedlogins()` from
     * `include/functions.php:1318` (just the side-effect half —
     * the chrome rendering is handled by `renderBark()` for the
     * Laravel-pipeline compatibility reasons documented in the
     * controller PHPDoc). Increments / inserts the per-IP attempt
     * counter so a brute-forcer eventually trips the
     * `failedloginscheck()` gate.
     */
    private function recordFailedAttempt(bool $recover): void
    {
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();
        $count = (int) NexusDB::table('loginattempts')->where('ip', $ip)->count();
        if ($count === 0) {
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => 1,
            ]);
        } else {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['attempts' => NexusDB::raw('attempts + 1')]);
        }
        if ($recover) {
            NexusDB::table('loginattempts')->where('ip', $ip)->update(['type' => 'recover']);
        }
    }

    private function bark(string $msg): never
    {
        throw new BarkException($msg);
    }

    /**
     * Render the legacy bark envelope. See `TakeUploadController`
     * for the precedent — capture the legacy `stdhead/stdmsg/
     * stdfoot` output via `ob_start`/`ob_get_clean` so the
     * resulting HTML is identical to the legacy chrome, then fall
     * back to a chrome-less envelope when the legacy helpers are
     * not bootstrapped (the test runner path).
     *
     * @param  array<string,string>  $lang
     */
    private function renderBark(string $msg, array $lang): Response
    {
        $heading = (string) ($lang['std_login_fail'] ?? 'Login failed');
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

        // Fallback chrome-less envelope.
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

    /** @return array<string,string> */
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
