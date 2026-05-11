<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/confirmemail.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Original legacy flow:
 *
 *   1. Parse `$_SERVER['PATH_INFO']` against
 *      `^/(\d+)/([\w]{32})/(.+)$`; on miss → `httperr()` (404).
 *   2. Fetch `users.editsecret` for `$id`; on miss → 404.
 *   3. Compute `md5(hash_pad($editsecret) . $email . hash_pad($editsecret))`;
 *      if the URL md5 doesn't match → 404.
 *   4. UPDATE the user: blank `editsecret`, set `email = $newEmail`,
 *      conditional on `editsecret` still matching the row we read
 *      (idempotent / TOCTOU-safe).
 *   5. Redirect to `/usercp.php?action=security&type=saved`.
 *
 * Route shape: the legacy URL is `/confirmemail.php/<id>/<md5>/<email>`
 * — three positional path segments. Laravel parameterises them; the
 * route declaration in `routes/web.php` uses the same shape.
 *
 * Migrated contract:
 *   - Missing / non-numeric id → 404.
 *   - Wrong md5 / non-existent user → 404.
 *   - Race lost (editsecret rotated between fetch and update) → 404.
 *   - Success → 302 to `/usercp.php?action=security&type=saved`.
 *
 * No auth middleware: the URL is signed (md5 over editsecret + email),
 * so a valid hit is necessarily from the user themselves.
 */
class ConfirmEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $md5, string $email): RedirectResponse
    {
        $userId = (int) $id;
        if ($userId <= 0 || strlen($md5) !== 32) {
            abort(404);
        }

        // urldecode mirrors the legacy `$email = urldecode($matches[3])`;
        // the email arrives URL-encoded because it contains an `@`.
        $newEmail = urldecode($email);

        $editsecret = (string) (NexusDB::table('users')
            ->where('id', $userId)
            ->value('editsecret') ?? '');
        if ($editsecret === '') {
            abort(404);
        }

        $sec = hash_pad($editsecret);
        if (preg_match('/^ *$/s', $sec) === 1) {
            abort(404);
        }
        if ($md5 !== md5($sec.$newEmail.$sec)) {
            abort(404);
        }

        $affected = NexusDB::table('users')
            ->where('id', $userId)
            ->where('editsecret', $editsecret)
            ->update([
                'editsecret' => '',
                'email' => $newEmail,
            ]);
        if ($affected === 0) {
            abort(404);
        }

        return redirect('/usercp.php?action=security&type=saved');
    }
}
