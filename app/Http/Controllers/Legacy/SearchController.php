<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\MeiliSearchRepository;
use App\Repositories\SearchRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/search.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Authed paginated torrent search reachable from the search box
 * embedded in legacy chrome (`include/functions.php:2270` —
 * `<form action="search.php">`). Renders the standard
 * `torrenttable()` listing with the legacy column-header sort
 * links preserved.
 *
 * Original legacy flow (`public/search.php`, 163 LOC):
 *   1. `loggedinorreturn()` + `parked()` bootstrap.
 *   2. Whitelist filter chain: `categories.mode IN (browse-mode +
 *      maybe special-mode)`, optional `approval_status`, optional
 *      `banned='no'` for users without `seebanned`.
 *   3. Branch on `meilisearch.enabled`: when on, delegate to
 *      `MeiliSearchRepository::search`; otherwise build a
 *      `\Nexus\Database\NexusDB::table('torrents')` query with
 *      LIKE clauses driven by `?search_area=`.
 *   4. Sort by `?sort=N&type=asc|desc` mapped to a fixed column
 *      whitelist (`id/name/numfiles/comments/added/size/
 *      times_completed/seeders/leechers/owner`).
 *   5. Pagination via `pager()`; render via `torrenttable()`.
 *   6. When `?search_area` is outside the whitelist, the legacy
 *      script `write_log()`'d a moderator-tag entry — "user X is
 *      hacking search_area" — and silently fell through to a
 *      `name LIKE` search. Preserved verbatim.
 *
 * Replacement contract:
 *   - URL stays `/search.php` (`include/functions.php:2270` form
 *     submits to it). Inside `auth.nexus:nexus-web` middleware so
 *     guests redirect to login.
 *   - Below-`parked` accounts get `abort(403)` (legacy
 *     `parked() -> stderr() -> die`).
 *   - GET-only — the legacy form is `<form method="get">`.
 *   - Pagination knobs honoured: per-user `users.torrentsperpage`,
 *     fall back to `$torrentsperpage_main`, fall back to 50.
 *   - When the search string is empty or yields zero rows, render
 *     the same "Search results for ..." chrome-less notice the
 *     legacy script emitted via `stdmsg()`.
 *
 * Sweep findings:
 *   - The legacy `lang_search.php` does NOT exist; the search-page
 *     strings were borrowed from `lang_torrents.php`. Retained
 *     dependency through `nexus_trans('search.global_search')` and
 *     reuse of the existing `lang_torrents` keys for messages.
 *   - No menu seeders / `Update.php` references.
 *   - The `<form action="search.php">` snippet in
 *     `include/functions.php` keeps working unchanged.
 */
class SearchController extends Controller
{
    /** @var array<string,string> Map ?sort=N → SQL column. Mirrors legacy switch. */
    private const SORT_COLUMNS = [
        '1' => 'name',
        '2' => 'numfiles',
        '3' => 'comments',
        '4' => 'added',
        '5' => 'size',
        '6' => 'times_completed',
        '7' => 'seeders',
        '8' => 'leechers',
        '9' => 'owner',
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $search = (string) $request->input('search', '');
        $searchArea = (string) $request->input(
            'search_area',
            SearchRepository::SEARCH_AREA_TITLE,
        );

        $approvalStatus = $this->resolveApprovalStatus();
        $modeArr = $this->resolveModeArr();
        $banned = $this->resolveBanned();

        $count = 0;
        $rows = [];
        $rendererArgs = null;
        $torrentQuery = null;
        $tableTorrent = 'torrents';

        if ($search !== '') {
            $search = str_replace('.', ' ', $search);
            $searchArr = preg_split('/[\s]+/', $search, 10, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($this->shouldUseMeili($search)) {
                $rep = new MeiliSearchRepository;
                $params = $request->query->all();
                if ($approvalStatus !== null) {
                    $params['approval_status'] = $approvalStatus;
                }
                if ($banned !== null) {
                    $params['banned'] = $banned;
                }
                $params['incldead'] = 0;
                $params['mode'] = $modeArr;
                $result = $rep->search($params, (int) $viewer->id);
                $count = (int) ($result['total'] ?? 0);
                $rendererArgs = ['rows' => $result['list'] ?? []];
            } else {
                $tableCategory = 'categories';
                $torrentQuery = NexusDB::table($tableTorrent)
                    ->join($tableCategory, "$tableTorrent.category", '=', "$tableCategory.id")
                    ->whereIn("$tableCategory.mode", $modeArr);

                $this->applyAreaFilter($torrentQuery, $searchArea, $searchArr, $tableTorrent, $viewer);

                if ($approvalStatus !== null) {
                    $torrentQuery->where("$tableTorrent.approval_status", $approvalStatus);
                }
                if ($banned !== null) {
                    $torrentQuery->where("$tableTorrent.banned", $banned);
                }
                $count = (int) $torrentQuery->count();
            }
        }

        $perPage = $this->resolvePerPage($viewer);

        // Sort whitelist
        $column = 'id';
        $ascdesc = 'desc';
        $linkAscDesc = 'desc';
        $sortRaw = (string) $request->query('sort', '');
        $typeRaw = (string) $request->query('type', '');
        $addParam = '?search='.urlencode($search).'&search_area='.urlencode($searchArea).'&';
        if ($sortRaw !== '' && $typeRaw !== '') {
            $column = self::SORT_COLUMNS[$sortRaw] ?? 'id';
            if ($typeRaw === 'asc') {
                $ascdesc = 'ASC';
                $linkAscDesc = 'asc';
            } else {
                $ascdesc = 'DESC';
                $linkAscDesc = 'desc';
            }
            $addParam .= 'sort='.((int) $sortRaw).'&type='.$linkAscDesc.'&';
        }

        // Pagination via legacy `pager()` helper if available;
        // otherwise compute the offset/limit ourselves.
        if (function_exists('pager')) {
            [$pagerTop, $pagerBottom, $limit, $offset, $size, $page] = pager($perPage, $count, $addParam);
        } else {
            $page = max(0, (int) $request->query('page', 0));
            $totalPages = (int) max(1, ceil(max(0, $count) / max(1, $perPage)));
            $page = min($page, $totalPages - 1);
            $offset = $page * $perPage;
            $limit = $perPage;
            $pagerTop = $pagerBottom = '';
            $size = $perPage;
        }

        if ($search !== '' && $count > 0 && $rendererArgs === null && $torrentQuery !== null) {
            $fieldsStr = implode(', ', Torrent::getFieldsForList(true));
            $rendererArgs = [
                'rows' => $torrentQuery
                    ->selectRaw("$fieldsStr, categories.mode as search_box_id")
                    ->forPage($page + 1, $perPage)
                    ->orderBy("$tableTorrent.$column", $ascdesc)
                    ->get()
                    ->toArray(),
            ];
        }

        $title = (string) nexus_trans('search.global_search');
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $listing = '';
        if ($search !== '' && $count > 0 && $rendererArgs !== null && function_exists('torrenttable')) {
            ob_start();
            torrenttable(json_decode(json_encode($rendererArgs['rows']), true));
            $listing = $pagerTop.(string) ob_get_clean().$pagerBottom;
        } else {
            $msg = sprintf(
                '<p><b>%s "%s"</b></p><p>%s</p>',
                htmlspecialchars((string) nexus_trans('torrents.std_search_results_for'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                htmlspecialchars($search, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                htmlspecialchars((string) nexus_trans('torrents.std_try_again'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
            $listing = $msg;
        }

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
<div id="search-results">{$listing}</div>
</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * `torrent.approval_status_none_visible='no'` and the viewer
     * lacks `torrent-approval` permission ⇒ filter to APPROVAL_ALLOW
     * only. Otherwise the legacy script emitted no extra constraint.
     */
    private function resolveApprovalStatus(): ?int
    {
        if (get_setting('torrent.approval_status_none_visible') === 'no'
            && function_exists('user_can')
            && ! user_can('torrent-approval')) {
            return Torrent::APPROVAL_STATUS_ALLOW;
        }

        return null;
    }

    /**
     * @return array<int>
     */
    private function resolveModeArr(): array
    {
        $modes = [SearchBox::getBrowseMode()];
        if (SearchBox::isSpecialEnabled()
            && function_exists('user_can')
            && user_can('view_special_torrent')) {
            $modes[] = SearchBox::getSpecialMode();
        }

        return array_map('intval', $modes);
    }

    /**
     * Legacy: when the viewer lacks `seebanned`, the WHERE clause
     * pinned `banned='no'` to hide banned torrents from non-staff.
     * Preserved verbatim.
     */
    private function resolveBanned(): ?string
    {
        if (function_exists('user_can') && user_can('seebanned')) {
            return null;
        }

        return 'no';
    }

    private function shouldUseMeili(string $search): bool
    {
        return get_setting('meilisearch.enabled') === 'yes' && $search !== '';
    }

    /**
     * @param  array<int,string>  $searchArr
     */
    private function applyAreaFilter($torrentQuery, string $searchArea, array $searchArr, string $tableTorrent, $viewer): void
    {
        if ($searchArea === SearchRepository::SEARCH_AREA_TITLE) {
            foreach ($searchArr as $q) {
                $like = "%{$q}%";
                $torrentQuery->where(function ($qBuilder) use ($like, $tableTorrent) {
                    $qBuilder->where("$tableTorrent.name", 'like', $like)
                        ->orWhere("$tableTorrent.small_descr", 'like', $like);
                });
            }
        } elseif ($searchArea === SearchRepository::SEARCH_AREA_DESC) {
            foreach ($searchArr as $q) {
                $torrentQuery->where("$tableTorrent.descr", 'like', "%{$q}%");
            }
        } elseif ($searchArea === SearchRepository::SEARCH_AREA_OWNER) {
            $torrentQuery->join('users', "$tableTorrent.owner", '=', 'users.id');
            foreach ($searchArr as $q) {
                $torrentQuery->where('users.username', 'like', "%{$q}%");
            }
        } elseif ($searchArea === SearchRepository::SEARCH_AREA_IMDB) {
            foreach ($searchArr as $q) {
                $torrentQuery->where("$tableTorrent.url", 'like', "%{$q}%");
            }
        } else {
            // Unknown ?search_area=. Legacy script logged a moderator
            // alert and fell through to a `name LIKE` search. Preserved
            // bit-for-bit so security audits keep finding the alert.
            foreach ($searchArr as $q) {
                $torrentQuery->where("$tableTorrent.name", 'like', "%{$q}%");
            }
            if (function_exists('write_log')) {
                $username = (string) ($viewer->username ?? '');
                $ip = (string) ($viewer->ip ?? '');
                write_log(
                    'User '.$username.','.$ip.' is hacking search_area field in /search.php',
                    'mod',
                );
            }
        }
    }

    private function resolvePerPage($viewer): int
    {
        $userPref = (int) ($viewer->torrentsperpage ?? 0);
        if ($userPref > 0) {
            return $userPref;
        }
        $globalPref = (int) ($GLOBALS['torrentsperpage_main'] ?? 0);
        if ($globalPref > 0) {
            return $globalPref;
        }

        return 50;
    }
}
