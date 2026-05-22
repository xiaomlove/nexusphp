<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/cheaters.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. `get_user_class() < UC_MODERATOR` gate — only Moderator+
 *      (class >= 13) sees the page.
 *   3. Renders a filter form (class threshold, ratio threshold)
 *      and a paginated table of users ranked by `cheat` score,
 *      showing username, registration date, upload/download stats,
 *      ratio, cheat value, and cheat spread percentage.
 *   4. Uses `pager(20, $top, ...)` for pagination (max 100 results).
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`.
 *   - Moderator+ → 200 with chrome-less HTML envelope wrapping the
 *     filter form and cheater table.
 *   - Filters: `?c=` (class threshold), `?r=` (ratio threshold).
 *   - Pagination via `?page=<n>` (20 per page, max 100 total).
 */
class CheatersController extends Controller
{
    /** Maximum results to display. */
    private const TOP_LIMIT = 100;

    /** Rows per page. Matches the legacy `pager(20, ...)` argument. */
    private const PER_PAGE = 20;

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

        $class = (int) $request->query('c', 0);
        $ratio = (int) $request->query('r', 0);
        $page = max(1, (int) $request->query('page', 1));

        // Build WHERE clause
        $where = 'WHERE enabled = 1 AND downloaded > 0 AND uploaded > 0';
        if ($class > 2) {
            $where .= ' AND class < '.($class - 1);
        }
        if ($ratio > 1) {
            $where .= ' AND (uploaded / downloaded) > '.($ratio - 1);
        }

        // Get summary stats
        $summaryRows = NexusDB::select("SELECT COUNT(*) AS c, MIN(cheat) AS mn, MAX(cheat) AS mx FROM users {$where}");
        $summary = $summaryRows ? (array) $summaryRows[0] : ['c' => 0, 'mn' => 0, 'mx' => 0];
        $totalCount = min(self::TOP_LIMIT, (int) ($summary['c'] ?? 0));
        $min = (float) ($summary['mn'] ?? 0);
        $max = (float) ($summary['mx'] ?? 0);

        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        // Fetch cheat rows
        $cheatRows = NexusDB::select(
            "SELECT * FROM users {$where} ORDER BY cheat DESC LIMIT ".self::PER_PAGE." OFFSET {$offset}",
        );

        $body = $this->renderFilterForm($class, $ratio);
        $body .= $this->renderPager($totalCount, $page, $totalPages, $class, $ratio);
        $body .= $this->renderTable($cheatRows, $min, $max);
        $body .= $this->renderPager($totalCount, $page, $totalPages, $class, $ratio);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Cheaters</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderFilterForm(int $class, int $ratio): string
    {
        $body = '<h1>Cheaters</h1>'."\n"
            .'<center><form method="get" action="cheaters.php">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><th colspan="4">Important</th></tr>'
            .'<tr><td colspan="4" class="left">'
            .'Although the word <b>cheat</b> is used here, it should be kept in mind that this '
            .'is statistical analysis - &quot;There are lies, damm lies, and statistics!&quot;<br />'
            .'The value for cheating can and will change quite drastically depending on what '
            .'is happening, so you should always take into account other factors before '
            .'issuing a warning.</td></tr>'."\n"
            .'<tr><th>Class:</th><td><select name="c">'
            .'<option value="1">(any)</option>';

        // Build class options (class 0 = Peasant through to the max)
        for ($i = 2; $i <= 18; $i++) {
            $className = get_user_class_name($i - 2);
            if (! $className) {
                break;
            }
            $selected = ($class === $i) ? ' selected' : '';
            $body .= '<option value="'.$i.'"'.$selected.'>&lt;= '.htmlspecialchars($className).'</option>';
        }

        $body .= '</select></td>'
            .'<th>Ratio:</th><td><select name="r">'
            .'<option value="1"'.($ratio === 1 ? ' selected' : '').'>(any)</option>'
            .'<option value="2"'.($ratio === 2 ? ' selected' : '').'>&gt;= 1.000</option>'
            .'<option value="3"'.($ratio === 3 ? ' selected' : '').'>&gt;= 2.000</option>'
            .'<option value="4"'.($ratio === 4 ? ' selected' : '').'>&gt;= 3.000</option>'
            .'<option value="5"'.($ratio === 5 ? ' selected' : '').'>&gt;= 4.000</option>'
            .'<option value="6"'.($ratio === 6 ? ' selected' : '').'>&gt;= 5.000</option>'
            .'</select></td></tr>'."\n"
            .'<tr><td colspan="4"><input name="submit" type="submit" value="Filter"></td></tr>'."\n"
            .'</table></form></center>'."\n";

        return $body;
    }

    private function renderTable(array $cheatRows, float $min, float $max): string
    {
        $body = '<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><th class="left">User name</th><th>Registered</th>'
            .'<th>Uploaded</th><th>Downloaded</th><th>Ratio</th>'
            .'<th>Cheat Value</th><th>Cheat Spread</th></tr>'."\n";

        foreach ($cheatRows as $rowObj) {
            $arr = (array) $rowObj;
            $id = (int) ($arr['id'] ?? 0);
            $username = htmlspecialchars((string) ($arr['username'] ?? ''));
            $added = (string) ($arr['added'] ?? '');
            $uploaded = (int) ($arr['uploaded'] ?? 0);
            $downloaded = (int) ($arr['downloaded'] ?? 0);
            $cheat = (float) ($arr['cheat'] ?? 0);

            // Join date
            if ($added === '0000-00-00 00:00:00' || $added === '') {
                $joindate = 'N/A';
                $age = 1; // avoid division by zero
            } else {
                $joindate = htmlspecialchars(get_elapsed_time(strtotime($added)).' ago');
                $age = max(1, time() - strtotime($added));
            }

            // Ratio
            if ($downloaded > 0) {
                $ratioValue = number_format($uploaded / $downloaded, 3);
                $ratioColor = get_ratio_color($ratioValue);
                $ratioHtml = '<font color="'.$ratioColor.'">'.$ratioValue.'</font>';
            } else {
                $ratioHtml = $uploaded > 0 ? 'Inf.' : '---';
            }

            // Cheat spread
            $spread = ($max - $min) > 0
                ? (int) ceil(($cheat - $min) / ($max - $min) * 100)
                : 0;

            $body .= '<tr>'
                .'<th class="left"><a href="userdetails.php?id='.$id.'"><b>'.$username.'</b></a></th>'
                .'<td>'.$joindate.'</td>'
                .'<td class="right">'.mksize($uploaded).' @ '.mksize((int) ($uploaded / $age)).'ps</td>'
                .'<td class="right">'.mksize($downloaded).' @ '.mksize((int) ($downloaded / $age)).'ps</td>'
                .'<td>'.$ratioHtml.'</td>'
                .'<td>'.$cheat.'</td>'
                .'<td class="right">'.$spread.'%</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderPager(int $totalCount, int $page, int $totalPages, int $class, int $ratio): string
    {
        if ($totalCount <= self::PER_PAGE) {
            return '';
        }

        $params = '';
        if ($class > 0) {
            $params .= '&c='.$class;
        }
        if ($ratio > 0) {
            $params .= '&r='.$ratio;
        }

        $links = '';
        if ($page > 1) {
            $links .= '<a href="cheaters.php?page='.($page - 1).$params.'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="cheaters.php?page='.($page + 1).$params.'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}
