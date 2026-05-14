<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/user-ban-log.php` (deleted in the same PR).
 *
 * Phase 2 batch #11 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (37 LOC):
 *   1. `require "../include/bittorrent.php";` — bootstraps the
 *      legacy globals, including `dbconn()`.
 *   2. (No auth / class check at all.) Builds an
 *      `\App\Models\UserBanLog::query()` filtered by an optional
 *      `?q=` substring on `username`.
 *   3. `pager(50, $total, "?")` → renders the legacy chrome plus a
 *      `build_table([...header...], $rows)` of the matching log
 *      entries, ordered by `id DESC`.
 *
 * The legacy script's lack of an auth check is the only obvious
 * security regression in the file — the page is linked exclusively
 * from `public/complains.php:170` (a staff-only "view ban log" link
 * next to each complainant), and Bens on the live site rely on the
 * site nav menu to gate access. This controller tightens the contract
 * in line with the rest of Phase 2: guest → login redirect, below
 * `User::CLASS_ADMINISTRATOR` → 403. The same pattern was applied to
 * `DonorlistController` in batch #9; the legacy `stderr()` 200 body
 * is not reproduced.
 *
 * Pagination is preserved (50 rows per page via `?page=<n>`) so the
 * `complains.php` deep link `?q=<username>` keeps working. The legacy
 * `pager()` helper rendered a wider control with shortcuts and jump
 * links, which depends on `$lang_functions` and a CSS class set the
 * chrome-less envelope does not load — the simplified controls
 * preserve the observable next/prev links so the page is still
 * navigable.
 */
class UserBanLogController extends Controller
{
    /** Rows per page. Matches the legacy `pager(50, ...)` argument. */
    private const PER_PAGE = 50;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));
        $query = NexusDB::table('user_ban_logs');
        if ($q !== '') {
            $query->where('username', 'like', '%'.$q.'%');
        }

        $count = (int) (clone $query)->count();
        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::PER_PAGE;

        $rows = (clone $query)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get(['id', 'uid', 'username', 'reason', 'created_at']);

        $qEsc = htmlspecialchars($q, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $action = htmlspecialchars(
            (string) $request->getRequestUri(),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $body = '<h1 style="text-align: center">User ban log</h1>'."\n"
            .'<form id="filterForm" action="'.$action.'" method="get">'."\n"
            .'<input id="q" type="text" name="q" value="'.$qEsc.'" placeholder="username">'."\n"
            .'<input type="submit">'."\n"
            .'<input type="reset" onclick="document.getElementById(\'q\').value=\'\';document.getElementById(\'filterForm\').submit();">'."\n"
            .'</form>'."\n"
            .'<table align="center" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="colhead">ID</td>'
            .'<td class="colhead" align="left">UID</td>'
            .'<td class="colhead" align="left">Username</td>'
            .'<td class="colhead" align="left">Reason</td>'
            .'<td class="colhead" align="left">Created at</td></tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $id = (int) ($arr['id'] ?? 0);
            $uid = (int) ($arr['uid'] ?? 0);
            $username = htmlspecialchars(
                (string) ($arr['username'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $reason = htmlspecialchars(
                (string) ($arr['reason'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $createdAt = htmlspecialchars(
                (string) ($arr['created_at'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $body .= '<tr>'
                .'<td>'.$id.'</td>'
                .'<td align="left">'.$uid.'</td>'
                .'<td align="left">'.$username.'</td>'
                .'<td align="left">'.$reason.'</td>'
                .'<td align="left">'.$createdAt.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n"
            .$this->renderPager($count, $page, $q);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>User ban log</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Render a minimal `Prev / N / Next` pager block, preserving the
     * current `?q=<filter>` so paginated views keep the same filter
     * the user typed into the search box.
     */
    private function renderPager(int $count, int $page, string $q): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PER_PAGE);
        $qParam = $q === '' ? '' : '&q='.rawurlencode($q);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="user-ban-log.php?page='.($page - 1).$qParam.'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="user-ban-log.php?page='.($page + 1).$qParam.'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}
