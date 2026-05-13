<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/allagents.php` (deleted in the same PR).
 *
 * Phase 2 batch #4 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_MODERATOR` → `stderr("Error", "Permission denied.")`.
 *   3. `SELECT agent, COUNT(*) FROM peers GROUP BY agent ORDER BY agent`.
 *   4. `stdhead('All Clients');` + `<table>` listing each
 *      BitTorrent client agent and its peer count + `stdfoot()`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`.
 *     The legacy script rendered an HTTP 200 with an `stderr()` body,
 *     which is the same anti-pattern `TakeUpdateController` replaces
 *     with a real 403 (see its class doc for the rationale).
 *   - Moderator+ → 200 with a chrome-less, self-contained HTML
 *     envelope wrapping a 2-column "Client / Counts" table. Follows
 *     the same pattern as `MoreSmiliesController` — the legacy
 *     `stdhead()` / `stdfoot()` chrome is not reproduced here; this
 *     is an internal admin tool and the original page used the
 *     chrome only as a viewport.
 *
 * The legacy script emitted a malformed `</a></td>` prefix before
 * every data row (no matching opening `<tr><td>`), dropping the
 * "Client" cell back into the previous row's structure. The
 * migrated controller emits a well-formed two-cell row that
 * matches every other admin table on the site.
 */
class AllAgentsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(): Response
    {
        $user = $this->context->user();
        // Auth middleware guarantees a user; we re-assert so a
        // misconfigured route can't reach the moderator gate
        // without one.
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $rows = NexusDB::table('peers')
            ->groupBy('agent')
            ->orderBy('agent')
            ->selectRaw('agent, count(*) as counts')
            ->get();

        $cells = '';
        foreach ($rows as $row) {
            $row = (array) $row;
            $agent = htmlspecialchars((string) ($row['agent'] ?? ''));
            $counts = htmlspecialchars((string) ($row['counts'] ?? '0'));
            $cells .= sprintf(
                "<tr><td align=\"left\">%s</td><td align=\"left\">%s</td></tr>\n",
                $agent,
                $counts,
            );
        }

        $body = "<table align=\"center\" border=\"3\" cellspacing=\"0\" cellpadding=\"5\">\n"
            ."<tr><td class=\"colhead\">Client</td><td class=\"colhead\">Counts</td></tr>\n"
            .$cells
            ."</table>\n";

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>All Clients</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}
