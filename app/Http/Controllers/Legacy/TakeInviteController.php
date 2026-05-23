<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;

/**
 * Replacement for `public/takeinvite.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. Follow-up to PR #301
 * (`InviteController`, which migrated the read-only `/invite.php`
 * surface).
 *
 * POST-only "send invitation" write-handler. Receives a
 * `<form action="/takeinvite.php?id={id}">` POST submitted from the
 * `?type=new` form rendered by `InviteController::renderNewForm()`.
 *
 * Faithful 1:1 transcription of the legacy 146-LOC script preserving
 * every side effect:
 *
 *   1. Acquire `takeinvite:<inviter-id>` Redis lock for 10s; lock-busy
 *      → bark "do not repeat" (legacy parity for double-submit).
 *   2. `registration_check('invitesystem', true, false)` — exits via
 *      legacy `stderr()` if the invite system is disabled or the user
 *      cap is reached. Same posture as the legacy script.
 *   3. `UserRepository::getInviteBtnText($id)` — re-checks the
 *      sender's invite eligibility (sendinvite class, ratio, quota).
 *      Throws `NexusException` → bark with the exception's localised
 *      message.
 *   4. Validate POST: `email` (non-empty / well-formed / not banned /
 *      domain allowed), `body` (non-empty after `strip_tags+nl2br`),
 *      and `pre_register_username` when `system.is_invite_pre_email_and_username='yes'`
 *      (length, charset, uniqueness).
 *   5. Reject if the email is already in `users.email` or already
 *      sitting unredeemed in `invites.invitee`.
 *   6. Branch on `?hash`:
 *      - `permanent`  → freshly md5'd hash + on success INSERT a
 *        new `invites` row and decrement `users.invites`.
 *      - any other   → look up `invites WHERE inviter=$id AND
 *        hash=?`, reject when missing / already consumed / expired,
 *        UPDATE the row with the new invitee on success.
 *   7. Compose the localised invitation email (using the legacy
 *      `lang_takeinvite['mail_*']` strings — Phase 5 will sweep) and
 *      hand off to the global `sent_mail()` helper.
 *   8. INSERT/UPDATE only when `sent_mail()` reports success — same
 *      legacy contract; mail-failure leaves DB unchanged.
 *   9. Release the lock and 302 to `/invite.php?id=<id>&sent=1`.
 *
 * Bark / exit transformation
 * --------------------------
 * The legacy `bark($msg)` helper (defined inline in the script)
 * called `stdhead()` + `stdmsg()` + `stdfoot()` + `exit;`. Inside the
 * Laravel pipeline, `exit` mid-render terminates the FPM worker
 * before middleware can post-process. We reuse the
 * `App\Http\Controllers\Legacy\BarkException` introduced by PR #302
 * (`TakeUploadController` / `TakeEditController`): every legacy
 * `bark()` call site throws the exception, and `__invoke` catches it
 * and renders the same `stdhead()` + `stdmsg()` + `stdfoot()` envelope
 * captured into a `Response`.
 *
 * URL preservation
 * ----------------
 * The URL stays `/takeinvite.php` so that
 * `app/Http/Controllers/Legacy/InviteController.php:239` (the
 * `<form action="/takeinvite.php?id=...">` action) keeps posting to
 * the same endpoint without template changes.
 *
 * The legacy `lang/<locale>/lang_takeinvite.php` files (19 locales)
 * are intentionally KEPT live: the controller loads them on every
 * request, and `public/takesignup.php:227-228` still references
 * `$lang_takeinvite['mail_two']` / `mail_five` for its registration-
 * confirmation email. Phase 5 will retranslate centrally.
 *
 * CSRF: `/takeinvite.php` is exempt — the legacy form has no `@csrf`
 * field. See `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
class TakeInviteController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $userRep,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $lang = $this->loadLang('takeinvite.php');

        // 10s flood lock keyed by inviter id. Matches the legacy
        // `new NexusLock("takeinvite:$id", 10)` call. The lock is
        // released only on the success path inside handle(); a bark
        // path leaves the 10s auto-expiry to do its job — matches the
        // legacy bark()→exit semantics that never released the lock.
        $lock = new NexusLock("takeinvite:{$user->id}", 10);
        if (! $lock->acquire()) {
            return $this->renderBark((string) nexus_trans('nexus.do_not_repeat'), $lang);
        }

        try {
            return $this->handle($request, $user, $lang, $lock);
        } catch (BarkException $e) {
            return $this->renderBark($e->getMessage(), $lang);
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function handle(Request $request, User $user, array $lang, NexusLock $lock): RedirectResponse
    {
        // registration_check() may stderr-exit if the invite system
        // is disabled or the user cap is reached. Match legacy
        // parity — the rendered legacy chrome page is the contract.
        if (function_exists('registration_check')) {
            registration_check('invitesystem', true, false);
        }

        // Re-check invite-eligibility (sendinvite perm + ratio +
        // quota) — same call the legacy script makes. The repository
        // throws `NexusException` with a localised message when the
        // sender is not allowed to invite right now.
        try {
            $this->userRep->getInviteBtnText((int) $user->id);
        } catch (\Throwable $e) {
            $this->bark($e->getMessage());
        }

        // ─── Email ──────────────────────────────────────────────────────
        $emailRaw = (string) ($_POST['email'] ?? $request->input('email', ''));
        $email = function_exists('unesc')
            ? unesc(htmlspecialchars(trim($emailRaw)))
            : htmlspecialchars(trim($emailRaw));
        if (function_exists('safe_email')) {
            $email = (string) safe_email($email);
        }

        $preRegisterUsername = (string) ($_POST['pre_register_username']
            ?? $request->input('pre_register_username', ''));
        $isPreRegister = get_setting('system.is_invite_pre_email_and_username') === 'yes';

        if (strlen($preRegisterUsername) > 12) {
            $this->bark((string) ($lang['std_username_too_long']
                ?? 'Sorry, the username is too long (up to 12 characters)'));
        }
        if ($email === '' || $email === false) {
            $this->bark((string) ($lang['std_must_enter_email']
                ?? 'You must enter an email address!'));
        }
        if (function_exists('check_email') && ! check_email($email)) {
            $this->bark((string) ($lang['std_invalid_email_address']
                ?? 'Invalid email address!'));
        }
        if (function_exists('EmailBanned') && EmailBanned($email)) {
            $this->bark((string) ($lang['std_email_address_banned']
                ?? 'This email address is banned!'));
        }
        if (function_exists('EmailAllowed') && ! EmailAllowed($email)) {
            $allowed = function_exists('allowedemails') ? (string) allowedemails() : '';
            $this->bark(((string) ($lang['std_wrong_email_address_domains']
                ?? 'This email address is not allowed! Allowed domains: ')).$allowed);
        }

        // ─── Personal message ───────────────────────────────────────────
        $rawBody = (string) ($_POST['body'] ?? $request->input('body', ''));
        $body = str_replace('<br />', '<br />', nl2br(trim(strip_tags($rawBody))));
        if ($body === '') {
            $this->bark((string) ($lang['std_must_enter_personal_message']
                ?? 'Please add a personal message.'));
        }

        // ─── Pre-register username (when enabled) ───────────────────────
        if ($isPreRegister) {
            if ($preRegisterUsername === '') {
                $this->bark((string) nexus_trans('invite.require_pre_register_username'));
            }
            if (function_exists('validusername') && ! validusername($preRegisterUsername)) {
                $this->bark((string) nexus_trans(
                    'user.username_invalid',
                    ['username' => $preRegisterUsername],
                ));
            }
            $exists = User::query()->where('username', $preRegisterUsername)->exists();
            if ($exists) {
                $this->bark((string) nexus_trans(
                    'user.username_already_exists',
                    ['username' => $preRegisterUsername],
                ));
            }
        }

        // ─── Email already used? ────────────────────────────────────────
        $emailInUse = (int) NexusDB::table('users')->where('email', (string) $email)->count();
        if ($emailInUse > 0) {
            $this->bark(
                (string) ($lang['std_email_address'] ?? 'The email address ')
                .htmlspecialchars((string) $email)
                .(string) ($lang['std_is_in_use'] ?? ' is already in use.'),
            );
        }
        $inviteSent = (int) NexusDB::table('invites')->where('invitee', (string) $email)->count();
        if ($inviteSent > 0) {
            $this->bark(
                (string) ($lang['std_invitation_already_sent_to']
                    ?? 'Invitation failed! The email address ')
                .htmlspecialchars((string) $email)
                .(string) ($lang['std_await_user_registeration']
                    ?? ' has already received an invitation. Please wait for the user to register.'),
            );
        }

        $username = (string) ($user->username ?? '');

        // ─── Hash branch ────────────────────────────────────────────────
        $rawHash = (string) ($_POST['hash'] ?? $request->input('hash', ''));
        if ($rawHash === '') {
            // English `lang_takeinvite.php` is missing this key; chs/cht
            // do supply it. Fall back to a sensible English default.
            $this->bark((string) ($lang['std_must_select_invite']
                ?? 'Please select an invite to consume.'));
        }

        /** @var Invite|null $hashRecord */
        $hashRecord = null;
        if ($rawHash === 'permanent') {
            $passhash = (string) ($user->passhash ?? '');
            $hash = md5(mt_rand(1, 10000).$username.((string) time()).$passhash);
        } else {
            $hashRecord = Invite::query()
                ->where('inviter', (int) $user->id)
                ->where('hash', $rawHash)
                ->first();
            if ($hashRecord === null) {
                $this->bark((string) ($lang['hash_not_exists']
                    ?? 'Invitation hash not found.'));
            }
            if ((string) $hashRecord->invitee !== '') {
                $this->bark('hash '.((string) ($lang['std_is_in_use']
                    ?? ' is already in use.')));
            }
            if ($hashRecord->expired_at !== null && $hashRecord->expired_at->lt(now())) {
                $this->bark((string) ($lang['hash_expired']
                    ?? 'Invitation hash has expired.'));
            }
            $hash = $rawHash;
        }

        // ─── Compose mail ───────────────────────────────────────────────
        $siteName = (string) Setting::getSiteName();
        $globalSiteName = (string) ($GLOBALS['SITENAME'] ?? $siteName);
        $globalSiteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $globalReportMail = (string) ($GLOBALS['REPORTMAIL'] ?? '');
        $inviteTimeout = (string) ($GLOBALS['invite_timeout'] ?? '');

        $title = $globalSiteName.((string) ($lang['mail_tilte'] ?? ' Invitation'));
        $signupUrl = function_exists('getSchemeAndHttpHost')
            ? getSchemeAndHttpHost()."/signup.php?type=invite&invitenumber={$hash}"
            : '/signup.php?type=invite&invitenumber='.$hash;

        // sprintf is preserved verbatim from the legacy script even
        // though some locales' templates have fewer %s placeholders
        // than args supplied — PHP's sprintf is tolerant of surplus
        // args, and breaking the bit-for-bit contract would risk
        // surprising translators.
        $mailTwo = @sprintf((string) ($lang['mail_two'] ?? ''), $siteName, $siteName);
        $mailFour = @sprintf((string) ($lang['mail_four'] ?? ''), $siteName);
        $mailSix = @sprintf((string) ($lang['mail_six'] ?? ''), $globalReportMail, $siteName);
        $here = (string) ($lang['mail_here'] ?? 'HERE');
        $mailOne = (string) ($lang['mail_one'] ?? 'Hi,<br />You have been invited by ');
        $mailThree = (string) ($lang['mail_three'] ?? "You'll need to accept the invitation within ");
        $mailFive = (string) ($lang['mail_five'] ?? ':');

        $message = <<<EOD
{$mailOne}{$username}{$mailTwo}
<b><a href="javascript:void(null)" onclick="window.open({$signupUrl})">{$here}</a></b><br />
{$signupUrl}
<br />{$mailThree}{$inviteTimeout}{$mailFour}{$username}{$mailFive}<br />
{$body}
<br /><br />{$mailSix}
EOD;

        $sendResult = function_exists('sent_mail')
            ? sent_mail(
                $email, $globalSiteName, $globalSiteEmail, $title,
                $message, 'invitesignup', false, false, '',
            )
            : true;

        // The legacy script wrote to the DB only when `sent_mail()`
        // reported success. Mail failure leaves DB state untouched —
        // we preserve the same contract.
        if ($sendResult === true) {
            if ($hashRecord !== null) {
                $update = [
                    'invitee' => $email,
                    'time_invited' => now(),
                    'valid' => 1,
                ];
                if ($isPreRegister) {
                    $update['pre_register_email'] = $email;
                    $update['pre_register_username'] = $preRegisterUsername;
                }
                $hashRecord->update($update);
            } else {
                $insert = [
                    'inviter' => (int) $user->id,
                    'invitee' => $email,
                    'hash' => $hash,
                    'time_invited' => now()->toDateTimeString(),
                ];
                if ($isPreRegister) {
                    $insert['pre_register_email'] = $email;
                    $insert['pre_register_username'] = $preRegisterUsername;
                }
                Invite::query()->insert($insert);
                NexusDB::table('users')
                    ->where('id', (int) $user->id)
                    ->update(['invites' => NexusDB::raw('invites - 1')]);
            }
        }

        $lock->release();

        return new RedirectResponse('/invite.php?id='.((int) $user->id).'&sent=1');
    }

    private function bark(string $msg): never
    {
        throw new BarkException($msg);
    }

    /**
     * Render the legacy `bark()` envelope: `stdhead()` + `stdmsg()` +
     * `stdfoot()`, captured into a `Response` so middleware can run.
     * Falls back to a chrome-less notice if the legacy helpers are
     * not bootstrapped (the test suite path).
     *
     * @param  array<string,string>  $lang
     */
    private function renderBark(string $msg, array $lang): Response
    {
        $heading = (string) ($lang['head_invitation_failed'] ?? 'Invitation failed!');
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

        // Fallback chrome-less envelope (same shape as
        // `TakeConfirmController::notice()`).
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
