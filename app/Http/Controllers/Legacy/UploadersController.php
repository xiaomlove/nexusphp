<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/uploaders.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. `get_user_class() < UC_UPLOADER` gate — Uploader+ (class >= 12).
 *   3. Renders a year/month selector form.
 *   4. Queries uploaders with torrent stats for the selected month.
 *   5. Also lists uploaders with no uploads that month.
 *   6. Sort options: username, torrent_size, torrent_count.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - User below `User::CLASS_UPLOADER` → `abort(403)`.
 *   - Authenticated → 200 with chrome-less HTML envelope.
 *   - Filters: `?year=`, `?month=`, `?order=`.
 */
class UploadersController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_UPLOADER) {
            abort(403);
        }

        $year = (int) $request->query('year', (int) date('Y'));
        $month = (int) $request->query('month', (int) date('m'));
        $order = (string) $request->query('order', 'username');

        if ($year < 2000) {
            $year = (int) date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int) date('m');
        }
        if (! in_array($order, ['username', 'torrent_size', 'torrent_count'])) {
            $order = 'username';
        }

        $orderSql = $order === 'username' ? 'username ASC' : $order.' DESC';

        $timeStart = strtotime("{$year}-{$month}-01 00:00:00");
        $sqlStartTime = date('Y-m-d H:i:s', $timeStart);
        $timeEnd = strtotime('+1 month', $timeStart);
        $sqlEndTime = date('Y-m-d H:i:s', $timeEnd);

        $ucUploader = (int) User::CLASS_UPLOADER;

        // Check if any uploaders exist
        $numUploaders = (int) NexusDB::table('users')->where('class', '>=', $ucUploader)->count();

        $body = '<h1 align="center">Uploaders - '.date('Y-m', $timeStart).'</h1>'."\n";
        $body .= $this->renderYearMonthForm($year, $month);

        if ($numUploaders === 0) {
            $body .= '<p align="center">No uploaders yet.</p>';
        } else {
            $body .= $this->renderTable($ucUploader, $sqlStartTime, $sqlEndTime, $orderSql, $year, $month);
            $body .= $this->renderOrderLinks($year, $month);
        }

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Uploaders</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderYearMonthForm(int $year, int $month): string
    {
        $yearNow = (int) date('Y');
        $yearOptions = '';
        for ($i = 2007; $i <= $yearNow; $i++) {
            $selected = ($i === $year) ? ' selected' : '';
            $yearOptions .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }

        $monthOptions = '';
        for ($i = 1; $i <= 12; $i++) {
            $selected = ($i === $month) ? ' selected' : '';
            $monthOptions .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }

        return '<form method="get" action="uploaders.php">'
            .'Select month: <select name="year">'.$yearOptions.'</select>&nbsp;&nbsp;'
            .'<select name="month">'.$monthOptions.'</select>&nbsp;&nbsp;'
            .'<input type="submit" value="Go" />'
            .'</form>'."\n";
    }

    private function renderTable(int $ucUploader, string $sqlStartTime, string $sqlEndTime, string $orderSql, int $year, int $month): string
    {
        $startQuoted = NexusDB::getPdo()->quote($sqlStartTime);
        $endQuoted = NexusDB::getPdo()->quote($sqlEndTime);

        $rows = NexusDB::select(
            'SELECT users.id AS userid, users.username AS username, COUNT(torrents.id) AS torrent_count, SUM(torrents.size) AS torrent_size '
            .'FROM torrents LEFT JOIN users ON torrents.owner=users.id '
            ."WHERE users.class >= {$ucUploader} AND torrents.added > {$startQuoted} AND torrents.added < {$endQuoted} "
            ."GROUP BY userid ORDER BY {$orderSql}",
        );

        $hasUpUserIds = [];

        $body = '<table border="1" cellspacing="0" cellpadding="5" align="center" width="97%"><tr>'
            .'<td class="colhead">Username</td>'
            .'<td class="colhead">Torrents Size</td>'
            .'<td class="colhead">Torrents Count</td>'
            .'<td class="colhead">Last Upload Time</td>'
            .'<td class="colhead">Last Upload</td>'
            .'</tr>'."\n";

        foreach ($rows as $rowObj) {
            $row = (array) $rowObj;
            $uid = (int) $row['userid'];
            $hasUpUserIds[] = $uid;

            $lastTorrentObj = NexusDB::table('torrents')
                ->where('owner', $uid)
                ->orderByDesc('id')
                ->select(['id', 'name', 'added'])
                ->first();
            $lastTorrent = $lastTorrentObj ? (array) $lastTorrentObj : [];

            $body .= '<tr>'
                .'<td class="colfollow">'.get_username($uid, false, true, true, false, false, true).'</td>'
                .'<td class="colfollow">'.($row['torrent_size'] ? mksize((int) $row['torrent_size']) : '0').'</td>'
                .'<td class="colfollow">'.(int) $row['torrent_count'].'</td>'
                .'<td class="colfollow">'.(! empty($lastTorrent['added']) ? htmlspecialchars(gettime($lastTorrent['added'])) : 'N/A').'</td>'
                .'<td class="colfollow">'.(! empty($lastTorrent['name']) ? '<a href="details.php?id='.$lastTorrent['id'].'">'.htmlspecialchars($lastTorrent['name']).'</a>' : 'N/A').'</td>'
                .'</tr>'."\n";
        }

        // Uploaders with no uploads this month
        $notIn = count($hasUpUserIds) > 0
            ? ' AND users.id NOT IN ('.implode(',', $hasUpUserIds).')'
            : '';
        $inactiveRows = NexusDB::select(
            "SELECT users.id AS userid, users.username AS username FROM users WHERE class >= {$ucUploader}{$notIn} ORDER BY username ASC",
        );

        foreach ($inactiveRows as $rowObj) {
            $row = (array) $rowObj;
            $uid = (int) $row['userid'];

            $lastTorrentObj = NexusDB::table('torrents')
                ->where('owner', $uid)
                ->orderByDesc('id')
                ->select(['id', 'name', 'added'])
                ->first();
            $lastTorrent = $lastTorrentObj ? (array) $lastTorrentObj : [];

            $body .= '<tr>'
                .'<td class="colfollow">'.get_username($uid, false, true, true, false, false, true).'</td>'
                .'<td class="colfollow">0</td>'
                .'<td class="colfollow">0</td>'
                .'<td class="colfollow">'.(! empty($lastTorrent['added']) ? htmlspecialchars(gettime($lastTorrent['added'])) : 'N/A').'</td>'
                .'<td class="colfollow">'.(! empty($lastTorrent['name']) ? '<a href="details.php?id='.$lastTorrent['id'].'">'.htmlspecialchars($lastTorrent['name']).'</a>' : 'N/A').'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderOrderLinks(int $year, int $month): string
    {
        return '<div style="margin-top: 8px; margin-bottom: 8px;"><b>Order by:</b> '
            .'<a href="uploaders.php?year='.$year.'&month='.$month.'&order=username">Username</a> | '
            .'<a href="uploaders.php?year='.$year.'&month='.$month.'&order=torrent_size">Torrent Size</a> | '
            .'<a href="uploaders.php?year='.$year.'&month='.$month.'&order=torrent_count">Torrent Count</a>'
            .'</div>'."\n";
    }
}
