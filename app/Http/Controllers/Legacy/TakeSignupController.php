<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Invite;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takesignup.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Part 2 of 3 in the auth-flow
 * batch (see `SignupController` PHPDoc for the full split).
 *
 * POST-only signup write-handler. Receives the registration payload
 * from the `<form action="takesignup.php">` rendered by
 * `SignupController`. The URL stays `/takesignup.php` so the
 * existing form action keeps targeting the right endpoint without
 * a template / JS change.
 *
 * Faithful 1:1 transcription of the legacy 280-LOC script
 * preserving every side effect — registration is a one-shot path
 * (an aborted insert leaves a half-initialized user that the
 * confirmation flow can't recover), so the migration is
 * intentionally minimal-surface-area:
 *
 *   1. `cur_user_check()` parity — already-authenticated callers
 *      are 302'd to `/index.php` (matches `SignupController`).
 *   2. `failedloginscheck()` — IP ban gate (re-implemented inline).
 *   3. Optional image captcha verification when `$iv == 'yes'`.
 *   4. Required POST fields: `wantusername` / `wantpassword` /
 *      `email`. Missing → bark.
 *   5. Invite-mode handling: validate `hash` against `invites`
 *      table, optional inviter mismatch flips the invite to
 *      `valid='no'` AND barks (anti-stuffing). Pre-register
 *      override copies username/email from the invite when the
 *      `system.is_invite_pre_email_and_username` setting is on.
 *   6. Email validation chain: `safe_email` →
 *      `check_email` → `EmailBanned` → `EmailAllowed` →
 *      `validemail` → uniqueness (BINARY collation lookup).
 *   7. Country (int) / gender (Male|Female|male|female) /
 *      `rulesverify` + `faqverify` + `ageverify` checkboxes all
 *      `yes` / username uniqueness / `validusername`.
 *   8. INSERT users row. Hash is `sha256($secret . $wantpassword)`,
 *      passkey is `md5($username . now . $passhash)`, status is
 *      `pending`, class is `$defaultclass_class`, lang is
 *      `get_langid_from_langcookie()`, stylesheet is `$defcss`,
 *      uploaded is `$iniupload_main` (when positive), and the
 *      auto-confirm `editsecret` is empty when `$verification ==
 *      'admin'` (otherwise the original `$secret` so a confirmation
 *      email link can verify it).
 *   9. `fire_event('user_created', $userInfo)` so other plugins
 *      (notifications, statistics) get the same hook payload.
 *  10. Optional temporary-invite grant via `UserRepository::
 *      addTemporaryInvite` when `main.tmp_invite_count > 0`.
 *  11. `Message::add(welcome_pm)` — the in-site welcome PM the
 *      legacy script seeded for every new account.
 *  12. Confirmation email via `sent_mail`. Title / body strings
 *      preserved verbatim from `lang_takesignup` /
 *      `lang_takeinvite` so existing locale dictionaries keep
 *      working without a translation update.
 *  13. Invite-mode follow-ups: invalidate the invite, record the
 *      register email/username on the invite row, send a PM to
 *      the inviter, drop their unread/inbox counters from cache.
 *  14. Redirect to one of three landing pages depending on the
 *      `$verification` setting:
 *        - `admin`     → `/ok.php?type=adminactivate` (or `inviter`).
 *        - `automatic` → `/confirm.php?id=<id>&secret=<md5(secret)>`
 *                        (auto-confirms via `ConfirmController`).
 *        - `email`     → `/ok.php?type=signup&email=<urlencoded>`.
 *      Same redirect targets the legacy script used; the existing
 *      `OkController` / `ConfirmController` continue to render
 *      these pages unchanged.
 *
 * Bark / exit transformation
 * --------------------------
 * Same `BarkException` pattern as `TakeLoginController` /
 * `TakeUploadController`: every legacy `bark($msg); exit;` /
 * `stderr(...); exit;` site throws the exception, the `__invoke`
 * catch block renders the legacy chrome envelope captured into a
 * `Response`. No mid-request `die()` skipping middleware.
 *
 * CSRF: `/takesignup.php` is exempt — the legacy
 * `<form action="takesignup.php">` has no `@csrf` field. See
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class TakeSignupController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $userRep,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        // cur_user_check() parity — a stray double-submit from a
        // freshly-confirmed user shouldn't try to register again.
        if ($this->context->user() !== null) {
            return new RedirectResponse('/index.php');
        }

        $this->ipBanGateOrAbort();

        $lang = $this->loadLang('takesignup.php');

        try {
            return $this->handle($request, $lang);
        } catch (BarkException $e) {
            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function handle(Request $request, array $lang): RedirectResponse
    {
        $type = (string) $request->input('type', '');
        $isInvite = $type === 'invite';

        // Image captcha. The legacy script gates this on `$iv ==
        // 'yes'` and `check_code()` itself stderr-exits on failure
        // — we let it; on success it returns and we keep going.
        if (($GLOBALS['iv'] ?? '') === 'yes' && function_exists('check_code')) {
            $hash = (string) $request->input('hash', '');
            $referer = $isInvite
                ? 'signup.php?type=invite&invitenumber='.htmlspecialchars($hash)
                : 'signup.php';
            check_code(
                $request->input('imagehash'),
                $request->input('imagestring'),
                $referer,
            );
        }

        $invite = null;
        $inviter = '';
        if ($isInvite) {
            $inviter = (string) $request->input('inviter', '');
            // The legacy `int_check()` treats non-numeric or empty
            // values as a hard exit. Replace with a bark for the
            // same observable behaviour from the user's side.
            if (! ctype_digit($inviter) || (int) $inviter <= 0) {
                $this->bark('invalid inviter');
            }
            $code = (string) $request->input('hash', '');
            $invite = NexusDB::table('invites')
                ->where('valid', (int) Invite::VALID_YES)
                ->where('hash', $code)
                ->first();
            $invite = $invite !== null ? (array) $invite : null;
            if ($invite === null) {
                $this->bark('invalid invite code');
            }
            // Anti-stuffing: if the form's hidden `inviter` doesn't
            // match the invite's owner, invalidate the invite and
            // bark (preserving the legacy `stderr` -> exit posture).
            if ((int) $invite['inviter'] !== (int) $inviter) {
                Invite::query()
                    ->where('id', (int) $invite['id'])
                    ->update(['valid' => Invite::VALID_NO]);
                $this->bark((string) (function_exists('nexus_trans')
                    ? nexus_trans('invite.invalid_inviter')
                    : 'Invalid inviter for this invite code.'));
            }
        }

        $isPreRegister = function_exists('get_setting')
            && get_setting('system.is_invite_pre_email_and_username') === 'yes';

        // Read the form payload. When the inviter pre-registered
        // username/email AND the system toggle is on, the invite
        // row is the source of truth — the form fields were
        // rendered read-only on the GET side.
        $wantusername = (string) $request->input('wantusername', '');
        $email = (string) $request->input('email', '');
        $wantpassword = (string) $request->input('wantpassword', '');

        if ($isPreRegister && $isInvite && $invite !== null
            && ! empty($invite['pre_register_username'])
            && ! empty($invite['pre_register_email'])) {
            $wantusername = (string) $invite['pre_register_username'];
            $email = (string) $invite['pre_register_email'];
        }

        $email = htmlspecialchars(trim($email));
        if (function_exists('safe_email')) {
            $email = (string) safe_email($email);
        }

        if (function_exists('check_email') && ! check_email($email)) {
            $this->bark((string) ($lang['std_invalid_email_address'] ?? 'Invalid email address.'));
        }
        if (function_exists('EmailBanned') && EmailBanned($email)) {
            $this->bark((string) ($lang['std_email_address_banned'] ?? 'Email address is banned.'));
        }
        if (function_exists('EmailAllowed') && ! EmailAllowed($email)) {
            $allowed = function_exists('allowedemails') ? (string) allowedemails() : '';
            $this->bark((string) ($lang['std_wrong_email_address_domains'] ?? 'Email domain is not allowed.').$allowed);
        }

        $country = (string) $request->input('country', '');
        if (! ctype_digit($country) || (int) $country <= 0) {
            $this->bark((string) ($lang['std_blank_field'] ?? 'A required field is blank.'));
        }

        $school = null;
        if (($GLOBALS['showschool'] ?? '') === 'yes') {
            $schoolRaw = (string) $request->input('school', '');
            if (! ctype_digit($schoolRaw) || (int) $schoolRaw <= 0) {
                $this->bark((string) ($lang['std_blank_field'] ?? 'A required field is blank.'));
            }
            $school = (int) $schoolRaw;
        }

        $gender = htmlspecialchars(trim((string) $request->input('gender', '')));
        $allowedGenders = ['Male', 'Female', 'male', 'female'];
        if (! in_array($gender, $allowedGenders, true)) {
            $this->bark((string) ($lang['std_invalid_gender'] ?? 'Invalid gender.'));
        }

        if ($wantusername === '' || $wantpassword === '' || $email === '' || $country === '' || $gender === '') {
            $this->bark((string) ($lang['std_blank_field'] ?? 'A required field is blank.'));
        }

        if (strlen($wantusername) > 12) {
            $this->bark((string) ($lang['std_username_too_long'] ?? 'Username is too long.'));
        }

        if (function_exists('validemail') && ! validemail($email)) {
            $this->bark((string) ($lang['std_wrong_email_address_format'] ?? 'Invalid email address format.'));
        }

        if (function_exists('validusername') && ! validusername($wantusername)) {
            $this->bark((string) ($lang['std_invalid_username'] ?? 'Invalid username.'));
        }

        // All three checkboxes must be ticked. Legacy script used
        // `stderr(... std_unqualified)`; we map to the same bark
        // for envelope consistency.
        $rulesverify = (string) $request->input('rulesverify', '');
        $faqverify = (string) $request->input('faqverify', '');
        $ageverify = (string) $request->input('ageverify', '');
        if ($rulesverify !== 'yes' || $faqverify !== 'yes' || $ageverify !== 'yes') {
            $this->bark((string) ($lang['std_unqualified'] ?? 'You must agree to all of the rules to sign up.'));
        }

        // Email uniqueness — case-sensitive (BINARY) match exactly
        // like the legacy script. `users.email` is utf8mb4 but
        // signup compares case-sensitively to avoid silent
        // collisions on case-folded variants.
        $emailInUse = NexusDB::table('users')
            ->whereRaw('BINARY email = ?', [$email])
            ->count();
        if ($emailInUse > 0) {
            $this->bark((string) ($lang['std_email_address'] ?? 'Email address').' '.$email.' '.((string) ($lang['std_in_use'] ?? 'is already in use.')));
        }

        // Username uniqueness.
        $existingUserCount = NexusDB::table('users')
            ->where('username', $wantusername)
            ->count();
        if ($existingUserCount > 0) {
            $this->bark((string) ($lang['std_username_exists'] ?? 'Username already exists.'));
        }

        // Hash the password the same way the legacy script did
        // (sha256(secret . sha256(password)) is what `takelogin.php`
        // verifies). Crucially `secret` is the same value stored
        // on the row — auto-confirm needs it later via `editsecret`.
        $secret = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));
        $wantpasshash = hash('sha256', $secret.$wantpassword);
        $verification = (string) ($GLOBALS['verification'] ?? 'email');
        $editsecret = $verification === 'admin' ? '' : $secret;
        $defaultClass = (int) ($GLOBALS['defaultclass_class'] ?? User::CLASS_USER);
        $defcss = (int) ($GLOBALS['defcss'] ?? 1);
        $iniupload = (int) ($GLOBALS['iniupload_main'] ?? 0);
        $inviteCount = (int) ($GLOBALS['invite_count'] ?? 0);
        $authKey = function_exists('mksecret') ? (string) mksecret() : bin2hex(random_bytes(20));
        $passkey = md5($wantusername.date('Y-m-d H:i:s').$wantpasshash);
        $sitelangid = function_exists('get_langid_from_langcookie') ? (int) get_langid_from_langcookie() : 0;

        $now = date('Y-m-d H:i:s');
        $insertData = [
            'username' => $wantusername,
            'passhash' => $wantpasshash,
            'passkey' => $passkey,
            'secret' => $secret,
            'auth_key' => $authKey,
            'editsecret' => $editsecret,
            'email' => $email,
            'country' => (int) $country,
            'gender' => $gender,
            'status' => 'pending',
            'class' => $defaultClass,
            'invites' => $inviteCount,
            'added' => $now,
            'last_access' => $now,
            'lang' => $sitelangid,
            'stylesheet' => $defcss,
            'uploaded' => $iniupload > 0 ? $iniupload : 0,
        ];
        if ($isInvite) {
            $insertData['invited_by'] = (int) $inviter;
        }
        if ($school !== null) {
            $insertData['school'] = $school;
        }

        $id = (int) NexusDB::insert('users', $insertData);
        $userInfo = User::query()->find($id, User::$commonFields);

        if (function_exists('fire_event')) {
            fire_event('user_created', $userInfo);
        }

        // Optional temporary-invite grant.
        if (function_exists('get_setting')) {
            $tmpInviteCount = (int) get_setting('main.tmp_invite_count');
            if ($tmpInviteCount > 0) {
                $this->userRep->addTemporaryInvite(null, $id, 'increment', $tmpInviteCount, 7);
            }
        }

        // Welcome PM — `MessageTemplate::forRegisterWelcome` with a
        // fallback to the legacy `lang_takesignup` strings.
        $siteName = method_exists(Setting::class, 'getSiteName')
            ? (string) Setting::getSiteName()
            : (string) ($GLOBALS['SITENAME'] ?? '');
        $subject = (string) ($lang['msg_subject'] ?? 'Welcome to ').$siteName.'!';
        $msg = $userInfo !== null
            ? (string) MessageTemplate::forRegisterWelcome((int) $userInfo->lang, ['username' => $userInfo->username])
            : '';
        if ($msg === '') {
            $youAre = (string) ($lang['msg_you_are_a_member'] ?? 'You are a member of %s.');
            $msg = (string) ($lang['msg_congratulations'] ?? 'Congratulations, ').$wantusername.sprintf($youAre, $siteName, $siteName);
        }
        Message::add([
            'sender' => 0,
            'receiver' => $id,
            'subject' => $subject,
            'added' => $now,
            'msg' => $msg,
        ]);

        // Invite follow-ups: mark invite invalid + record register
        // info + send PM to the inviter.
        if ($isInvite && $invite !== null) {
            Invite::query()
                ->where('id', (int) $invite['id'])
                ->update([
                    'valid' => Invite::VALID_NO,
                    'invitee_register_uid' => $id,
                    'invitee_register_email' => (string) $request->input('email', ''),
                    'invitee_register_username' => (string) $request->input('wantusername', ''),
                ]);

            if (function_exists('get_user_locale') && function_exists('nexus_trans')) {
                $locale = (string) get_user_locale((int) $inviter);
                $inviterSubject = (string) nexus_trans('user.msg_invited_user_has_registered', [], $locale);
                $youInvited = (string) nexus_trans('user.msg_user_you_invited', [], $locale);
                $hasRegistered = (string) nexus_trans('user.msg_has_registered', [], $locale);
                $inviterMsg = $youInvited.$wantusername.$hasRegistered;
                Message::add([
                    'sender' => 0,
                    'receiver' => (int) $inviter,
                    'subject' => $inviterSubject,
                    'added' => $now,
                    'msg' => $inviterMsg,
                ]);
            }

            // Drop the inviter's unread / inbox cache so the new PM
            // shows up immediately.
            if (isset($GLOBALS['Cache']) && method_exists($GLOBALS['Cache'], 'delete_value')) {
                $GLOBALS['Cache']->delete_value('user_'.$inviter.'_unread_message_count');
                $GLOBALS['Cache']->delete_value('user_'.$inviter.'_inbox_count');
            }
        }

        // Confirmation-email body — preserved verbatim from the
        // legacy script using `lang_takesignup` / `lang_takeinvite`
        // dictionary keys (the latter still loaded by every
        // `TakeInviteController` request, so the keys remain live).
        $row = NexusDB::table('users')
            ->where('id', $id)
            ->select(['passhash', 'secret', 'editsecret', 'status'])
            ->first();
        $row = $row !== null ? (array) $row : [];
        $psecret = md5((string) ($row['secret'] ?? ''));
        $smtptype = (string) ($GLOBALS['smtptype'] ?? 'none');

        // Three-way redirect — same target URLs the legacy script
        // used, so the existing `OkController` / `ConfirmController`
        // keep rendering the right landing pages.
        if ($verification === 'admin') {
            $okType = $isInvite ? 'inviter' : 'adminactivate';

            return new RedirectResponse('/ok.php?type='.$okType);
        }

        if ($verification === 'automatic' || $smtptype === 'none') {
            return new RedirectResponse('/confirm.php?id='.$id.'&secret='.$psecret);
        }

        // Email-verification path.
        $this->sendConfirmationEmail((int) $id, $email, $wantusername, $psecret, $lang);

        return new RedirectResponse('/ok.php?type=signup&email='.rawurlencode($email));
    }

    /**
     * Send the confirmation email exactly the way the legacy script
     * did — same `lang_takesignup`/`lang_takeinvite` keys, same
     * URLs, same `sent_mail()` arguments. Falls back to a no-op
     * when the SMTP helper isn't bootstrapped (test runner path).
     *
     * @param  array<string,string>  $lang
     */
    private function sendConfirmationEmail(int $id, string $email, string $username, string $psecret, array $lang): void
    {
        if (! function_exists('sent_mail')) {
            return;
        }
        $siteName = method_exists(Setting::class, 'getSiteName')
            ? (string) Setting::getSiteName()
            : (string) ($GLOBALS['SITENAME'] ?? '');
        $reportMail = (string) ($GLOBALS['REPORTMAIL'] ?? '');
        $siteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $usern = htmlspecialchars($username);
        $title = $siteName.((string) ($lang['mail_title'] ?? ' Signup'));
        $confirmUrl = function_exists('getSchemeAndHttpHost')
            ? getSchemeAndHttpHost().'/confirm.php?id='.$id.'&secret='.$psecret
            : '/confirm.php?id='.$id.'&secret='.$psecret;
        $confirmResendUrl = function_exists('getSchemeAndHttpHost')
            ? getSchemeAndHttpHost().'/confirm_resend.php'
            : '/confirm_resend.php';

        // The takeinvite mail-body strings are reused for invite
        // signup confirmation emails; this mirror is intentional
        // so existing locale dictionaries keep working.
        $takeinviteLang = $this->loadLang('takeinvite.php');
        $mailTwo = sprintf((string) ($takeinviteLang['mail_two'] ?? ''), $siteName);
        $mailFive = sprintf(
            (string) ($takeinviteLang['mail_five'] ?? ''),
            $siteName,
            $siteName,
            $reportMail,
            $siteName,
        );
        $ip = function_exists('getip') ? (string) getip() : (string) request()->ip();

        $body = ((string) ($lang['mail_one'] ?? '')).$usern.$mailTwo
            .'('.$email.')'.((string) ($lang['mail_three'] ?? '')).$ip
            .((string) ($lang['mail_four'] ?? ''))
            .'<b><a href="javascript:void(null)" onclick="window.open('.$confirmUrl.')">'
            .((string) ($lang['mail_this_link'] ?? 'this link')).' </a></b><br />'
            .$confirmUrl
            .((string) ($lang['mail_four_1'] ?? ''))
            .'<b><a href="javascript:void(null)" onclick="window.open('.$confirmResendUrl.')">'
            .((string) ($lang['mail_here'] ?? 'here')).'</a></b><br />'
            .$confirmResendUrl
            .'<br />'.$mailFive;

        sent_mail($email, $siteName, $siteEmail, $title, $body, 'signup', false, false, '');
    }

    /**
     * Same inline `failedloginscheck()` parity as `LoginController`.
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

    private function bark(string $msg): never
    {
        throw new BarkException($msg);
    }

    /**
     * Render the legacy bark envelope — same posture as
     * `TakeLoginController::renderBark()`.
     *
     * @param  array<string,string>  $lang
     */
    private function renderBark(string $msg, array $lang): Response
    {
        $heading = (string) ($lang['std_signup_failed'] ?? 'Signup failed');
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
