<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SendStaffMessageRequest;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Repositories\MessageRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takecontact.php` (deleted in the same PR).
 *
 * Phase 2.3 of the legacy migration — second canonical "full
 * rewrite" page after `thanks.php`; see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md` § "Common test
 * pitfalls (Phase 2 lessons)".
 *
 * Original legacy flow (see `public/takecontact.php` in
 * pre-migration history):
 *   1. `dbconn();` + `loggedinorreturn();` → bootstraps legacy globals.
 *   2. Reject `GET` via `stderr()` (HTTP 200 + body).
 *   3. Trim `subject` / `body`; reject empties via `stderr()`.
 *   4. If `class < UC_MODERATOR`, throttle to one message / 60s
 *      keyed by `users.last_staffmsg`.
 *   5. INSERT into `staffmessages` (sender, added, msg, subject).
 *   6. UPDATE `users.last_staffmsg = NOW()` for the sender.
 *   7. Invalidate three Redis cache keys
 *      (`staff_message_count`, `staff_new_message_count`, plus
 *      `clear_staff_message_cache()` which mirrors the first two).
 *   8. If `$_POST['returnto']` was set → 302 to it.
 *   9. Else → 200 HTML "Message successfully sent" via legacy chrome.
 *
 * Replacement contract (this controller):
 *   - GET → 405 (legacy 200/`std_method` body had no actual feature
 *     value; bots looking for the GET path get a smaller rejection).
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Validation failures (missing subject/body, oversize subject)
 *     → 422 with a typed error body, courtesy of
 *     `SendStaffMessageRequest`.
 *   - Anti-flood hit (non-staff user posted within 60s of last
 *     `last_staffmsg`) → 429 with retry-after seconds in the body.
 *   - Success → 302 redirect. Default target is `/usercp.php`; an
 *     explicit `returnto` is honoured only if it parses as a
 *     same-host (or relative) URL — open-redirect protection that
 *     the legacy `htmlspecialchars($returnto)` did NOT have.
 *
 * The cache invalidations mirror the legacy three (`$Cache->delete_value`
 * x2 + `clear_staff_message_cache()`) by calling
 * `MessageRepository::updateStaffMessageCountCache(false)` — the
 * canonical Laravel-side helper that also drives the Filament admin
 * dashboard's unread counter.
 */
class TakeContactController extends Controller
{
    /**
     * Anti-flood window for non-staff senders, in seconds. Mirrors the
     * legacy `TIMENOW - 60` literal in `public/takecontact.php`.
     */
    private const FLOOD_WINDOW_SECONDS = 60;

    /**
     * `users.class` threshold above which the anti-flood window is
     * skipped — mirrors legacy `if (get_user_class() < UC_MODERATOR)`.
     * `User::CLASS_MODERATOR` is the string `'13'`; we compare as ints
     * so `'5' < '13'` doesn't accidentally pass (lexical compare).
     */
    private const STAFF_CLASS_THRESHOLD = 13;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(SendStaffMessageRequest $request): RedirectResponse|JsonResponse
    {
        $user = $this->context->user();
        // Auth middleware guarantees a user; we re-assert so a
        // misconfigured route can't reach the DB writes without one.
        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if ($flood = $this->floodResponse($user)) {
            return $flood;
        }

        $now = Carbon::now()->toDateTimeString();

        NexusDB::table('staffmessages')->insert([
            'sender' => (int) $user->id,
            'added' => $now,
            'msg' => (string) $request->validated('body'),
            'subject' => (string) $request->validated('subject'),
        ]);

        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['last_staffmsg' => NexusDB::raw('NOW()')]);

        // `MessageRepository::updateStaffMessageCountCache(false)`
        // wipes both `staff_message_count` and
        // `staff_new_message_count` — the same two keys the legacy
        // `$Cache->delete_value(...)` calls hit, plus the same
        // semantics as `clear_staff_message_cache()`.
        MessageRepository::updateStaffMessageCountCache(false);

        return new RedirectResponse($this->successTarget($request->validated('returnto')));
    }

    /**
     * Returns a 429 response if `$user` is below the staff threshold
     * AND posted within the flood window; otherwise null (caller
     * proceeds with the insert).
     */
    private function floodResponse(User $user): ?JsonResponse
    {
        if ((int) $user->class >= self::STAFF_CLASS_THRESHOLD) {
            return null;
        }

        $last = $user->last_staffmsg;
        if ($last === null || $last === '' || $last === '0000-00-00 00:00:00') {
            return null;
        }

        $lastTs = Carbon::parse($last)->timestamp;
        $elapsed = Carbon::now()->timestamp - $lastTs;
        if ($elapsed >= self::FLOOD_WINDOW_SECONDS) {
            return null;
        }

        $remaining = self::FLOOD_WINDOW_SECONDS - $elapsed;

        return new JsonResponse([
            'message' => "Anti-flood: please wait {$remaining} more second(s) before sending another staff message.",
            'retry_after' => $remaining,
        ], 429);
    }

    /**
     * Pick a safe redirect target. Default `/usercp.php` (the user
     * control panel that the staff-message form is reachable from
     * via the navigation rail). An explicit `returnto` is honoured
     * only if it parses as a relative path or a URL on the current
     * host — anything else falls back to the default to prevent the
     * legacy open-redirect.
     */
    private function successTarget(mixed $returnto): string
    {
        if (! is_string($returnto) || $returnto === '') {
            return '/usercp.php';
        }

        // Relative URL (no scheme, no leading `//`) → safe.
        if (! preg_match('#^(?:[a-z][a-z0-9+\-.]*:)?//#i', $returnto) && ! str_starts_with($returnto, '/\\')) {
            return $returnto;
        }

        // Absolute URL — only allow same-host navigation.
        $parsed = parse_url($returnto);
        if (is_array($parsed) && isset($parsed['host']) && $parsed['host'] === request()->getHost()) {
            return $returnto;
        }

        return '/usercp.php';
    }
}
