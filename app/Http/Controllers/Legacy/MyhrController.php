<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\HitAndRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/myhr.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. Defaults to current user; `?userid=<n>` for viewing others
 *      (requires `viewhistory` permission).
 *   3. Renders a status filter bar (inspecting/unreached/reached/pardoned).
 *   4. Paginated table (50/page) of H&R records showing torrent name,
 *      uploaded/downloaded, ratio, seed time required, completed at,
 *      TTL, comment, and a "Remove" action button.
 *   5. Remove button JS: POST to `ajax.php` with
 *      `action=removeHitAndRun`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Cross-user view requires `viewhistory` permission.
 *   - Authenticated → 200 with chrome-less HTML envelope.
 *   - Filters: `?status=`, `?userid=`, `?q=` (H&R ID search).
 *   - Pagination via `?page=<n>` (50/page).
 *   - Remove button + JS preserved (calls existing `ajax.php`).
 */
class MyhrController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $userid = (int) $user->id;
        if ($request->query('userid') !== null) {
            $requestedUid = (int) $request->query('userid');
            if ($requestedUid !== (int) $user->id && ! user_can('viewhistory')) {
                abort(403);
            }
            $userid = $requestedUid;
        }

        $userInfo = User::query()->find($userid, User::$commonFields);
        if ($userInfo === null) {
            abort(404);
        }

        $status = (string) $request->query('status', HitAndRun::STATUS_INSPECTING);
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $allStatus = HitAndRun::listStatus();

        // Build status filter links
        $pagerParams = ['userid' => $userid, 'status' => $status];
        $headerFilters = [];
        foreach ($allStatus as $key => $value) {
            $filterParams = array_merge($pagerParams, ['status' => $key]);
            $activeClass = ($key == $status) ? 'faqlink' : '';
            $headerFilters[] = '<a href="myhr.php?'.http_build_query($filterParams).'" class="'.$activeClass.'"><b>'.$value['text'].'</b></a>';
        }

        $pageTitle = htmlspecialchars($userInfo->username).' - H&amp;R';

        // Query
        $baseQuery = HitAndRun::query()->where('uid', $userid)->where('status', $status);
        $total = (clone $baseQuery)->count();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $queryBuilder = (clone $baseQuery)
            ->with([
                'torrent' => fn ($q) => $q->select(['id', 'size', 'name', 'category']),
                'torrent.basic_category',
                'snatch',
                'user' => fn ($q) => $q->select(['id', 'lang']),
                'user.language',
            ])
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->orderByDesc('id');

        if ($q !== '') {
            $queryBuilder->where('id', $q);
        }

        $list = $queryBuilder->get();

        // Render
        $body = '<h1>'.$pageTitle.'</h1>'."\n";
        $body .= '<p>'.implode(' | ', $headerFilters).'</p>'."\n";

        // Filter form
        $qEsc = htmlspecialchars($q);
        $body .= '<form id="filterForm" action="myhr.php" method="get">'
            .'<input type="hidden" name="userid" value="'.$userid.'">'
            .'<input type="hidden" name="status" value="'.htmlspecialchars($status).'">'
            .'<input id="q" type="text" name="q" value="'.$qEsc.'" placeholder="H&amp;R ID">'
            .'<input type="submit">'
            .'</form>'."\n";

        $body .= $this->renderPager($total, $page, $totalPages, $pagerParams);
        $body .= $this->renderTable($list, $user, $status);
        $body .= $this->renderPager($total, $page, $totalPages, $pagerParams);

        // JS for remove button
        $cancelBonus = get_setting('bonus.cancel_hr');
        $removeMsg = nexus_trans('hr.remove_confirm_msg', ['bonus' => $cancelBonus]);
        $body .= <<<JS
<script>
jQuery('#hr-table').on('click', '.remove-hr', function () {
    var id = jQuery(this).attr('data-id')
    layer.confirm('{$removeMsg}', function (index) {
        jQuery.post('/hit-and-run/remove', {"params": {"id": id}}, function (response) {
            if (response.ret != 0) { layer.alert(response.msg); return; }
            window.location.reload()
        }, 'json')
    })
})
</script>
JS;

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$pageTitle}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderTable($list, $user, string $status): string
    {
        $body = '<table width="100%" id="hr-table" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead" align="center">ID</td>'
            .'<td class="colhead" align="center">Torrent</td>'
            .'<td class="colhead" align="center">Uploaded</td>'
            .'<td class="colhead" align="center">Downloaded</td>'
            .'<td class="colhead" align="center">Ratio</td>'
            .'<td class="colhead" align="center">Seed Time Required</td>'
            .'<td class="colhead" align="center">Completed At</td>'
            .'<td class="colhead" align="center">TTL</td>'
            .'<td class="colhead" align="center">Comment</td>'
            .'<td class="colhead" align="center">Action</td>'
            .'</tr>'."\n";

        foreach ($list as $row) {
            $torrentName = optional($row->torrent)->name ?? '—';
            $uploaded = $row->snatch ? mksize($row->snatch->uploaded) : '0';
            $downloaded = $row->snatch ? mksize($row->snatch->downloaded) : '0';
            $ratio = $row->snatch ? get_hr_ratio($row->snatch->uploaded, $row->snatch->downloaded) : '---';
            $completedAt = $row->snatch ? format_datetime($row->snatch->completedat) : '---';

            $actionHtml = '<td class="rowfollow nowrap" align="center">';
            if ((int) $row->uid === (int) $user->id && in_array($row->status, HitAndRun::CAN_PARDON_STATUS)) {
                $actionHtml .= '<input class="remove-hr" type="button" value="Remove" data-id="'.$row->id.'">';
            }
            $actionHtml .= '</td>';

            $body .= '<tr>'
                .'<td class="rowfollow nowrap" align="center">'.$row->id.'</td>'
                .'<td class="rowfollow" align="left"><a href="details.php?id='.$row->torrent_id.'">'.htmlspecialchars($torrentName).'</a></td>'
                .'<td class="rowfollow nowrap" align="center">'.$uploaded.'</td>'
                .'<td class="rowfollow nowrap" align="center">'.$downloaded.'</td>'
                .'<td class="rowfollow nowrap" align="center">'.$ratio.'</td>'
                .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars((string) $row->seedTimeRequired).'</td>'
                .'<td class="rowfollow nowrap" align="center">'.$completedAt.'</td>'
                .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars((string) $row->inspectTimeLeft).'</td>'
                .'<td class="rowfollow nowrap" align="left" style="padding-left: 10px">'.nl2br(htmlspecialchars(trim((string) $row->comment))).'</td>'
                .$actionHtml
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderPager(int $total, int $page, int $totalPages, array $params): string
    {
        if ($total <= self::PER_PAGE) {
            return '';
        }
        $links = '';
        if ($page > 1) {
            $links .= '<a href="myhr.php?'.http_build_query(array_merge($params, ['page' => $page - 1])).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="myhr.php?'.http_build_query(array_merge($params, ['page' => $page + 1])).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}
