<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/adredir.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md` § "Worked example".
 *
 * Original legacy flow (`public/adredir.php`, 40 LOC):
 *   1. `loggedinorreturn()` — redirect guests to `/login.php?returnto=...`.
 *   2. `parked()` — block parked users via `stderr()`.
 *   3. Reject when `$enablead_advertisement !== 'yes'` via `stderr()`.
 *   4. Read `$_GET['id']`, `$_GET['url']`; bail on empty.
 *   5. `SELECT COUNT(*) FROM advertisements WHERE id = ?`; bail if 0.
 *   6. If `adclickbonus_advertisement > 0` and the user has no prior
 *      click for this ad, award the bonus via
 *      `KPS('+', $bonus, $CURUSER['id'])` (a `users.seedbonus += $bonus`
 *      `NexusDB::raw` update — see `include/functions.php:1328`).
 *   7. `INSERT INTO adclicks (adid, userid, added)`.
 *   8. `header("Location: " . htmlspecialchars_decode(urldecode($_GET['url'])))`.
 *
 * Step 8 was an open redirect. `?url=` is decoded and passed straight
 * to `Location:` with no relationship to the ad being clicked, so an
 * attacker who knows any valid `advertisements.id` can build
 * `/adredir.php?id=<known>&url=<evil>` and have the site redirect to
 * `<evil>`. The same-origin appearance (link starts with the trusted
 * tracker's host) is what makes this a phishing primitive rather than
 * a curiosity.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...` (same URL the legacy
 *     `loggedinorreturn()` produced).
 *   - Parked user → 403 JSON `{"message":"..."}` (legacy `parked()`
 *     called `stderr()` which rendered HTTP 200 with the legacy chrome;
 *     tightened for the same reason as `AllAgentsController` and
 *     `ClearCacheController`).
 *   - Ad system disabled
 *     (`Setting::getByName('advertisement.enablead') !== 'yes'`)
 *     → 403 JSON.
 *   - Missing / non-positive `id` → 422 JSON.
 *   - Missing / empty `url` → 422 JSON.
 *   - `advertisements` row not found → 404 JSON.
 *   - `url` not authorised for this ad (i.e. not embedded in
 *     `advertisements.code`) → 403 JSON. This is the open-redirect
 *     fix; see {@see allowedUrlsFor()} for the matching strategy.
 *   - Happy path → record the click in `adclicks`, award the bonus
 *     once per user/ad pair if configured, 302 to the decoded URL.
 *
 * Open-redirect fix (no schema migration, no admanage changes):
 *
 *   `advertisements.code` is set exclusively by `public/admanage.php`
 *   (SYSOP-only). For `text` / `image` ad types — the only two that
 *   wire links through `adredir.php` at all — `admanage.php` writes a
 *   fixed-shape href:
 *
 *       <a href="adredir.php?id=ID&amp;url=URL_RAWURLENCODED"
 *          target="_blank">CONTENT</a>
 *
 *   The set of URLs we accept for `?id=ID` is therefore the set of
 *   URLs the SYSOP embedded in that row's `code` field. We extract
 *   them with the regex in {@see allowedUrlsFor()}, decode them the
 *   same way the legacy script decoded `$_GET['url']`
 *   (`htmlspecialchars_decode(rawurldecode(...))`), and compare for
 *   equality. Manually-edited `code` that doesn't contain the
 *   wrapping yields an empty allow-list — all requests for that ad
 *   then fall through to 403 (fail closed; admin re-saves the ad
 *   through `admanage.php` to restore the link).
 *
 *   This means existing production ads keep working without any data
 *   migration: the URL the controller redirects to is the same URL
 *   that the legacy script would have redirected to, modulo the
 *   open-redirect attack vector being closed.
 */
class AdRedirectController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->context->user();
        // `auth.nexus:nexus-web` guarantees we're authenticated; the
        // re-check protects against future route mis-wiring.
        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        if (($user->parked ?? 'no') === 'yes') {
            return new JsonResponse(['message' => 'Your account is parked.'], 403);
        }

        // `Setting::getByName()` reads the `settings` row directly
        // (no static cache), so toggling `advertisement.enablead`
        // from `admanage.php` is picked up on the next click without
        // a process restart. The legacy `get_setting()` helper would
        // also work but is process-static (Pitfall 3 in
        // `docs/migration-recipe.md`), which makes Feature tests for
        // the disabled-ad branch racy when the cache has already been
        // primed by an earlier test in the same run.
        $enablead = (string) Setting::getByName('advertisement.enablead', 'yes');
        if ($enablead !== 'yes') {
            return new JsonResponse(['message' => 'Ad system disabled.'], 403);
        }

        $rawId = $request->query('id');
        $id = is_numeric($rawId) ? (int) $rawId : 0;
        if ($id <= 0) {
            return new JsonResponse(['message' => 'Invalid ad id'], 422);
        }

        $requestedUrl = (string) $request->query('url', '');
        // Match the legacy `htmlspecialchars_decode(urldecode(...))`
        // chain: PHP has already URL-decoded the query value into
        // `$_GET['url']`, so the only step left is undoing the
        // `htmlspecialchars` that `admanage.php` applied before
        // rawurlencoding the link.
        $requestedUrl = htmlspecialchars_decode($requestedUrl);
        if ($requestedUrl === '') {
            return new JsonResponse(['message' => 'No redirect URL.'], 422);
        }

        $adRow = NexusDB::table('advertisements')
            ->where('id', $id)
            ->first(['id', 'code']);
        if ($adRow === null) {
            return new JsonResponse(['message' => 'Invalid ad id'], 404);
        }

        $allowed = $this->allowedUrlsFor((string) $adRow->code);
        if (! in_array($requestedUrl, $allowed, true)) {
            return new JsonResponse(
                ['message' => 'URL not authorized for this advertisement.'],
                403,
            );
        }

        $bonus = (float) Setting::getByName('advertisement.adclickbonus', 0);
        if ($bonus > 0) {
            $alreadyClicked = NexusDB::table('adclicks')
                ->where('adid', $id)
                ->where('userid', (int) $user->id)
                ->exists();
            if (! $alreadyClicked) {
                // Mirrors `KPS('+', $bonus, $CURUSER['id'])` —
                // `include/functions.php:1328`. The bonus is gated on
                // `$bonus_tweak` in the legacy helper; here we only
                // gate on `$bonus > 0` and rely on the SYSOP setting
                // `advertisement.adclickbonus` to express the same
                // intent. `bonus_tweak` is a separate toggle for the
                // wider points subsystem and is not specific to ad
                // clicks.
                NexusDB::table('users')
                    ->where('id', (int) $user->id)
                    ->update([
                        'seedbonus' => NexusDB::raw('seedbonus + '.$bonus),
                    ]);
            }
        }

        NexusDB::table('adclicks')->insert([
            'adid' => $id,
            'userid' => (int) $user->id,
            'added' => Carbon::now()->toDateTimeString(),
        ]);

        return new RedirectResponse($requestedUrl);
    }

    /**
     * Extract every URL that `admanage.php` embedded inside an
     * `adredir.php?id=<n>&url=<encoded>` href in the ad's `code`
     * column, decoded the same way the legacy script decoded
     * `$_GET['url']`.
     *
     * The regex matches both the canonical `&amp;url=` shape (which
     * `admanage.php` writes literally into `code`) and the bare
     * `&url=` shape (in case a downstream tool re-rendered the HTML
     * through an entity-decoder). The capture group stops at the
     * first character that cannot appear inside a `<a href="...">`
     * value (quote, whitespace, `>`, `&`).
     *
     * @return list<string>
     */
    private function allowedUrlsFor(string $code): array
    {
        if ($code === '') {
            return [];
        }

        if (! preg_match_all('/adredir\.php\?id=\d+&(?:amp;)?url=([^"\'\s>&]+)/i', $code, $matches)) {
            return [];
        }

        $urls = [];
        foreach ($matches[1] as $encoded) {
            // `admanage.php` did `rawurlencode(htmlspecialchars(...))`
            // when writing `code`; reverse in the same order.
            $urls[] = htmlspecialchars_decode(rawurldecode($encoded));
        }

        return array_values(array_unique($urls));
    }
}
