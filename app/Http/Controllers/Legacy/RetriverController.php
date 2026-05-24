<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\TorrentRepository;
use App\Support\Imdb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;
use Nexus\PTGen\PTGen;

/**
 * Replacement for `public/retriver.php` (deleted in the same PR).
 *
 * Phase 2 migration. The legacy script was a 71-LOC AJAX/redirect
 * endpoint that refreshes external metadata (IMDb / Douban / Bangumi)
 * for a torrent. It is linked from `public/details.php` (the "click
 * here to retrieve/update" link next to IMDb blocks) and from the
 * formatted PTGen output in `nexus/PTGen/PTGen.php`.
 *
 * Original legacy flow:
 *   1. `require "../include/bittorrent.php"; dbconn();` bootstrap.
 *   2. `loggedinorreturn();` — redirect to login if not authenticated.
 *   3. `user_can('updateextinfo', true);` — permission gate
 *      (default: class 7 / Extreme User, configurable via
 *      `$AUTHORITY['updateextinfo']` in `config/allconfig.php`).
 *   4. Parse `?id`, `?type`, `?siteid` from query string.
 *   5. Look up `torrents` row by id, exit silently if not found.
 *   6. Switch on `$siteid`:
 *      - `1` (legacy IMDb shape): parse IMDb id from `torrents.url`,
 *        call `TorrentRepository::fetchImdb($id)`, redirect to
 *        `/details.php?id=$id`.
 *      - PTGen sites (`imdb` / `douban` / `bangumi`): call
 *        `PTGen::updateTorrentPtGen($id)`, redirect to
 *        `/details.php?id=$id`.
 *      - Default: exit with "Error!" text.
 *
 * Replacement contract (this controller):
 *   - Authenticated users only (matches `loggedinorreturn()`).
 *   - `updateextinfo` permission required (matches `user_can(..., true)`).
 *   - Same three query params: `id`, `type`, `siteid`.
 *   - On success: 302 redirect to `/details.php?id={id}`.
 *   - On missing/invalid params or missing torrent: empty 200 response
 *     (matches the legacy `exit()` behaviour — legacy callers do not
 *     parse the response body on failure, the page simply stays put
 *     if the AJAX/redirect does not fire).
 *   - On PTGen exception: log the error and redirect anyway (matches
 *     legacy behaviour of catching, logging, then redirecting).
 */
class RetriverController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        if (! user_can('updateextinfo', false, (int) $user->id)) {
            abort(403);
        }

        $id = (int) $request->query('id', 0);
        $type = (int) $request->query('type', 0);
        $siteid = $request->query('siteid', '');

        // Legacy validation: all three params must be truthy.
        if (! $id || ! $type || $siteid === '' || $siteid === '0') {
            return new Response('', 200);
        }

        $row = NexusDB::table('torrents')->where('id', $id)->first();
        if ($row === null) {
            return new Response('', 200);
        }
        $rowArr = (array) $row;

        switch ($siteid) {
            case '1':
                $imdbId = Imdb::parseId($rowArr['url'] ?? '');
                if ($imdbId) {
                    $torrentRep = new TorrentRepository;
                    $torrentRep->fetchImdb($id);

                    return redirect("/details.php?id={$id}");
                }

                return new Response('', 200);

            case PTGen::SITE_IMDB:
            case PTGen::SITE_DOUBAN:
            case PTGen::SITE_BANGUMI:
                $ptGen = new PTGen;
                try {
                    $ptGen->updateTorrentPtGen($id);
                } catch (\Exception $e) {
                    $log = $e->getMessage().', trace: '.$e->getTraceAsString();
                    do_log($log, 'error');
                }

                return redirect("/details.php?id={$id}");

            default:
                return new Response('Error!', 200);
        }
    }
}
