<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/stats.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_MODERATOR` gate — only Moderator+
 *      (class >= 13) sees the page; everyone else gets
 *      `stderr("Error", "Permission denied.")`.
 *   3. Renders two sortable tables:
 *      a) "Uploader Activity" — all users with class >= Uploader (3)
 *         showing last upload, torrent count/percentage, peer count/%.
 *      b) "Category Activity" — all categories with last upload,
 *         torrent count/%, peer count/%.
 *   4. Sorting via `?uporder=` (uploader/lastul/torrents/peers) and
 *      `?catorder=` (category/lastul/torrents/peers).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`.
 *   - Moderator+ → 200 with a chrome-less, self-contained HTML
 *     envelope wrapping the two stats tables. Follows the same
 *     precedent as `BansController` / `DonorlistController`.
 *   - Sorting parameters preserved via `?uporder=` and `?catorder=`.
 */
class StatsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $uporder = (string) $request->query('uporder', '');
        $catorder = (string) $request->query('catorder', '');

        $nTor = (int) NexusDB::table('torrents')->count();
        $nPeers = (int) NexusDB::table('peers')->count();

        $body = $this->renderUploaderActivity($uporder, $catorder, $nTor, $nPeers);
        $body .= $this->renderCategoryActivity($uporder, $catorder, $nTor, $nPeers);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Stats</title>
<style type="text/css">
a.colheadlink:link, a.colheadlink:visited {
    font-weight: bold;
    color: #FFFFFF;
    text-decoration: none;
}
a.colheadlink:hover {
    text-decoration: underline;
}
</style>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderUploaderActivity(string $uporder, string $catorder, int $nTor, int $nPeers): string
    {
        $orderby = match ($uporder) {
            'lastul' => 'last DESC, name',
            'torrents' => 'n_t DESC, name',
            'peers' => 'n_p DESC, name',
            default => 'name',
        };

        $query = 'SELECT u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) as n_p '
            .'FROM users as u LEFT JOIN torrents as t ON u.id = t.owner LEFT JOIN peers as p ON t.id = p.torrent WHERE u.class = 3 '
            .'GROUP BY u.id UNION SELECT u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) as n_p '
            .'FROM users as u LEFT JOIN torrents as t ON u.id = t.owner LEFT JOIN peers as p ON t.id = p.torrent WHERE u.class > 3 '
            .'GROUP BY u.id ORDER BY '.$orderby;

        $uperRows = NexusDB::select($query);

        if (count($uperRows) === 0) {
            return '<p align="center"><b>No uploaders.</b></p>'."\n";
        }

        $body = '<h2>Uploader Activity</h2>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead"><a href="stats.php?uporder=uploader&catorder='.htmlspecialchars($catorder).'" class="colheadlink">Uploader</a></td>'
            .'<td class="colhead"><a href="stats.php?uporder=lastul&catorder='.htmlspecialchars($catorder).'" class="colheadlink">Last Upload</a></td>'
            .'<td class="colhead"><a href="stats.php?uporder=torrents&catorder='.htmlspecialchars($catorder).'" class="colheadlink">Torrents</a></td>'
            .'<td class="colhead">Perc.</td>'
            .'<td class="colhead"><a href="stats.php?uporder=peers&catorder='.htmlspecialchars($catorder).'" class="colheadlink">Peers</a></td>'
            .'<td class="colhead">Perc.</td>'
            .'</tr>'."\n";

        foreach ($uperRows as $uper) {
            $arr = (array) $uper;
            $id = (int) ($arr['id'] ?? 0);
            $name = htmlspecialchars((string) ($arr['name'] ?? ''));
            $last = $arr['last'] ?? null;
            $nT = (int) ($arr['n_t'] ?? 0);
            $nP = (int) ($arr['n_p'] ?? 0);

            $lastCell = $last
                ? '>'.htmlspecialchars((string) $last)
                : ' align="center">---';
            $torPerc = $nTor > 0 ? number_format(100 * $nT / $nTor, 1).'%' : '---';
            $peerPerc = $nPeers > 0 ? number_format(100 * $nP / $nPeers, 1).'%' : '---';

            $body .= '<tr>'
                .'<td><a href="userdetails.php?id='.$id.'">'.htmlspecialchars($name).'</a></td>'
                .'<td'.$lastCell.'</td>'
                .'<td align="right">'.$nT.'</td>'
                .'<td align="right">'.$torPerc.'</td>'
                .'<td align="right">'.$nP.'</td>'
                .'<td align="right">'.$peerPerc.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderCategoryActivity(string $uporder, string $catorder, int $nTor, int $nPeers): string
    {
        if ($nTor === 0) {
            return '<p align="center"><b>No categories defined!</b></p>'."\n";
        }

        $orderby = match ($catorder) {
            'lastul' => 'last DESC, c.name',
            'torrents' => 'n_t DESC, c.name',
            'peers' => 'n_p DESC, c.name',
            default => 'c.name',
        };

        $catRows = NexusDB::select(
            'SELECT c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p '
            .'FROM categories as c LEFT JOIN torrents as t ON t.category = c.id LEFT JOIN peers as p '
            .'ON t.id = p.torrent GROUP BY c.id ORDER BY '.$orderby,
        );

        $body = '<h2>Category Activity</h2>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead"><a href="stats.php?uporder='.htmlspecialchars($uporder).'&catorder=category" class="colheadlink">Category</a></td>'
            .'<td class="colhead"><a href="stats.php?uporder='.htmlspecialchars($uporder).'&catorder=lastul" class="colheadlink">Last Upload</a></td>'
            .'<td class="colhead"><a href="stats.php?uporder='.htmlspecialchars($uporder).'&catorder=torrents" class="colheadlink">Torrents</a></td>'
            .'<td class="colhead">Perc.</td>'
            .'<td class="colhead"><a href="stats.php?uporder='.htmlspecialchars($uporder).'&catorder=peers" class="colheadlink">Peers</a></td>'
            .'<td class="colhead">Perc.</td>'
            .'</tr>'."\n";

        foreach ($catRows as $cat) {
            $arr = (array) $cat;
            $name = htmlspecialchars((string) ($arr['name'] ?? ''));
            $last = $arr['last'] ?? null;
            $nT = (int) ($arr['n_t'] ?? 0);
            $nP = (int) ($arr['n_p'] ?? 0);

            $lastCell = $last
                ? '>'.htmlspecialchars((string) $last)
                : ' align="center">---';
            $torPerc = number_format(100 * $nT / $nTor, 1).'%';
            $peerPerc = $nPeers > 0 ? number_format(100 * $nP / $nPeers, 1).'%' : '---';

            $body .= '<tr>'
                .'<td class="rowhead">'.$name.'</td>'
                .'<td'.$lastCell.'</td>'
                .'<td align="right">'.$nT.'</td>'
                .'<td align="right">'.$torPerc.'</td>'
                .'<td align="right">'.$nP.'</td>'
                .'<td align="right">'.$peerPerc.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }
}
