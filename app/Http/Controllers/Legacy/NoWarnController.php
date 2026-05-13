<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/nowarn.php` (deleted in the same PR).
 *
 * Phase 2 batch #6 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. If `$_POST['nowarned'] === 'nowarned'`:
 *      - `get_user_class() < UC_MODERATOR` → `stderr("Sorry",
 *        "Access denied.")`.
 *      - Empty `usernw`/`desact`/`delete` → `bark()` (renders HTTP
 *        200 with "You Must Select A User To Edit." and exits).
 *      - Non-empty `usernw[]` → `UPDATE users SET warned='no',
 *        warneduntil=NULL, modcomment = if(modcomment='', $stamp,
 *        concat_ws('\n', $stamp, modcomment)) WHERE id IN (...)`,
 *        where `$stamp = date('Y-m-d') . ' - Warning Removed By '
 *        . $CURUSER['username']`. NOTE: the legacy `modcomment`
 *        column was dropped in migration
 *        `2025_01_18_235747_drop_users_table_text_column.php`; the
 *        legacy script has been silently failing on every modern
 *        deploy since then. The migrated controller writes to
 *        `user_modify_logs` via `User::updateWithModComment()`,
 *        which is the canonical replacement.
 *      - Non-empty `desact[]` → `UPDATE users SET enabled='no'
 *        WHERE id IN (...)`.
 *      - `delete[]` was checked in the `empty()` guard but never
 *        actually processed by the legacy script — preserved as a
 *        no-op here.
 *   3. Always `header('Location: warned.php')` at the end.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_MODERATOR` →
 *     `abort(403)` (legacy `stderr()` returned HTTP 200, which we
 *     tighten — same rationale as the rest of Phase 2).
 *   - POST without `nowarned=nowarned` → 302 to `/warned.php`
 *     (matches the legacy "fall through" branch).
 *   - POST `nowarned=nowarned` with all selection arrays empty →
 *     422 with body "You Must Select A User To Edit." (legacy
 *     `bark()` was HTTP 200 with that string; the body stays so
 *     existing operators / smoke tests grepping for it still work).
 *   - POST `nowarned=nowarned` + `usernw[]` → bulk un-warn and
 *     append modcomment, then 302 to `/warned.php`.
 *   - POST `nowarned=nowarned` + `desact[]` → bulk disable, then
 *     302 to `/warned.php`.
 *   - Both arrays together are processed (matches legacy).
 *
 * The `usernw[]` / `desact[]` payload is normalized to a list of
 * positive integers — the legacy script trusted `$_POST['usernw']`
 * directly, which is unsafe; this normalization is the only
 * security tightening (no behavioural change for callers that
 * already send arrays of integer ids).
 */
class NoWarnController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        if ($request->input('nowarned') !== 'nowarned') {
            return redirect('/warned.php');
        }

        $usernw = $this->toIntIds($request->input('usernw'));
        $desact = $this->toIntIds($request->input('desact'));
        $delete = $this->toIntIds($request->input('delete'));

        if ($usernw === [] && $desact === [] && $delete === []) {
            return new Response('You Must Select A User To Edit.', 422);
        }

        if ($usernw !== []) {
            $modcomment = sprintf(
                '%s - Warning Removed By %s',
                date('Y-m-d'),
                (string) $user->username,
            );
            // `warned` and `warneduntil` are NOT in `User::$fillable`,
            // so an Eloquent `update()` skips them. Use the query
            // builder directly for the bulk un-warn, then write the
            // canonical mod-comment audit-log entries in the same
            // transaction — mirrors the shape of
            // `User::updateWithModComment()` for batched ids.
            NexusDB::transaction(function () use ($usernw, $modcomment) {
                NexusDB::table('users')
                    ->whereIn('id', $usernw)
                    ->update([
                        'warned' => 'no',
                        'warneduntil' => null,
                    ]);
                $now = date('Y-m-d H:i:s');
                $rows = array_map(
                    fn (int $uid): array => [
                        'user_id' => $uid,
                        'content' => $modcomment,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $usernw,
                );
                NexusDB::table('user_modify_logs')->insert($rows);
            });
        }

        if ($desact !== []) {
            NexusDB::table('users')
                ->whereIn('id', $desact)
                ->update(['enabled' => 'no']);
        }

        return redirect('/warned.php');
    }

    /**
     * Coerce `$_POST['usernw']` etc. (which legacy callers send as
     * `usernw[]` checkbox arrays of stringified integer ids) into a
     * list of positive integers.
     *
     * @return list<int>
     */
    private function toIntIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
