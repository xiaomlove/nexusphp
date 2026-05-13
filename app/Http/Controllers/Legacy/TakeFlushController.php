<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/takeflush.php` (deleted in the same PR).
 *
 * Phase 2 batch #5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `int_check($id, true);` validates `?id=<int>` (legacy
 *      `int_check` calls `stderr()` on a non-int).
 *   3. `get_user_class() >= UC_MODERATOR || $CURUSER['id'] == $id`
 *      → `DELETE FROM peers WHERE last_action < deadtime() AND
 *      userid = ?`, then `stderr('Success', "$n ghost torrents
 *      were sucessfully cleaned.")`.
 *   4. Else → `bark('You can only clean your own ghost torrents')`
 *      (`bark` is a local function that calls `stderr()`).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Missing / non-positive `id` → `abort(404)` (legacy
 *     `int_check` rendered HTTP 200, which the modern handler
 *     tightens to a real 404).
 *   - Authenticated, not the target user and below
 *     `User::CLASS_MODERATOR` → `abort(403)` (legacy `bark()`
 *     rendered HTTP 200; tightened for the same reason as
 *     `AllAgentsController` / `ClearCacheController`).
 *   - Self-flush or moderator+ → `DELETE FROM peers WHERE
 *     last_action < $deadtime AND userid = ?`, then render a small
 *     chrome-less "Success — N ghost torrents were sucessfully
 *     cleaned." HTML page (matching the
 *     `MoreSmiliesController` chrome-less precedent).
 *
 * The legacy URL was a `GET` that performed a DELETE — that is a
 * deliberate choice to keep the existing "flush my peers" links in
 * the userdetails page working without a form. Tightening to POST
 * is left to a later sweep; for now the migrated controller keeps
 * the same `Route::get(...)` verb.
 */
class TakeFlushController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        if ((int) $user->class < (int) User::CLASS_MODERATOR && (int) $user->id !== $id) {
            abort(403);
        }

        $deadtime = deadtime();
        $lastAction = date('Y-m-d H:i:s', $deadtime);
        $affected = NexusDB::table('peers')
            ->where('last_action', '<', $lastAction)
            ->where('userid', $id)
            ->delete();

        $body = sprintf(
            "<h2>Success</h2>\n<p>%d ghost torrents were sucessfully cleaned.</p>\n",
            $affected,
        );

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Success</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
