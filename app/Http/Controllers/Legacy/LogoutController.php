<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replacement for `public/logout.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — the canonical "worked example"
 * from `docs/migration-recipe.md`. The legacy script was:
 *
 *     dbconn();
 *     logoutcookie();
 *     nexus_redirect("/");
 *
 * where `logoutcookie()` (include/functions.php:3428) clears the
 * legacy `c_secure_pass` cookie via `setcookie(..., expires=0x7fffffff)`
 * — actually a far-future expiry, NOT an immediate clear, but the
 * empty value causes the legacy auth check to fall through anyway.
 *
 * The migrated controller keeps the same observable contract:
 *   - Drops a `c_secure_pass` cookie with empty value + past expiry
 *     (a proper clear, slightly stronger than the legacy script).
 *   - Redirects to `/`.
 *
 * No auth middleware: `/logout.php` was reachable as a guest in the
 * legacy code (it just no-ops cookie-clearing for an already-clean
 * client) and we preserve that.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Drop the legacy auth cookie. Negative `minutes` arg expires
        // it immediately on the client. Path `/` matches the cookie
        // attributes used by `logoutcookie()` in the legacy code.
        return redirect('/')->withCookie(cookie('c_secure_pass', '', -1, '/'));
    }
}
