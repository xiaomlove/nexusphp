<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\Permission\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\TorrentRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;
use Nexus\PTGen\PTGen;

/**
 * Replacement for `public/retriver.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. The legacy URL is
 * preserved verbatim ("retriver" is a long-standing typo of
 * "retriever" frozen into the public contract) so the existing
 * deep links keep working without a template change:
 *
 *   - `public/details.php:449,473` — `<a href="retriver.php?id=N&type=1&siteid=1">`
 *     and the post-cache-update `<a href="retriver.php?id=N&type=2&siteid=1">`
 *     IMDb refresh links rendered in torrent-detail pages.
 *   - `nexus/PTGen/PTGen.php:143` — the per-site `<a href="retriver.php?
 *     id=N&type=1&siteid=<site>">` links rendered inside ptgen sections
 *     (where `<site>` is `imdb` / `douban` / `bangumi`).
 *
 * Original legacy flow (`public/retriver.php`, 71 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `user_can('updateextinfo', true);`
 *      bootstrap; `permissiondenied()` rendered HTTP 200 for users below
 *      class `$AUTHORITY['updateextinfo']` (default `7`, Extreme User).
 *   2. Reads `?id=`, `?type=`, `?siteid=`. Any missing/zero-ish param
 *      → silent `exit()` (HTTP 200 empty body).
 *   3. Loads torrent row; missing → silent `exit()`.
 *   4. Dispatch on `$siteid`:
 *      - `=== 1`  → legacy IMDb refresh: `parse_imdb_id($row['url'])`
 *        and, on hit, `(new TorrentRepository)->fetchImdb($id)`,
 *        then `nexus_redirect('/details.php?id=$id')`. The outer
 *        `parse_imdb_id` guard is defensive: `fetchImdb()` itself
 *        early-returns on a missing/unparseable URL (see
 *        `TorrentRepository::fetchImdb` line 1144), so the legacy
 *        empty-on-no-imdb branch is unreachable in practice.
 *      - `'imdb'` / `'douban'` / `'bangumi'` (PTGen site keys) →
 *        `(new PTGen)->updateTorrentPtGen($id)` wrapped in a
 *        try/catch that just `do_log()`s the error, then redirect.
 *      - everything else → `exit('Error!')` (HTTP 200 plain-text).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `/login.php?returnto=...`.
 *   - Authenticated user without the `updateextinfo` permission →
 *     `abort(403)` (legacy `permissiondenied()` rendered HTTP 200;
 *     tightened, same as every other Phase 2 controller).
 *   - Missing/zero `?id` / `?type` / `?siteid` → `abort(404)`. The
 *     legacy silent `exit()` was a 200-empty body; the new contract
 *     surfaces the bad request explicitly.
 *   - Unknown torrent id → `abort(404)`.
 *   - Unknown `?siteid` → `abort(422, 'Unknown siteid.')` (legacy
 *     `exit('Error!')` was HTTP 200).
 *   - `?siteid=1` → `TorrentRepository::fetchImdb($id)` then 302 to
 *     `/details.php?id={id}`. Calling `fetchImdb()` directly is safe
 *     even when the torrent has no parseable IMDb URL — the
 *     repository early-returns with a `do_log()` and the redirect
 *     still fires, which is the same effective UX the legacy
 *     `if ($imdb_id) { ... redirect; }` produced for the only
 *     callsite that ever rendered the link (`details.php:449,473`,
 *     which only emits the link when the torrent has an imdb URL).
 *   - `?siteid=imdb|douban|bangumi` → `PTGen::updateTorrentPtGen($id)`
 *     wrapped in the same try/catch-and-log pattern, then 302 to
 *     `/details.php?id={id}`.
 *
 * `?type=N` is required to be a non-zero positive integer (matches
 * the legacy validation contract) but its value does not affect
 * dispatch — the legacy script read it and then never used it. The
 * two real callsites pass `type=1` (cache miss) and `type=2`
 * (cache update); we accept both transparently.
 *
 * The matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
class RetriverController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly TorrentRepository $torrents,
        private readonly PTGen $ptGen,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        if (! user_can(PermissionEnum::UPDATE_EXT_INFO->value, false, (int) $user->id)) {
            abort(403);
        }

        $id = (int) $request->query('id', 0);
        $type = (int) $request->query('type', 0);
        $siteid = trim((string) $request->query('siteid', ''));

        if ($id <= 0 || $type <= 0 || $siteid === '' || $siteid === '0') {
            abort(404);
        }

        $exists = NexusDB::table('torrents')->where('id', $id)->exists();
        if (! $exists) {
            abort(404);
        }

        switch ($siteid) {
            case '1':
                $this->torrents->fetchImdb($id);
                break;

            case PTGen::SITE_IMDB:
            case PTGen::SITE_DOUBAN:
            case PTGen::SITE_BANGUMI:
                try {
                    $this->ptGen->updateTorrentPtGen($id);
                } catch (\Exception $e) {
                    do_log($e->getMessage().', trace: '.$e->getTraceAsString(), 'error');
                }
                break;

            default:
                abort(422, 'Unknown siteid.');
        }

        return new RedirectResponse('/details.php?id='.$id);
    }
}
