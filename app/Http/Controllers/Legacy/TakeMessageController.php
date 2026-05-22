<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takemessage.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * POST-only handler for the legacy "Send PM" / "Reply" / "Forward"
 * forms. Reads the form payload, performs validation + anti-flood +
 * recipient-acceptance gates, INSERTs into `messages`, and sends an
 * optional e-mail notification when the recipient opted in. Used by:
 *
 *   - `SendMessageController` (already migrated) — its
 *     `<form action="takemessage.php">` posts to this endpoint.
 *   - `public/messages.php` reply / forward forms — same.
 *
 * Original legacy flow (`public/takemessage.php`, 192 LOC):
 *   1. `loggedinorreturn()` bootstrap.
 *   2. POST verb required (legacy `stderr()` for GET).
 *   3. Forward branch (`?forward=1`): looks up the original message,
 *      requires the viewer to be sender or receiver, prepends a
 *      "-------- Original message from X --------" header in the
 *      recipient's locale.
 *   4. Standard branch: trims body, requires non-empty body.
 *   5. Anti-flood: 10-second window, bypassed for `staffmem` users.
 *   6. Recipient privacy gate: `acceptpms` ∈ {`yes`, `friends`,
 *      `no`}, plus `parked`-recipient gate. Bypassed for `staffmem`.
 *   7. INSERT via `App\Models\Message::add()`.
 *   8. UPDATE `users.last_pm = NOW()`.
 *   9. Optional SMTP notify when recipient's `notifs` includes
 *      `[pm]` and `emailnotify_smtp` is on.
 *  10. Forward-with-`delete=yes`: applies the same delete-or-move
 *      semantics the inbox view normally would.
 *  11. Redirect to `?returnto=`, or render a chrome-less success
 *      page when missing.
 *
 * Replacement contract:
 *   - URL stays `/takemessage.php` so the existing forms
 *     (rendered by `SendMessageController` and `public/messages.php`)
 *     keep posting unchanged.
 *   - Inside `auth.nexus:nexus-web` middleware. POST-only —
 *     non-POST returns 405 (legacy: `stderr()` HTTP 200, tightened).
 *   - POST is CSRF-exempt — the legacy forms have no `@csrf`
 *     field; see `App\Http\Middleware\VerifyCsrfToken::$except`.
 *   - Validation errors return 422 with a short message in the
 *     body (legacy: `stderr()` HTTP 200, tightened across Phase 2).
 *   - All branches preserve their observable side-effects (DB row
 *     insert, anti-flood bump, optional e-mail) bit-for-bit.
 */
class TakeMessageController extends Controller
{
    private const FLOOD_SECONDS = 10;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (! $request->isMethod('post')) {
            abort(405, 'POST required.');
        }

        $origMsgId = (int) $request->input('origmsg', 0);
        $body = trim((string) $request->input('body', ''));
        $isForward = (string) $request->input('forward', '') === '1';

        if ($isForward) {
            [$receiverId, $body] = $this->resolveForward($request, $viewer, $origMsgId, $body);
        } else {
            $receiverId = (int) $request->input('receiver', 0);
            if ($receiverId <= 0 || ($origMsgId > 0 && $origMsgId <= 0)) {
                abort(422, 'Invalid id.');
            }
            if ($body === '') {
                abort(422, 'Please enter something.');
            }
        }

        $save = ((string) $request->input('save', '')) === 'yes' ? 'yes' : 'no';
        $returnto = (string) $request->input('returnto', '');

        $isStaff = function_exists('user_can') && user_can('staffmem');
        $this->antiFlood($viewer, $isStaff);

        $recipient = NexusDB::table('users')
            ->where('id', $receiverId)
            ->first(['id', 'username', 'parked', 'email', 'acceptpms', 'notifs']);
        if ($recipient === null) {
            abort(422, 'User does not exist.');
        }

        $this->checkRecipientAcceptsPm($recipient, $viewer, $isStaff);

        $subject = trim((string) $request->input('subject', ''));

        $message = Message::add([
            'sender' => (int) $viewer->id,
            'receiver' => $receiverId,
            'msg' => $body,
            'subject' => $subject,
            'added' => now(),
            'saved' => $save,
            'location' => 1,
        ]);

        $this->cacheBust((int) $viewer->id);
        $msgId = (int) $message->id;

        NexusDB::statement(
            'UPDATE users SET last_pm = NOW() WHERE id = '.(int) $viewer->id,
        );

        $this->maybeSendEmailNotification($viewer, $recipient, $subject, $msgId);

        // Forward + delete-original branch: apply legacy
        // delete-or-move-out-of-inbox semantics on the original
        // message that triggered the forward.
        if ($origMsgId > 0 && (string) $request->input('delete', '') === 'yes') {
            $this->disposeOriginal($origMsgId, (int) $viewer->id);
        }

        if ($returnto !== '') {
            return redirect($returnto);
        }

        $body = '<h1>Message sent.</h1><p><a href="messages.php">Back to messages</a></p>';

        return new Response(
            "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Sent</title></head><body>{$body}</body></html>",
        );
    }

    /**
     * @return array{0:int,1:string} `[receiver_id, body]`
     */
    private function resolveForward(Request $request, $viewer, int $origMsgId, string $body): array
    {
        if ($origMsgId <= 0) {
            abort(422, 'Invalid id.');
        }
        $orig = NexusDB::table('messages')->where('id', $origMsgId)->first();
        if ($orig === null) {
            abort(403, 'No permission for forwarding.');
        }
        $orig = (array) $orig;
        if ((int) $orig['sender'] !== (int) $viewer->id
            && (int) $orig['receiver'] !== (int) $viewer->id) {
            abort(403, 'No permission for forwarding.');
        }

        $toName = trim((string) $request->input('to', ''));
        if ($toName === '') {
            abort(422, 'Must enter username.');
        }
        if (! function_exists('get_user_id_from_name')) {
            abort(500, 'get_user_id_from_name() unavailable.');
        }
        $receiverId = (int) get_user_id_from_name($toName);
        if ($receiverId <= 0) {
            abort(422, 'Unknown recipient.');
        }

        $locale = function_exists('get_user_locale')
            ? (string) get_user_locale($receiverId)
            : '';

        if ((int) $orig['sender'] === 0) {
            $origFrom = (string) nexus_trans('message.msg_system', [], $locale);
        } else {
            $origSenderName = function_exists('get_plain_username')
                ? (string) get_plain_username($orig['sender'])
                : '';
            $origFrom = '[url=userdetails.php?id='.(int) $orig['sender'].']'
                .$origSenderName.'[/url]';
        }

        $origMsgPrefix = (string) nexus_trans('message.msg_original_message_from', [], $locale);
        $forwarded = "-------- {$origMsgPrefix}{$origFrom} --------\n"
            .((string) $orig['msg'])."\n\n"
            .($body !== ''
                ? '-------- [url=userdetails.php?id='.(int) $viewer->id.']'
                  .((string) $viewer->username).'[/url][i] Wrote at '
                  .date('Y-m-d H:i:s').":[/i] --------\n".$body
                : '');

        return [$receiverId, $forwarded];
    }

    private function antiFlood($viewer, bool $isStaff): void
    {
        if ($isStaff) {
            return;
        }
        $lastPm = (string) ($viewer->last_pm ?? '0');
        $lastTs = strtotime($lastPm);
        if ($lastTs > time() - self::FLOOD_SECONDS) {
            $secs = self::FLOOD_SECONDS - (time() - $lastTs);
            abort(429, 'Message flooding denied. Wait '.$secs.'s before sending another PM.');
        }
    }

    private function checkRecipientAcceptsPm($recipient, $viewer, bool $isStaff): void
    {
        if ($isStaff) {
            return;
        }
        $recipient = (array) $recipient;

        if (($recipient['parked'] ?? 'no') === 'yes') {
            abort(403, 'Recipient account is parked.');
        }
        $accept = (string) ($recipient['acceptpms'] ?? 'yes');
        if ($accept === 'no') {
            abort(403, 'User blocks all PMs.');
        }
        if ($accept === 'yes') {
            // legacy "yes" actually means "accept by default but
            // block specific users". Check the `blocks` table.
            $blocked = NexusDB::table('blocks')
                ->where('userid', (int) $recipient['id'])
                ->where('blockid', (int) $viewer->id)
                ->exists();
            if ($blocked) {
                abort(403, 'User blocks your PMs.');
            }

            return;
        }
        if ($accept === 'friends') {
            $isFriend = NexusDB::table('friends')
                ->where('userid', (int) $recipient['id'])
                ->where('friendid', (int) $viewer->id)
                ->exists();
            if (! $isFriend) {
                abort(403, 'User accepts PMs from friends only.');
            }
        }
    }

    private function cacheBust(int $userId): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        $key = 'user_'.$userId.'_outbox_count';
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value($key);

            return;
        }
        NexusDB::cache_del($key);
    }

    private function maybeSendEmailNotification($viewer, $recipient, string $subject, int $msgId): void
    {
        $emailNotifyEnabled = (string) ($GLOBALS['emailnotify_smtp'] ?? 'no') === 'yes';
        $smtpType = (string) ($GLOBALS['smtptype'] ?? 'none');
        if (! $emailNotifyEnabled || $smtpType === 'none') {
            return;
        }
        $recipient = (array) $recipient;
        $notifs = (string) ($recipient['notifs'] ?? '');
        if (! str_contains($notifs, '[pm]')) {
            return;
        }
        if (! function_exists('sent_mail')) {
            return;
        }

        $username = trim((string) $viewer->username);
        $msgReceiver = trim((string) $recipient['username']);
        $siteName = Setting::getSiteName();
        $siteEmail = (string) ($GLOBALS['SITEEMAIL'] ?? '');
        $title = $siteName.' — PM from '.$username;
        $body = "Dear $msgReceiver,\n\n"
            ."You received a PM from $username with subject \"$subject\".\n\n"
            ."Visit /messages.php?action=viewmessage&id=$msgId to read it.\n";

        @sent_mail(
            $recipient['email'],
            $siteName,
            $siteEmail,
            $title,
            nl2br($body),
            'sendmessage',
            false,
            false,
            '',
        );
    }

    private function disposeOriginal(int $origMsgId, int $viewerId): void
    {
        $orig = NexusDB::table('messages')->where('id', $origMsgId)->first();
        if ($orig === null) {
            return;
        }
        $orig = (array) $orig;
        if ((int) $orig['receiver'] !== $viewerId) {
            return;
        }
        if (($orig['saved'] ?? 'no') === 'no') {
            NexusDB::statement('DELETE FROM messages WHERE id = '.$origMsgId);
        } elseif (($orig['saved'] ?? 'no') === 'yes') {
            NexusDB::statement(
                "UPDATE messages SET location = '0' WHERE id = ".$origMsgId,
            );
        }
    }
}
