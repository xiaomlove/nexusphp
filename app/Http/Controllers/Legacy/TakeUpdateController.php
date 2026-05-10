<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
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
 *     legacy `user_can('staffmem', true)` chain raised
 *     `InsufficientPermissionException`, which the JSON-aware
 *     exception handler does not always render as a 403; we replace
 *     the gate with a direct `abort(403)` to keep the contract
 *     simple and avoid coupling the Phase 2 controller to the
 *     handler's render registration order).
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

        // `user_can('staffmem', false)` returns a bool without
        // throwing the legacy stderr-die exception, so we keep the
        // permission lookup logic (sysop / staff-member auth + tool
        // grants) but render the failure case as a plain 403 here.
        // Setting $CURUSER mirrors what the legacy bootstrap would
        // have done so `get_user_id()` resolves to the right id.
        $GLOBALS['CURUSER'] = $user->toLegacyArray();
        if ((int) $user->class < User::CLASS_STAFF_LEADER && ! user_can('staffmem')) {
            abort(403);
        }

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
