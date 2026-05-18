<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Repositories\SeedBoxRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class ViewSnatchesController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly LegacyContext $context,
        private readonly SeedBoxRepository $seedBoxRepository,
    ) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            abort(422, 'Invalid torrent id.');
        }

        $torrentName = NexusDB::table('torrents')->where('id', $id)->value('name');
        $torrentNameStr = (string) ($torrentName ?? '');

        $count = (int) NexusDB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $id)
            ->count();

        $idEsc = (string) $id;
        $body = '<h1 align="center">Snatch detail for '
            .'<a href="details.php?id='.$idEsc.'"><b>'
            .htmlspecialchars($torrentNameStr, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            .'</b></a></h1>'."\n";

        if ($count === 0) {
            $body .= $this->notice('Sorry', 'No snatched users found.');

            return new Response($this->wrap('View Snatches', $body));
        }

        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::PER_PAGE;
        $canViewIp = $this->canViewIp($viewer);
        $canViewAnonymous = $this->canViewAnonymous($viewer);
        $viewerId = (int) $viewer->id;

        $rows = NexusDB::table('snatched')
            ->where('finished', 'yes')
            ->where('torrentid', $id)
            ->orderByDesc('completedat')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        $body .= '<p align="center">Users that have finished downloading this torrent.</p>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5" align="center" width="940">'."\n"
            .'<tr>'
            .'<td class="colhead" align="center">User Name</td>'
            .($canViewIp ? '<td class="colhead" align="center">IP</td>' : '')
            .'<td class="colhead" align="center">Uploaded/Downloaded</td>'
            .'<td class="colhead" align="center">Ratio</td>'
            .'<td class="colhead" align="center">Seed Time</td>'
            .'<td class="colhead" align="center">Leech Time</td>'
            .'<td class="colhead" align="center">Completed</td>'
            .'<td class="colhead" align="center">Last Action</td>'
            .'<td class="colhead" align="center">Report</td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $userId = (int) ($arr['userid'] ?? 0);
            $uploadedBytes = (int) ($arr['uploaded'] ?? 0);
            $downloadedBytes = (int) ($arr['downloaded'] ?? 0);
            $seedSeconds = (int) ($arr['seedtime'] ?? 0);
            $leechSeconds = (int) ($arr['leechtime'] ?? 0);
            $ip = (string) ($arr['ip'] ?? '');
            $completedAt = (string) ($arr['completedat'] ?? '');
            $lastAction = (string) ($arr['last_action'] ?? '');

            $ratio = $this->renderRatio($uploadedBytes, $downloadedBytes);
            $uploaded = function_exists('mksize') ? (string) call_user_func('mksize', $uploadedBytes) : (string) $uploadedBytes;
            $downloaded = function_exists('mksize') ? (string) call_user_func('mksize', $downloadedBytes) : (string) $downloadedBytes;
            $seedTime = function_exists('mkprettytime') ? (string) call_user_func('mkprettytime', $seedSeconds) : (string) $seedSeconds;
            $leechTime = function_exists('mkprettytime') ? (string) call_user_func('mkprettytime', $leechSeconds) : (string) $leechSeconds;

            $totalTime = $seedSeconds + $leechSeconds;
            $uprateBytes = $totalTime > 0 ? (int) floor($uploadedBytes / $totalTime) : 0;
            $downrateBytes = $leechSeconds > 0 ? (int) floor($downloadedBytes / $leechSeconds) : 0;
            $uprate = function_exists('mksize') ? (string) call_user_func('mksize', $uprateBytes) : (string) $uprateBytes;
            $downrate = function_exists('mksize') ? (string) call_user_func('mksize', $downrateBytes) : (string) $downrateBytes;

            $userRow = $this->fetchUserRow($userId);
            $privacy = (string) ($userRow['privacy'] ?? '');
            $usernameLink = $this->renderUsername($userId, $userRow);
            if ($privacy === 'strong') {
                $username = 'Anonymous';
                if ($canViewAnonymous || $userId === $viewerId) {
                    $username .= '<br />('.$usernameLink.')';
                }
            } else {
                $username = $usernameLink;
            }

            $highlight = $userId === $viewerId ? ' bgcolor="#00A527"' : '';
            $completedAtStr = $this->renderTime($completedAt);
            $lastActionStr = $this->renderTime($lastAction);

            $reportImage = '<img class="f_report" src="pic/trans.gif" alt="Report" title="Report" />';
            if ($privacy !== 'strong' || $canViewAnonymous) {
                $reportCell = '<a href="report.php?user='.$userId.'">'.$reportImage.'</a>';
            } else {
                $reportCell = $reportImage;
            }

            $ipCell = '';
            if ($canViewIp) {
                $ipDisplay = htmlspecialchars($ip, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    .$this->renderSeedBoxIcon($ip, $userId);
                $ipCell = '<td class="rowfollow" align="center"><span class="nowrap">'.$ipDisplay.'</span></td>';
            }

            $body .= '<tr'.$highlight.'>'
                .'<td class="rowfollow" align="center">'.$username.'</td>'
                .$ipCell
                .'<td class="rowfollow" align="center">'.$uploaded.'@'.$uprate.'/s<br />'.$downloaded.'@'.$downrate.'/s</td>'
                .'<td class="rowfollow" align="center">'.$ratio.'</td>'
                .'<td class="rowfollow" align="center">'.$seedTime.'</td>'
                .'<td class="rowfollow" align="center">'.$leechTime.'</td>'
                .'<td class="rowfollow" align="center">'.$completedAtStr.'</td>'
                .'<td class="rowfollow" align="center">'.$lastActionStr.'</td>'
                .'<td class="rowfollow" align="center" style="padding: 0px">'.$reportCell.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n"
            .$this->renderPager($id, $count, $page);

        return new Response($this->wrap('View Snatches', $body));
    }

    private function canViewIp(User $viewer): bool
    {
        if (! function_exists('user_can')) {
            return false;
        }

        return (bool) call_user_func('user_can', 'userprofile', false, (int) $viewer->id);
    }

    private function canViewAnonymous(User $viewer): bool
    {
        if (! function_exists('user_can')) {
            return false;
        }

        return (bool) call_user_func('user_can', 'viewanonymous', false, (int) $viewer->id);
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUserRow(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $row = NexusDB::table('users')->where('id', $userId)->first(['id', 'username', 'class', 'privacy']);

        return $row === null ? [] : (array) $row;
    }

    /**
     * @param  array<string,mixed>  $userRow
     */
    private function renderUsername(int $userId, array $userRow): string
    {
        if (function_exists('get_username')) {
            return (string) call_user_func('get_username', $userId);
        }
        $name = (string) ($userRow['username'] ?? '');
        $nameEsc = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<a href="userdetails.php?id='.$userId.'">'.$nameEsc.'</a>';
    }

    private function renderRatio(int $uploaded, int $downloaded): string
    {
        if ($downloaded > 0) {
            $value = number_format($uploaded / $downloaded, 3);
            $color = function_exists('get_ratio_color') ? (string) call_user_func('get_ratio_color', $value) : 'black';

            return '<font color="'.htmlspecialchars($color, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">'.$value.'</font>';
        }
        if ($uploaded > 0) {
            return 'Inf.';
        }

        return '---';
    }

    private function renderTime(string $value): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        if (function_exists('gettime')) {
            return (string) call_user_func('gettime', $value, true, false);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function renderSeedBoxIcon(string $ip, int $userId): string
    {
        try {
            return (string) $this->seedBoxRepository->renderIcon($ip, $userId);
        } catch (\Throwable) {
            return '';
        }
    }

    private function renderPager(int $torrentId, int $count, int $page): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PER_PAGE);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="viewsnatches.php?id='.$torrentId.'&amp;page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="viewsnatches.php?id='.$torrentId.'&amp;page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }

    private function notice(string $title, string $message): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<h1 align="center">'.$titleEsc.'</h1>'."\n"
            .'<p align="center">'.$messageEsc.'</p>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}
