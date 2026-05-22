<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Claim;
use App\Models\Snatch;
use App\Models\User;
use App\Repositories\ClaimRepository;
use App\Repositories\SeedBoxRepository;
use App\Repositories\TorrentRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/getusertorrentlistajax.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. AJAX fragment endpoint called by
 * `public/js/common.js::getusertorrentlistajax()` to render the
 * expandable torrent sub-tables on the user-details page
 * (`public/userdetails.php` and the `/user/{id}` Livewire page).
 *
 * Five `?type=` modes:
 *   - `uploaded`   — torrents owned by the user
 *   - `seeding`    — currently seeding (peers JOIN snatched)
 *   - `leeching`   — currently leeching
 *   - `completed`  — snatched + finished
 *   - `incomplete` — snatched + not finished
 *
 * Returns a bare HTML fragment (no `<html>` wrapper) — the JS
 * snippet `innerHTML`s the result directly into the expand block.
 * Cache-control headers are preserved from the original.
 *
 * URL preserved exactly so `public/js/common.js:361` and
 * `public/userdetails.php:344–356` keep working without changes.
 *
 * No CSRF needed — GET-only endpoint (the JS uses `ajax.gets()`).
 */
class GetUserTorrentListAjaxController extends Controller
{
    /** Rows per page. Matches the legacy `pager(100, ...)`. */
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly LegacyContext $context,
        private readonly TorrentRepository $torrentRep,
        private readonly ClaimRepository $claimRep,
        private readonly SeedBoxRepository $seedBoxRep,
    ) {}

    public function __invoke(Request $request): Response
    {
        // Suppress caching, matching the legacy `header()` calls.
        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        $viewer = $this->context->user();
        if ($viewer === null) {
            return new Response('', 401, $headers);
        }

        $lang = $this->loadLang();

        $id = (int) $request->query('userid', 0);
        $type = (string) $request->query('type', '');

        $validTypes = ['uploaded', 'seeding', 'leeching', 'completed', 'incomplete'];
        if (! in_array($type, $validTypes, true)) {
            return new Response('', 400, $headers);
        }

        // Permission gate: own profile or has torrenthistory permission.
        if (! user_can('torrenthistory') && $id !== (int) $viewer->id) {
            return new Response('', 403, $headers);
        }

        $claimTorrentTTL = Claim::getConfigTorrentTTL();

        [$fields, $tableWhere, $order] = $this->buildQuery($type, $id, $viewer);

        if ($tableWhere === '') {
            return new Response(htmlspecialchars((string) ($lang['text_no_record'] ?? 'No records.')), 200, $headers);
        }

        $page = max(0, (int) $request->query('page', 0));
        $cacheKey = sprintf('user:%s:type:%s:total_size', $id, $type);

        if ($page === 0) {
            $sumSql = "select count(*) as count, sum(torrents.size) as total_size from {$tableWhere} limit 1";
            $sumRows = NexusDB::select($sumSql);
            $sumRes = $sumRows ? (array) $sumRows[0] : ['count' => 0, 'total_size' => 0];
            NexusDB::cache_put($cacheKey, $sumRes);
        } else {
            $sumRes = NexusDB::remember($cacheKey, 3600, function () use ($tableWhere): array {
                $sumSql = "select count(*) as count, sum(torrents.size) as total_size from {$tableWhere} limit 1";
                $sumRows = NexusDB::select($sumSql);

                return $sumRows ? (array) $sumRows[0] : ['count' => 0, 'total_size' => 0];
            });
        }

        $count = (int) ($sumRes['count'] ?? 0);
        $totalSize = (float) ($sumRes['total_size'] ?? 0);

        if ($count === 0) {
            return new Response(
                htmlspecialchars((string) ($lang['text_no_record'] ?? 'No records.')),
                200,
                $headers,
            );
        }

        // Pager: offset = page * PAGE_SIZE.
        $offset = $page * self::PAGE_SIZE;
        $limitSql = 'LIMIT '.self::PAGE_SIZE.' OFFSET '.$offset;

        $sql = "select {$fields} from {$tableWhere} order by {$order} {$limitSql}";
        $res = NexusDB::select($sql);

        [$table, $totalSizeThisPage] = $this->buildTable($res, $type, $id, $viewer, $lang, $claimTorrentTTL);

        $pagerHtml = $this->buildPager($count, $page, $id, $type, $lang);
        $summary = '<b>'.$count.'</b>'.(string) ($lang['text_record'] ?? ' record').add_s($count);

        /** @phpstan-ignore-next-line */
        $showTotalSize = in_array($type, ['uploaded', 'seeding', 'leeching'], true);
        if ($showTotalSize && $totalSize > 0) {
            $summary .= (string) ($lang['text_total_size'] ?? ', total size: ').mksize((float) $totalSize);
        }

        $btnArr = apply_filter('user_seeding_top_btn', [], (int) $viewer->id);
        $header = '<div style="display:flex;justify-content:space-between">'
            .'<div>'.$summary.'</div>'
            .'<div>'.implode('', $btnArr).'</div>'
            .'</div>';

        $html = '<br />'.$header.$pagerHtml.$table.$pagerHtml;

        return new Response($html, 200, $headers);
    }

    // ─── Query builder ────────────────────────────────────────────────────

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildQuery(string $type, int $id, User $viewer): array
    {
        switch ($type) {
            case 'uploaded':
                $fields = 'torrents.id AS torrent, torrents.name as torrentname, small_descr, seeders, leechers, anonymous, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added, torrents.owner as userid, categories.mode as search_box_id';
                $tableWhere = "torrents LEFT JOIN categories ON torrents.category = categories.id WHERE torrents.owner={$id}";
                if ((int) $viewer->id !== $id && ! user_can('viewanonymous')) {
                    $tableWhere .= " AND anonymous = 'no'";
                }

                return [$fields, $tableWhere, 'torrents.id DESC'];

            case 'seeding':
                $fields = 'torrent,added,snatched.uploaded,snatched.downloaded,snatched.seedtime,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers,snatched.userid, categories.mode as search_box_id, peers.peer_id, peers.agent, peers.port, peers.ipv4, peers.ipv6';
                $tableWhere = "peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid={$id} AND snatched.userid = {$id} AND peers.seeder='yes'";

                return [$fields, $tableWhere, 'peers.id DESC'];

            case 'leeching':
                $fields = 'torrent,snatched.uploaded,snatched.downloaded,snatched.seedtime,torrents.name as torrentname, torrents.small_descr, torrents.sp_state, torrents.banned, torrents.approval_status, categories.name as catname,size,torrents.hr,image,category,seeders,leechers, torrents.added,snatched.userid, categories.mode as search_box_id, peers.peer_id, peers.agent, peers.port, peers.ipv4, peers.ipv6';
                $tableWhere = "peers LEFT JOIN torrents ON peers.torrent = torrents.id LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN snatched ON torrents.id = snatched.torrentid WHERE peers.userid={$id} AND snatched.userid = {$id} AND peers.seeder='no'";

                return [$fields, $tableWhere, 'peers.id DESC'];

            case 'completed':
                $fields = 'torrents.id AS torrent, torrents.name AS torrentname, small_descr, categories.name AS catname, torrents.banned, torrents.approval_status, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.seedtime,snatched.uploaded, snatched.leechtime, snatched.completedat,snatched.userid, categories.mode as search_box_id';
                $tableWhere = "torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='yes' AND userid={$id} AND torrents.owner != {$id}";

                return [$fields, $tableWhere, 'snatched.id DESC'];

            case 'incomplete':
                $fields = 'torrents.id AS torrent, torrents.name AS torrentname, small_descr, torrents.banned, torrents.approval_status, categories.name AS catname, categories.image, category, sp_state, size, torrents.hr, torrents.added,snatched.uploaded, snatched.downloaded, snatched.leechtime,snatched.seedtime,snatched.userid, categories.mode as search_box_id';
                $tableWhere = "torrents LEFT JOIN snatched ON torrents.id = snatched.torrentid LEFT JOIN categories on torrents.category = categories.id WHERE snatched.finished='no' AND userid={$id} AND torrents.owner != {$id}";

                return [$fields, $tableWhere, 'snatched.id DESC'];
        }

        return ['', '', ''];
    }

    // ─── Table builder ────────────────────────────────────────────────────

    /**
     * @param  array<int, mixed>  $res
     * @return array{0: string, 1: float}
     */
    private function buildTable(
        array $res,
        string $type,
        int $id,
        User $viewer,
        array $lang,
        int $claimTorrentTTL,
    ): array {
        // Column visibility flags.
        $showSize = true;
        $showSeNum = in_array($type, ['uploaded', 'seeding', 'leeching'], true);
        $showLeNum = $showSeNum;
        $showUploaded = true;
        $showDownloaded = in_array($type, ['seeding', 'leeching', 'incomplete'], true);
        $showRatio = $showDownloaded;
        $showSeTime = in_array($type, ['uploaded', 'seeding', 'completed'], true);
        $showLeTime = in_array($type, ['leeching', 'completed', 'incomplete'], true);
        $showCoTime = ($type === 'completed');
        $showAnonymous = ($type === 'uploaded');
        $showTotalSize = in_array($type, ['uploaded', 'seeding', 'leeching'], true);
        $showActionClaim = in_array($type, ['seeding', 'completed'], true);
        $showClient = in_array($type, ['seeding', 'leeching'], true);
        $shouldShowClient = $showClient && (user_can('userprofile') || (int) $viewer->id === $id);

        // Pre-fetch seedtime/uploaded for 'uploaded' mode.
        $results = [];
        $torrentIdArr = [];
        foreach ($res as $row) {
            $row = (array) $row;
            $results[] = $row;
            $torrentIdArr[] = (int) ($row['torrent'] ?? 0);
        }

        $seedTimeAndUploaded = collect();
        if ($type === 'uploaded' && ! empty($torrentIdArr)) {
            $seedTimeAndUploaded = Snatch::query()
                ->where('userid', $id)
                ->whereIn('torrentid', $torrentIdArr)
                ->select(['seedtime', 'uploaded', 'torrentid'])
                ->get()
                ->keyBy('torrentid');
        }

        $claimData = collect();
        if ($showActionClaim && ! empty($torrentIdArr)) {
            $claimData = Claim::query()
                ->where('uid', (int) $viewer->id)
                ->whereIn('torrent_id', $torrentIdArr)
                ->get()
                ->keyBy('torrent_id');
        }

        // Column headers.
        $colType = htmlspecialchars((string) ($lang['col_type'] ?? 'Type'));
        $colName = htmlspecialchars((string) ($lang['col_name'] ?? 'Name'));
        $colAdded = htmlspecialchars((string) ($lang['col_added'] ?? 'Added'));
        $colUp = htmlspecialchars((string) ($lang['col_uploaded'] ?? 'Uploaded'));
        $colDown = htmlspecialchars((string) ($lang['col_downloaded'] ?? 'Downloaded'));
        $colRatio = htmlspecialchars((string) ($lang['col_ratio'] ?? 'Ratio'));
        $colSeST = htmlspecialchars((string) ($lang['col_se_time'] ?? 'Seed time'));
        $colLeST = htmlspecialchars((string) ($lang['col_le_time'] ?? 'Leech time'));
        $colCoT = htmlspecialchars((string) ($lang['col_time_completed'] ?? 'Completed at'));
        $colAnon = htmlspecialchars((string) ($lang['col_anonymous'] ?? 'Anonymous'));
        $colClient = htmlspecialchars((string) ($lang['col_client'] ?? 'Client'));
        $colAction = htmlspecialchars((string) ($GLOBALS['lang_functions']['std_action'] ?? 'Action'));
        $ttSize = htmlspecialchars((string) ($lang['title_size'] ?? 'Size'), ENT_QUOTES);
        $ttSeedrs = htmlspecialchars((string) ($lang['title_seeders'] ?? 'Seeders'), ENT_QUOTES);
        $ttLechs = htmlspecialchars((string) ($lang['title_leechers'] ?? 'Leechers'), ENT_QUOTES);

        $header = '<td class="colhead" style="padding:0px">'.$colType.'</td>'
            .'<td class="colhead" align="center">'.$colName.'</td>'
            .'<td class="colhead" align="center">'.$colAdded.'</td>'
            .($showSize ? '<td class="colhead" align="center"><img class="size" src="pic/trans.gif" alt="size" title="'.$ttSize.'" /></td>' : '')
            .($showSeNum ? '<td class="colhead" align="center"><img class="seeders" src="pic/trans.gif" alt="seeders" title="'.$ttSeedrs.'" /></td>' : '')
            .($showLeNum ? '<td class="colhead" align="center"><img class="leechers" src="pic/trans.gif" alt="leechers" title="'.$ttLechs.'" /></td>' : '')
            .($showUploaded ? '<td class="colhead" align="center">'.$colUp.'</td>' : '')
            .($showDownloaded ? '<td class="colhead" align="center">'.$colDown.'</td>' : '')
            .($showRatio ? '<td class="colhead" align="center">'.$colRatio.'</td>' : '')
            .($showSeTime ? '<td class="colhead" align="center">'.$colSeST.'</td>' : '')
            .($showLeTime ? '<td class="colhead" align="center">'.$colLeST.'</td>' : '')
            .($showCoTime ? '<td class="colhead" align="center">'.$colCoT.'</td>' : '')
            .($showAnonymous ? '<td class="colhead" align="center">'.$colAnon.'</td>' : '');
        if ($shouldShowClient) {
            $header .= '<td class="colhead" align="center">'.$colClient.'</td>'
                .'<td class="colhead" align="center">IP</td>';
        }
        $header .= '<td class="colhead" align="center">'.$colAction.'</td>';

        $rows = '';
        $totalSize = 0.0;
        $smallDesc = ($GLOBALS['smalldescription_main'] ?? '') === 'yes';
        $maxLen = ((int) ($GLOBALS['CURUSER']['fontsize'] ?? 0) === 1) ? 70 : 80;

        foreach ($results as $arr) {
            if ($type === 'uploaded') {
                $stData = $seedTimeAndUploaded->get($arr['torrent']);
                $arr['seedtime'] = $stData ? $stData->seedtime : 0;
                $arr['uploaded'] = $stData ? $stData->uploaded : 0;
            }

            if ($showTotalSize) {
                $totalSize += (float) ($arr['size'] ?? 0);
            }

            $sphighlight = get_torrent_bg_color((string) ($arr['sp_state'] ?? ''));
            $bannedSuffix = ($arr['banned'] ?? '') === 'yes'
                ? ' <b>(<font class="striking">'.htmlspecialchars((string) ($GLOBALS['lang_functions']['text_banned'] ?? 'Banned')).'</font>)</b>'
                : '';
            $spSuffix = get_torrent_promotion_append(
                (string) ($arr['sp_state'] ?? ''), '', false, '', 0, '',
                (bool) ($arr['__ignore_global_sp_state'] ?? false),
            );
            $hrImg = get_hr_img($arr, (string) ($arr['search_box_id'] ?? ''));
            $approvalIcon = $this->torrentRep->renderApprovalStatus((string) ($arr['approval_status'] ?? ''));

            $dispname = htmlspecialchars((string) ($arr['torrentname'] ?? ''));
            $nametitle = $dispname;
            if (mb_strlen($dispname, 'UTF-8') > $maxLen) {
                $dispname = mb_substr($dispname, 0, $maxLen, 'UTF-8').'..';
            }
            $smallDescHtml = '';
            if ($smallDesc) {
                $sd = htmlspecialchars(trim((string) ($arr['small_descr'] ?? '')));
                if (mb_strlen($sd, 'UTF-8') > 80) {
                    $sd = mb_substr($sd, 0, 80, 'UTF-8').'..';
                }
                if ($sd !== '') {
                    $smallDescHtml = '<br />'.$sd;
                }
            }

            $torrentId = (int) ($arr['torrent'] ?? 0);
            $row = '<tr'.$sphighlight.'>'
                .'<td class="rowfollow nowrap" valign="middle" style="padding:0px">'
                .return_category_image((int) ($arr['category'] ?? 0), 'torrents.php?allsec=1&amp;')
                .'</td>'
                .'<td class="rowfollow" width="100%" align="left">'
                .'<a href="'.htmlspecialchars('details.php?id='.$torrentId.'&hit=1').'" title="'.$nametitle.'"><b>'.$dispname.'</b></a>'
                .$bannedSuffix.$spSuffix.$hrImg.$approvalIcon.$smallDescHtml
                .'</td>'
                .'<td class="rowfollow nowrap" align="center">'
                .substr((string) ($arr['added'] ?? ''), 0, 10).'<br/>'.substr((string) ($arr['added'] ?? ''), 11)
                .'</td>';

            if ($showSize) {
                $row .= '<td class="rowfollow" align="center">'.mksize_compact((float) ($arr['size'] ?? 0)).'</td>';
            }
            if ($showSeNum) {
                $row .= '<td class="rowfollow" align="center">'.((int) ($arr['seeders'] ?? 0)).'</td>';
            }
            if ($showLeNum) {
                $row .= '<td class="rowfollow" align="center">'.((int) ($arr['leechers'] ?? 0)).'</td>';
            }
            if ($showUploaded) {
                $row .= '<td class="rowfollow" align="center">'.mksize_compact((float) ($arr['uploaded'] ?? 0)).'</td>';
            }
            if ($showDownloaded) {
                $row .= '<td class="rowfollow" align="center">'.mksize_compact((float) ($arr['downloaded'] ?? 0)).'</td>';
            }
            if ($showRatio) {
                $dl = (float) ($arr['downloaded'] ?? 0);
                $ul = (float) ($arr['uploaded'] ?? 0);
                if ($dl > 0) {
                    $ratioVal = number_format($ul / $dl, 3);
                    $ratio = '<font color="'.get_ratio_color($ratioVal).'">'.$ratioVal.'</font>';
                } elseif ($ul > 0) {
                    $ratio = 'Inf.';
                } else {
                    $ratio = '---';
                }
                $row .= '<td class="rowfollow" align="center">'.$ratio.'</td>';
            }
            if ($showSeTime) {
                $row .= '<td class="rowfollow" align="center">'.mkprettytime((int) ($arr['seedtime'] ?? 0)).'</td>';
            }
            if ($showLeTime) {
                $row .= '<td class="rowfollow" align="center">'.mkprettytime((int) ($arr['leechtime'] ?? 0)).'</td>';
            }
            if ($showCoTime) {
                $row .= '<td class="rowfollow" align="center">'.str_replace('&nbsp;', '<br />', gettime((string) ($arr['completedat'] ?? ''), false)).'</td>';
            }
            if ($showAnonymous) {
                $row .= '<td class="rowfollow" align="center">'.htmlspecialchars((string) ($arr['anonymous'] ?? '')).'</td>';
            }
            if ($shouldShowClient) {
                $ipArr = array_filter([(string) ($arr['ipv4'] ?? ''), (string) ($arr['ipv6'] ?? '')]);
                foreach ($ipArr as &$_ip) {
                    $_ip = '<span class="nowrap">'.$_ip.$this->seedBoxRep->renderIcon($_ip, (int) ($arr['userid'] ?? 0)).'</span>';
                }
                unset($_ip);
                $row .= '<td class="rowfollow" align="center">'
                    .get_agent((string) ($arr['peer_id'] ?? ''), (string) ($arr['agent'] ?? ''))
                    .'<br/>'.((int) ($arr['port'] ?? 0))
                    .'</td>'
                    .'<td class="rowfollow" align="center">'.implode('<br/>', $ipArr).'</td>';
            }

            $claimButton = '';
            if (
                $showActionClaim
                && Claim::getConfigIsEnabled()
                && Carbon::parse((string) ($arr['added'] ?? ''))->addDays($claimTorrentTTL)->lte(Carbon::now())
            ) {
                $claim = $claimData->get($torrentId);
                if ((int) $viewer->id === (int) ($arr['userid'] ?? 0)) {
                    $claimButton = $this->claimRep->buildActionButtons($torrentId, $claim);
                } else {
                    $claimText = $claim
                        ? nexus_trans('claim.already_claimed')
                        : nexus_trans('claim.not_claim_yet');
                    $claimButton = '<button style="width:max-content;display:flex;align-items:center" disabled>'.$claimText.'</button>';
                }
            }
            $row .= '<td class="rowfollow" align="center">'.$claimButton.'</td>';
            $row .= '</tr>';
            $rows .= $row;
        }

        $table = '<table border="1" cellspacing="0" cellpadding="5" width="100%">'
            .'<tr>'.$header.'</tr>'
            .$rows
            .'</table>';

        return [$table, $totalSize];
    }

    // ─── Pager ───────────────────────────────────────────────────────────

    private function buildPager(int $count, int $page, int $id, string $type, array $lang): string
    {
        if ($count <= self::PAGE_SIZE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PAGE_SIZE);
        $base = htmlspecialchars('getusertorrentlistajax.php?userid='.$id.'&type='.$type.'&');
        $links = '';
        if ($page > 0) {
            $links .= '<a href="'.$base.'page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="'.$base.'page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }

    // ─── Lang ────────────────────────────────────────────────────────────

    /** @return array<string,string> */
    private function loadLang(): array
    {
        $path = base_path(get_langfile_path('getusertorrentlistajax.php'));
        $lang_getusertorrentlistajax = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_getusertorrentlistajax) ? $lang_getusertorrentlistajax : [];
    }
}
