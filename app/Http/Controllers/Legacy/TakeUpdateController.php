<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takeupdate.php` (deleted in the same PR).
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` + `user_can('staffmem', true)`
 *      bootstrap (auth + permission gate).
 *   2. POST body must contain `delreport` (an array of report ids) and
 *      either `setdealt` or `delete` flag.
 *   3. Either marks the rows as dealt-with (and stamps the staff
 *      member who handled them) or hard-deletes them.
 *   4. Invalidates the staff-side report counters in the cache.
 *   5. Redirects to `/reports.php`.
 *
 * Replacement contract:
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated but lacking `staffmem` permission → 403 (the
 *     `user_can('staffmem', true)` call throws
 *     `InsufficientPermissionException` outside the legacy
 *     stderr-die flow; Laravel's exception handler renders the 403).
 *   - Empty / non-array `delreport` → redirect back to `/reports.php`
 *     with a flash error (preserves the legacy "go back" behaviour
 *     without rendering the legacy stderr template).
 *   - Happy path → 302 to `/reports.php`.
 *
 * `$Cache->delete_value('staff_*')` from the legacy script is
 * mirrored via `NexusDB::cache_del()`, which clears the same keys
 * across language prefixes.
 */
class TakeUpdateController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return redirect('/login.php');
        }

        // `user_can()` reads the global $CURUSER for `get_user_id()`.
        // The Laravel auth middleware does not populate it; mirror
        // what the legacy `dbconn()` / `loggedinorreturn()` chain
        // would have done so the permission check sees the right id.
        $GLOBALS['CURUSER'] = $user->toLegacyArray();
        user_can('staffmem', true);

        $reports = $request->input('delreport');
        if (! is_array($reports) || $reports === []) {
            return redirect('/reports.php')
                ->withErrors(['delreport' => 'Select at least one record.']);
        }

        $reportIds = array_values(array_filter(
            array_map('intval', $reports),
            fn (int $id): bool => $id > 0,
        ));
        if ($reportIds === []) {
            return redirect('/reports.php')
                ->withErrors(['delreport' => 'Select at least one record.']);
        }

        if ($request->filled('setdealt')) {
            NexusDB::table('reports')
                ->where('dealtwith', 0)
                ->whereIn('id', $reportIds)
                ->update([
                    'dealtwith' => 1,
                    'dealtby' => (int) $user->id,
                ]);
            NexusDB::cache_del('staff_new_report_count');
        } elseif ($request->filled('delete')) {
            NexusDB::table('reports')->whereIn('id', $reportIds)->delete();
            NexusDB::cache_del('staff_new_report_count');
            NexusDB::cache_del('staff_report_count');
        }

        return redirect('/reports.php');
    }
}
