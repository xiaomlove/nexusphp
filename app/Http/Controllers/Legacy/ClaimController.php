<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Claim;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ClaimRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/claim.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Authed listing of `claims` rows filtered by either `?torrent_id`
 * (every user who claimed a given torrent) or `?uid` (every torrent
 * a given user has claimed). Read-only — no POST branch. The
 * "settle this claim now" / "cancel claim" action buttons are
 * rendered through `App\Repositories\ClaimRepository::buildActionButtons`,
 * which returns inline HTML with jQuery-AJAX bindings; the underlying
 * XHR endpoint is `ajax.php?action=settleClaim` (out of scope for
 * this migration). Action buttons appear only when the listing is
 * scoped to the current user (`?uid == $CURUSER['id']`).
 *
 * URL preserved exactly so:
 *   - `include/functions.php:2265` (the user-header snippet
 *     `[<a href="claim.php?uid=N">…</a>]`),
 *   - `app/Livewire/TorrentDetail.php:346` (the
 *     `detailsUrl => '/claim.php?torrent_id=N'` link rendered on
 *     the modern torrent-detail page),
 *   - `public/details.php:331` (the `<a href="claim.php?torrent_id=N">`
 *     deep link inside the legacy details panel),
 *   - `public/userdetails.php:330` (the `<a href="claim.php?uid=N">`
 *     deep link inside the legacy user profile),
 *   - `tests/e2e/behavior/torrent-detail-claim.spec.ts:33` and
 *     `tests/Feature/Livewire/TorrentDetailClaimTest.php:94`
 *     (which both pin the `/claim.php?torrent_id=…` URL contract),
 *
 * keep working without template/JS changes. The two stale lang
 * files (`lang/{chs,ja}/lang_claim.php`) are deleted in the same
 * PR — the legacy script already used Laravel translations
 * (`nexus_trans('claim.*')`), so the `lang_claim` arrays were
 * already dead code.
 */
class ClaimController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * Whitelist of `?sort=` values the legacy script accepted.
     * Keys map to the Laravel translation key the legacy form
     * rendered as the column header; values keep the same SQL
     * column / virtual column name the SELECT below emits.
     *
     * The two virtual columns (`seed_time`, `uploaded`) trigger the
     * `JOIN snatched ... ORDER BY (snatched.X - claims.Y_begin)`
     * branch so the user can sort by "this month's seed time / upload
     * delta", which is what the dashboard cares about.
     *
     * @var array<string,string>
     */
    private const SORT_LABELS = [
        'created_at' => 'claim.th_claim_at',
        'last_settle_at' => 'claim.th_last_settle',
        'seed_time' => 'claim.th_seed_time_this_month',
        'uploaded' => 'claim.th_uploaded_this_month',
    ];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }

        $sort = (string) $request->query('sort', 'created_at');
        if (! isset(self::SORT_LABELS[$sort])) {
            $sort = 'created_at';
        }
        $order = (string) $request->query('order', 'asc');
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'asc';
        }

        $torrentIdRaw = $request->query('torrent_id');
        $uidRaw = $request->query('uid');

        $torrentId = 0;
        $uid = 0;
        $headerLink = '';
        $titleKey = '';
        $pagerParam = '';
        $showActions = false;
        $query = null;

        if ($torrentIdRaw !== null && $torrentIdRaw !== '') {
            $torrentId = (int) $torrentIdRaw;
            if ($torrentId <= 0) {
                abort(422, 'Invalid torrent_id: '.htmlspecialchars((string) $torrentIdRaw));
            }
            $torrent = Torrent::query()->where('id', $torrentId)->first(Torrent::$commentFields);
            if (! $torrent) {
                abort(404, 'Invalid torrent_id: '.$torrentId);
            }
            $titleKey = 'claim.title_for_torrent';
            $query = Claim::query()->where('torrent_id', $torrentId);
            $pagerParam = sprintf('?torrent_id=%d&sort=%s&order=%s', $torrentId, $sort, $order);
            $headerLink = sprintf(
                '<a href="details.php?id=%d"><b>&nbsp;%s</b></a>',
                $torrentId,
                htmlspecialchars((string) $torrent->name),
            );
        } elseif ($uidRaw !== null && $uidRaw !== '') {
            $uid = (int) $uidRaw;
            if ($uid <= 0) {
                abort(422, 'Invalid uid: '.htmlspecialchars((string) $uidRaw));
            }
            $userRow = User::query()->where('id', $uid)->first(User::$commonFields);
            if (! $userRow) {
                abort(404, 'Invalid uid: '.$uid);
            }
            $titleKey = 'claim.title_for_user';
            $query = Claim::query()->where('uid', $uid);
            $pagerParam = sprintf('?uid=%d&sort=%s&order=%s', $uid, $sort, $order);
            $headerLink = sprintf(
                '<a href="userdetails.php?id=%d"><b>&nbsp;%s</b></a>',
                $uid,
                htmlspecialchars((string) $userRow->username),
            );
            // Action buttons (settle now / cancel) only appear when the
            // viewer is looking at their own claim list. Mirrors the
            // legacy `if ($uid == $CURUSER['id'])` gate.
            $showActions = ((int) $viewer->id === $uid);
        } else {
            abort(422, 'Require torrent_id or uid');
        }

        $title = (string) nexus_trans($titleKey);

        $total = (clone $query)->count();
        $totalPages = (int) max(1, ceil($total / self::PER_PAGE));
        $page = max(1, (int) $request->query('page', 1));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $listing = (clone $query)
            ->with(['user', 'torrent', 'snatch'])
            ->offset($offset)
            ->limit(self::PER_PAGE);

        // Both virtual sort keys reach into the joined `snatched`
        // table; the literal `$order` value is a hard-whitelisted
        // 'asc' / 'desc' so SQL injection through the orderByRaw
        // is impossible.
        if ($sort === 'seed_time') {
            $listing->join('snatched', 'claims.snatched_id', '=', 'snatched.id')
                ->orderByRaw('(snatched.seedtime - claims.seed_time_begin) '.$order);
        } elseif ($sort === 'uploaded') {
            $listing->join('snatched', 'claims.snatched_id', '=', 'snatched.id')
                ->orderByRaw('(snatched.uploaded - claims.uploaded_begin) '.$order);
        } else {
            $listing->orderBy($sort, $order);
        }

        $rows = $listing->selectRaw('claims.*')->get();

        $body = $this->renderListing(
            title: $title,
            headerLink: $headerLink,
            rows: $rows,
            pagerParam: $pagerParam,
            page: $page,
            totalPages: $totalPages,
            total: $total,
            sort: $sort,
            order: $order,
            showActions: $showActions,
            viewerId: (int) $viewer->id,
        );

        $titleHtml = htmlspecialchars($title);

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleHtml}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * @param  iterable<\App\Models\Claim>  $rows
     */
    private function renderListing(
        string $title,
        string $headerLink,
        iterable $rows,
        string $pagerParam,
        int $page,
        int $totalPages,
        int $total,
        string $sort,
        string $order,
        bool $showActions,
        int $viewerId,
    ): string {
        $titleHtml = htmlspecialchars($title);
        $heading = '<h1 align="center">'.$titleHtml.$headerLink.'</h1>';
        $filterForm = $this->renderFilterForm($pagerParam, $sort, $order);

        $thAction = '';
        if ($showActions) {
            $thAction = '<td class="colhead" align="center">'
                .htmlspecialchars((string) nexus_trans('claim.th_action')).'</td>';
        }

        $thead = '<tr>'
            .$this->headerCell('claim.th_id')
            .$this->headerCell('claim.th_username')
            .$this->headerCell('claim.th_torrent_name')
            .$this->headerCell('claim.th_torrent_size')
            .$this->headerCell('claim.th_torrent_ttl')
            .$this->headerCell('claim.th_claim_at')
            .$this->headerCell('claim.th_last_settle')
            .$this->headerCell('claim.th_seed_time_this_month')
            .$this->headerCell('claim.th_uploaded_this_month')
            .$this->headerCell('claim.th_reached_or_not')
            .$thAction
            .'</tr>';

        $now = Carbon::now();
        $seedTimeRequiredHours = (int) Claim::getConfigStandardSeedTimeHours();
        $uploadedRequiredTimes = (int) Claim::getConfigStandardUploadedTimes();
        $claimRep = new ClaimRepository;

        $tbody = '';
        foreach ($rows as $row) {
            $tbody .= $this->renderRow(
                $row,
                $now,
                $seedTimeRequiredHours,
                $uploadedRequiredTimes,
                $claimRep,
                $showActions,
            );
        }

        $pager = $this->renderPager($total, $page, $totalPages, $pagerParam);

        return $heading
            .$pager
            .$filterForm
            .'<table id="claim-table" width="100%" cellpadding="5">'
            .$thead
            .$tbody
            .'</table>'
            .$pager;
    }

    private function renderFilterForm(string $pagerParam, string $sort, string $order): string
    {
        $sortText = htmlspecialchars((string) nexus_trans('nexus.sort'));
        $orderText = htmlspecialchars((string) nexus_trans('nexus.order'));
        $selectOne = htmlspecialchars((string) nexus_trans('nexus.select_one_please'));
        $submitText = htmlspecialchars((string) nexus_trans('label.submit'));
        $resetText = htmlspecialchars((string) nexus_trans('label.reset'));

        $sortOptions = '';
        foreach (self::SORT_LABELS as $name => $key) {
            $selected = $name === $sort ? ' selected' : '';
            $sortOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($name),
                $selected,
                htmlspecialchars((string) nexus_trans($key)),
            );
        }
        $orderOptions = '';
        foreach (['asc' => 'nexus.asc', 'desc' => 'nexus.desc'] as $name => $key) {
            $selected = $name === $order ? ' selected' : '';
            $orderOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $name,
                $selected,
                htmlspecialchars((string) nexus_trans($key)),
            );
        }

        $action = htmlspecialchars('/claim.php'.$pagerParam, ENT_QUOTES);

        return <<<HTML
<div>
    <form id="filterForm" action="{$action}" method="get">
        <span>{$sortText}:</span>
        <select name="sort">
            <option value="">-{$selectOne}-</option>
            {$sortOptions}
        </select>
        &nbsp;&nbsp;
        <span>{$orderText}:</span>
        <select name="order">
            <option value="">-{$selectOne}-</option>
            {$orderOptions}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$submitText}">
        <input type="button" id="reset" value="{$resetText}" onclick="document.querySelector('#filterForm select[name=sort]').value='';document.querySelector('#filterForm select[name=order]').value='';">
    </form>
</div>
HTML;
    }

    private function headerCell(string $transKey): string
    {
        return '<td class="colhead" align="center">'
            .htmlspecialchars((string) nexus_trans($transKey))
            .'</td>';
    }

    private function renderRow(
        Claim $row,
        Carbon $now,
        int $seedTimeRequiredHours,
        int $uploadedRequiredTimes,
        ClaimRepository $claimRep,
        bool $showActions,
    ): string {
        // Mirror the legacy "did the user reach the standard"
        // disjunction: either (cumulative seed-time delta >= required
        // hours) OR (cumulative upload delta >= required-times *
        // torrent.size). Either branch satisfies the claim.
        $seedTimeDelta = (int) bcsub((string) ($row->snatch->seedtime ?? '0'), (string) $row->seed_time_begin);
        $uploadedDelta = (int) bcsub((string) ($row->snatch->uploaded ?? '0'), (string) $row->uploaded_begin);
        $reached = (
            $seedTimeDelta >= $seedTimeRequiredHours * 3600
            || $uploadedDelta >= $uploadedRequiredTimes * (int) $row->torrent->size
        ) ? 'Yes' : 'No';

        $username = htmlspecialchars((string) ($row->user->username ?? ''));
        $torrentName = htmlspecialchars((string) ($row->torrent->name ?? ''));
        $torrentSize = mksize((int) ($row->torrent->size ?? 0));
        $torrentAdded = $row->torrent->added;
        $ttl = $torrentAdded ? mkprettytime($torrentAdded->diffInSeconds($now, true)) : '-';

        $actionCell = '';
        if ($showActions) {
            $actionCell = '<td class="rowfollow nowrap" align="center">'
                .$claimRep->buildActionButtons($row->torrent_id, $row, 1)
                .'</td>';
        }

        return '<tr>'
            .'<td class="rowfollow nowrap" align="center">'.(int) $row->id.'</td>'
            .'<td class="rowfollow" align="left"><a href="userdetails.php?id='.(int) $row->uid.'">'.$username.'</a></td>'
            .'<td class="rowfollow" align="left"><a href="details.php?id='.(int) $row->torrent_id.'">'.$torrentName.'</a></td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars($torrentSize).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars($ttl).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars((string) format_datetime($row->created_at)).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars((string) format_datetime($row->last_settle_at)).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars(mkprettytime(max(0, $seedTimeDelta))).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.htmlspecialchars(mksize(max(0, $uploadedDelta))).'</td>'
            .'<td class="rowfollow nowrap" align="center">'.$reached.'</td>'
            .$actionCell
            .'</tr>';
    }

    private function renderPager(int $total, int $page, int $totalPages, string $pagerParam): string
    {
        if ($total <= self::PER_PAGE) {
            return '';
        }
        $links = '';
        if ($page > 1) {
            $links .= '<a href="/claim.php'.$pagerParam.'&page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="/claim.php'.$pagerParam.'&page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }
}
