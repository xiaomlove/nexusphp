<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SendStaffMassMessageRequest;
use App\Jobs\SendStaffMassMessage;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Replacement for `public/takestaffmess.php` (deleted in the same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md` § "Common test pitfalls (Phase 2 lessons)".
 *
 * Original legacy flow (see `public/takestaffmess.php` in pre-migration
 * history):
 *   1. `dbconn();` + `loggedinorreturn();` → bootstraps legacy globals.
 *   2. `if ($_SERVER['REQUEST_METHOD'] != 'POST') stderr(...)` →
 *      reject GET via `stderr()` (HTTP 200 + body).
 *   3. `get_user_class() < UC_ADMINISTRATOR` → `stderr("Sorry", "Permission denied.")`.
 *   4. Read `sender` ∈ {self, system}; `senderId = 0` for system,
 *      else `(int) $CURUSER['id']`.
 *   5. Trim `msg`, reject empty.
 *   6. Validate every entry of `$_POST['clases']` (the typo'd field
 *      is never used; only `$_POST['classes']` reaches the SQL).
 *   7. `set_time_limit(300)`, build WHERE from `classes` IN-list,
 *      run `apply_filter('role_query_conditions', $conditions, $_POST)`,
 *      bail with `stderr('Error', 'No valid filter')` if empty.
 *   8. `while (true)` page through users at 10k per chunk, insert
 *      into `messages` until empty page.
 *   9. `header('Location: staffmess.php?sent=1')` after the loop
 *      drains (i.e. browser blocked for the full duration).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` → `abort(403)`
 *     (same hardening as `AddUserController` / `DonatedController` —
 *     the legacy `stderr()` body was HTTP 200, which is the wrong
 *     status for "Permission denied").
 *   - GET → 405 (the legacy `stderr()` was HTTP 200 with body, but
 *     callers don't depend on a body shape for the wrong-method
 *     branch; the route is `Route::post(...)` so Laravel produces
 *     405 by itself).
 *   - Missing / empty `msg` → 422 via FormRequest.
 *   - `apply_filter('role_query_conditions')` returns no WHERE
 *     alternatives → 422 with `"No valid filter"` (mirrors legacy
 *     `stderr('Error', 'No valid filter')`).
 *   - Happy path → 302 to `/staffmess.php?sent=1` (same URL the
 *     legacy script used; the form-render page in
 *     `public/staffmess.php` shows "The message has been sent." for
 *     `?sent=1`). The fan-out itself now runs in the
 *     `SendStaffMassMessage` queue job — so by the time the user
 *     sees the confirmation, the messages may still be in the
 *     queue. The staff-side UX is unchanged.
 *
 * Behaviour preserved from legacy:
 *   - `$_POST['clases']` (typo'd field) is dropped — it was never
 *     used in the SQL, only "validated" before being thrown away.
 *   - `apply_filter('role_query_conditions', $conditions, $_POST)`
 *     hook is preserved so any plugin that adds role-based WHERE
 *     alternatives keeps working.
 *   - No per-receiver `clear_inbox_count_cache($uid)` call — the
 *     legacy code didn't invalidate the user-side
 *     `_unread_message_count` Redis cache after fan-out (60s TTL
 *     catches it eventually). Preserved for parity; flagged as
 *     follow-up.
 */
class TakeStaffMessController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(SendStaffMassMessageRequest $request): RedirectResponse|JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            // Auth middleware guarantees a user; we re-assert so a
            // misconfigured route can't reach the admin gate without
            // one.
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $sender = $request->validated('sender') ?? 'self';
        $senderId = $sender === 'system' ? 0 : (int) $user->id;
        $subject = (string) ($request->validated('subject') ?? '');
        $msg = (string) $request->validated('msg');

        $classes = array_map('intval', (array) ($request->validated('classes') ?? []));
        $conditions = [];
        if (! empty($classes)) {
            $conditions[] = 'class IN ('.implode(', ', $classes).')';
        }
        // Plugin filter hook — preserve legacy `apply_filter` so
        // external plugins (e.g. role-based filters defined in
        // `plugins/` ) can extend or replace the WHERE clause.
        // Pass the raw POST payload (sans Laravel meta) so plugins
        // see the same shape they did before the migration.
        $conditions = apply_filter('role_query_conditions', $conditions, $request->except(['_token', '_method']));
        if (empty($conditions)) {
            // Mirrors legacy `stderr('Error', 'No valid filter')`,
            // upgraded from HTTP 200 to 422 (same upgrade
            // `SendStaffMessageRequest` does for empty subject /
            // body). The legacy script delivered an HTTP-200 HTML
            // body here, which is the wrong status for a
            // validation failure.
            return new JsonResponse(['message' => 'No valid filter'], 422);
        }

        SendStaffMassMessage::dispatch($senderId, $subject, $msg, $conditions);

        return new RedirectResponse('/staffmess.php?sent=1');
    }
}
