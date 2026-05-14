<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\ModelEventEnum;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/takeconfirm.php` (deleted in the same PR).
 *
 * Phase 2 batch #11 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (47 LOC):
 *   1. `dbconn();` + `loggedinorreturn();`.
 *   2. `$id = $_POST['id'] ?? $_GET['id']`; `int_check($id, true);`.
 *   3. Permission check: `$CURUSER['id'] == $id || user_can('viewinvite')`
 *      — else `stderr('Sorry', 'Permission denied!')`.
 *   4. `$email = unesc(htmlspecialchars(trim($_POST["email"])))`.
 *   5. If `!empty($_POST['conusr'])`:
 *      - `SELECT ... FROM users WHERE id IN ($conusr) AND status='pending' AND invited_by=$id`.
 *      - `fire_event(USER_UPDATED, $user)` for each matched row.
 *      - `UPDATE users SET status='confirmed', editsecret='' WHERE id IN (...)`.
 *      - else (no matching pending invitees): `stderr('Sorry', 'No buddy to confirm...')`.
 *   6. Build email body via `lang_takeconfirm.mail_content_1 / mail_content_two`,
 *      `sent_mail($email, $SITENAME, $SITEEMAIL, $title, $body, "invite confirm", false, false, '')`.
 *   7. `header("Location: invite.php?id=".htmlspecialchars($CURUSER['id']))`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Missing / non-positive `id` → 422.
 *   - Acting user is neither the inviter (`$user->id == $id`) nor has
 *     the `viewinvite` permission → 403. Legacy `stderr()` rendered
 *     HTTP 200; tightened in line with the rest of Phase 2.
 *   - No `conusr[]` selection (or no matching pending invitees) → 200
 *     chrome-less HTML with the legacy "no buddy to confirm" notice.
 *   - Happy path → flip `status='confirmed' / editsecret=''` for each
 *     matched user, fire the `USER_UPDATED` event per row (kept
 *     verbatim from the legacy script so listeners in `app/Listeners`
 *     keep firing), send the confirmation email (best-effort — a
 *     thrown `Throwable` is swallowed because the legacy
 *     `sent_mail(...)` helper used `do_log()` on failure and never
 *     surfaced errors), 302 redirect to `/invite.php?id=<user_id>`.
 *
 * `ToolRepository` is constructor-injected so the test suite can
 * bind a stub via the Laravel container without talking to SMTP.
 * The legacy script called `sent_mail()` directly — both helpers
 * route through `Setting::getFromDb('smtp')`, so the test posture
 * matches `MailtestController`.
 */
class TakeConfirmController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly ToolRepository $tools,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $rawId = $request->input('id', $request->query('id'));
        $id = is_numeric($rawId) ? (int) $rawId : 0;
        if ($id <= 0) {
            return new Response($this->wrap(
                'Confirm Invitees',
                $this->notice('Sorry', 'Invalid invite id.'),
            ), 422);
        }

        if (
            (int) $user->id !== $id
            && ! user_can('viewinvite', false, (int) $user->id)
        ) {
            abort(403);
        }

        $email = trim((string) $request->input('email', ''));

        $conusr = $request->input('conusr', []);
        if (! is_array($conusr) || $conusr === []) {
            return new Response($this->wrap(
                'Confirm Invitees',
                $this->noBuddyNotice((int) $user->id),
            ));
        }

        // Filter to integer ids only — `whereIn` would happily accept
        // strings, but mass-assignment of non-numeric values into
        // `users.id` is not something we want a logged-in user to
        // probe.
        $confusrIds = [];
        foreach ($conusr as $candidate) {
            if (is_numeric($candidate)) {
                $confusrIds[] = (int) $candidate;
            }
        }
        if ($confusrIds === []) {
            return new Response($this->wrap(
                'Confirm Invitees',
                $this->noBuddyNotice((int) $user->id),
            ));
        }

        $userList = User::query()
            ->whereIn('id', $confusrIds)
            ->where('status', User::STATUS_PENDING)
            ->where('invited_by', $id)
            ->get(User::$commonFields);

        if ($userList->isEmpty()) {
            return new Response($this->wrap(
                'Confirm Invitees',
                $this->noBuddyNotice((int) $user->id),
            ));
        }

        $uidArr = [];
        foreach ($userList as $invitee) {
            $uidArr[] = $invitee->id;
            fire_event(ModelEventEnum::USER_UPDATED, $invitee);
        }
        User::query()
            ->whereIn('id', $uidArr)
            ->update(['status' => User::STATUS_CONFIRMED, 'editsecret' => '']);

        if ($email !== '') {
            $this->sendConfirmationMail($email);
        }

        return new RedirectResponse('/invite.php?id='.((int) $user->id));
    }

    /**
     * Send the "your account has been confirmed" email. Best-effort:
     * the legacy `sent_mail()` helper used `do_log()` on failure and
     * never surfaced exceptions, so we swallow `Throwable` here for
     * the same reason. Phrasing is preserved verbatim from the
     * English `lang/en/lang_takeconfirm.php` defaults.
     */
    private function sendConfirmationMail(string $email): void
    {
        try {
            $siteName = (string) Setting::getSiteName();
            $baseUrl = getSchemeAndHttpHost();
            $subject = $siteName.' Account Confirmed';
            $body = <<<HTML
<p>Your account has been confirmed. You can now log in to {$siteName}.</p>
<p><a href="{$baseUrl}/login.php">Log in here</a> ({$baseUrl}/login.php)</p>
HTML;
            $this->tools->sendMail($email, $subject, $body, true);
        } catch (\Throwable $e) {
            do_log('[TAKECONFIRM] sendMail failed: '.$e->getMessage(), 'error');
        }
    }

    private function noBuddyNotice(int $inviterId): string
    {
        return $this->notice(
            'Sorry',
            'No buddy to confirm. '
            .'<a class="altlink" href="/invite.php?id='.$inviterId.'">Click here to go back.</a>',
        );
    }

    /**
     * The notice body. Title is HTML-escaped; the message is allowed
     * to contain anchor tags built by the controller itself (see
     * {@see noBuddyNotice()}) — there is no user-controlled HTML
     * making it into the message string at this layer.
     */
    private function notice(string $title, string $message): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<h1 align="center">'.$titleEsc.'</h1>'."\n"
            .'<p align="center">'.$message.'</p>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
