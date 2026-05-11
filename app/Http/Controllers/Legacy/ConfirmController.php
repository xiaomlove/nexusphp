<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\ModelEventEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/confirm.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. The legacy script confirmed a
 * pending user account from a signup email's confirmation link:
 *
 *   1. Parse `?id=<int>&secret=<md5>` from the GET request.
 *   2. Fetch the user row; if missing → 404 (`httperr()`).
 *   3. If `status != 'pending'` → 302 `/ok.php?type=confirmed`.
 *   4. Compute `md5(hash_pad($users.secret))`; if it does not match
 *      `secret` → 404.
 *   5. UPDATE `users.status = 'confirmed'`, blank `editsecret`,
 *      conditional on `status='pending'` (idempotent / TOCTOU-safe).
 *   6. `publish_model_event(USER_UPDATED, $id)` so peripheral caches
 *      drop stale "pending" status.
 *   7. `logincookie($id, $row['auth_key'])` — mints the auth cookie
 *      so the user is logged in immediately on the next request.
 *   8. Redirect to `/ok.php?type=confirm`.
 *
 * The signed URL is the only auth token (legacy script has no
 * session check) — keeping that contract here means the migrated
 * controller does not sit behind `auth.nexus` middleware. A miss
 * always returns 404 so the URL doesn't double as a user-existence
 * oracle.
 */
class ConfirmController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        $secret = (string) $request->query('secret', '');

        if ($id <= 0) {
            abort(404);
        }

        $row = NexusDB::table('users')
            ->where('id', $id)
            ->select(['secret', 'auth_key', 'status'])
            ->first();
        if ($row === null) {
            abort(404);
        }
        $row = (array) $row;

        if ($row['status'] !== 'pending') {
            return redirect('/ok.php?type=confirmed');
        }

        $expected = md5(hash_pad((string) $row['secret']));
        if (! hash_equals($expected, $secret)) {
            abort(404);
        }

        $updated = NexusDB::table('users')
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'confirmed',
                'editsecret' => '',
            ]);
        if ($updated === 0) {
            abort(404);
        }

        publish_model_event(ModelEventEnum::USER_UPDATED, $id);
        logincookie($id, (string) $row['auth_key']);

        return redirect('/ok.php?type=confirm');
    }
}
