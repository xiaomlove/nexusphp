<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SendIncrementBulkRequest;
use App\Jobs\SendIncrementBulkBonus;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Replacement for `public/take-increment-bulk.php` (deleted in the
 * same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md`. The form-render half lives in
 * {@see IncrementBulkController}.
 *
 * Original legacy flow (`public/take-increment-bulk.php`, 88 LOC):
 *   1. `if ($_SERVER['REQUEST_METHOD'] != 'POST') stderr(...)`.
 *   2. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   3. `get_user_class() < UC_SYSOP` → `stderr("Sorry", "Permission denied.")`.
 *   4. Read sender ∈ {self, system}; `senderId = 0` for system.
 *   5. Validate `msg`, `amount`, `type`; numeric amount; type is in
 *      `$lang_incrementbulk['types']`.
 *   6. If `type === 'uploaded'` → `(int) getsize_int($amount, "G")`
 *      (GB → bytes).
 *   7. Build WHERE from `classes` IN-list + plugin
 *      `apply_filter('role_query_conditions', $conditions, $_POST)`,
 *      bail with `stderr('Error', 'No valid filter')` if empty.
 *   8. If `type === 'tmp_invites'` → require `duration > 0`.
 *   9. `set_time_limit(300)`, page through users at 2k per chunk:
 *      - cache id-list in Redis under `temporary_invite:<microtime>`;
 *      - if tmp_invites: shell out `php artisan invite:tmp <key>
 *        <duration> <amount>`;
 *      - else: `UPDATE users SET <type> = <type> + <amount>
 *        WHERE id IN (...)`;
 *      - bulk-insert messages.
 *  10. `header('Location: increment-bulk.php?sent=1&type=$type')`
 *      after the loop drains.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`
 *     (same hardening as `DonatedController` / `StaffMessController`).
 *   - GET → 405 (the legacy `stderr()` body was HTTP 200; the
 *     `Route::post(...)` registration in `routes/web.php` makes
 *     Laravel produce 405 by itself).
 *   - Missing / invalid input → 422 via
 *     `SendIncrementBulkRequest`. The legacy `stderr()` blurbs are
 *     preserved as the validator messages so admin UIs that scrape
 *     the response body keep matching the same strings.
 *   - `apply_filter('role_query_conditions')` returns no WHERE
 *     alternatives → 422 with `"No valid filter"`.
 *   - Happy path → 302 to `/increment-bulk.php?sent=1&type=<type>`
 *     (same URL the legacy script used; the form-render page in
 *     `public/increment-bulk.php` shows the legacy success banner
 *     for `?sent=1&type=...`). The fan-out itself runs in the
 *     {@see SendIncrementBulkBonus} queue job — so by the time the
 *     user sees the confirmation, the messages may still be in the
 *     queue. The staff-side UX is unchanged.
 *
 * Behaviour preserved from legacy:
 *   - The `uploaded` GB-to-bytes conversion uses the same
 *     `Format::bytesFromUnit($amount, 'G')` helper that the legacy
 *     script's `getsize_int` proxies to (see
 *     `tests/Unit/Support/FormatTest.php` for the pin-down test).
 *   - The plugin hook `apply_filter('role_query_conditions',
 *     $conditions, $_POST)` is preserved so any plugin that adds
 *     role-based WHERE alternatives keeps working. We pass the
 *     raw POST payload (sans Laravel meta) so plugins see the same
 *     shape they did before the migration.
 *   - For `tmp_invites`, the legacy script shelled out to
 *     `php artisan invite:tmp ...`. The Artisan command itself
 *     (`InviteAddTemporary`) just dispatches
 *     `GenerateTemporaryInvite::dispatch(...)`. We dispatch the
 *     job directly from the queue job, skipping the per-page
 *     `php` subprocess fork — same end state, fewer moving
 *     parts.
 *   - Messages are inserted with the same five columns the legacy
 *     loop used (sender, receiver, added, subject, msg); the rest
 *     fall back to schema defaults.
 *   - No per-receiver `clear_inbox_count_cache($uid)` call —
 *     same as legacy and `SendStaffMassMessage`.
 */
class TakeIncrementBulkController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(SendIncrementBulkRequest $request): RedirectResponse|JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $sender = $request->validated('sender') ?? 'self';
        $senderId = $sender === 'system' ? 0 : (int) $user->id;
        $subject = (string) ($request->validated('subject') ?? '');
        $msg = (string) $request->validated('msg');
        $type = (string) $request->validated('type');
        $duration = (int) ($request->validated('duration') ?? 0);

        $rawAmount = $request->validated('amount');
        if ($type === SendIncrementBulkRequest::TYPE_UPLOADED) {
            $amount = (int) Format::bytesFromUnit($rawAmount, 'G');
        } else {
            $amount = (int) $rawAmount;
        }

        $classes = array_map('intval', (array) ($request->validated('classes') ?? []));
        $conditions = [];
        if (! empty($classes)) {
            $conditions[] = 'class IN ('.implode(', ', $classes).')';
        }
        $conditions = apply_filter(
            'role_query_conditions',
            $conditions,
            $request->except(['_token', '_method']),
        );
        if (empty($conditions)) {
            return new JsonResponse(['message' => 'No valid filter'], 422);
        }

        SendIncrementBulkBonus::dispatch(
            senderId: $senderId,
            subject: $subject,
            msg: $msg,
            conditions: $conditions,
            type: $type,
            amount: $amount,
            duration: $duration,
        );

        return new RedirectResponse('/increment-bulk.php?sent=1&type='.$type);
    }
}
